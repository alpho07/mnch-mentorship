<?php

namespace App\Services\Rag;

use App\Models\RagChunk;
use App\Models\RagDocument;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class InAppRagEngine
{
    public function __construct(
        private readonly DocumentTextExtractor $extractor,
        private readonly ExternalAiProvider $provider,
    ) {}

    public function ingest(RagDocument $document, string $absolutePath): array
    {
        $sections = $this->extractor->extract($absolutePath, $document->extension);
        $chunks = $this->extractor->chunk($sections);

        if ($chunks === []) {
            throw new RuntimeException('No readable text could be extracted from the document.');
        }

        $embeddingModel = $this->provider->embeddingModel();

        DB::transaction(function () use ($document, $chunks, $embeddingModel): void {
            $document->chunks()->delete();

            $chunkIndex = 0;

            foreach (array_chunk($chunks, max(1, (int) config('rag.embeddings.batch_size', 32)), true) as $batch) {
                $embeddings = $this->provider->embed(array_column($batch, 'content'));

                foreach (array_values($batch) as $offset => $chunk) {
                    RagChunk::create([
                        'rag_document_id' => $document->id,
                        'chunk_index' => $chunkIndex++,
                        'locator_type' => $chunk['locator_type'] ?? null,
                        'locator' => $chunk['locator'] ?? null,
                        'content' => $chunk['content'],
                        'content_sha256' => hash('sha256', $chunk['content']),
                        'embedding' => $embeddings[$offset] ?? null,
                        'embedding_model' => $embeddingModel,
                    ]);
                }
            }
        });

        return [
            'document_id' => 'rag-document-'.$document->id,
            'chunk_count' => count($chunks),
            'page_or_slide_count' => count($sections),
            'engine' => 'external',
            'embedding_model' => $embeddingModel,
        ];
    }

    public function ask(string $question, int $topK): array
    {
        $queryEmbedding = $this->provider->embed([$question])[0] ?? null;
        if (! is_array($queryEmbedding) || $queryEmbedding === []) {
            throw new RuntimeException('Embedding provider returned an empty query vector.');
        }

        $sources = RagChunk::query()
            ->with('document')
            ->whereNotNull('embedding')
            ->whereHas('document', fn ($query) => $query->where('status', RagDocument::STATUS_READY))
            ->latest()
            ->limit((int) config('rag.search_pool_limit', 1000))
            ->get()
            ->map(function (RagChunk $chunk) use ($queryEmbedding): array {
                return [
                    'score' => $this->cosine($queryEmbedding, $chunk->embedding ?? []),
                    'document' => $chunk->document?->title ?? 'Document',
                    'page' => $chunk->locator_type === 'page' && is_numeric($chunk->locator) ? (int) $chunk->locator : null,
                    'slide' => $chunk->locator_type === 'slide' && is_numeric($chunk->locator) ? (int) $chunk->locator : null,
                    'locator_type' => $chunk->locator_type,
                    'locator' => $chunk->locator,
                    'content' => $chunk->content,
                ];
            })
            ->sortByDesc('score')
            ->take($topK)
            ->values()
            ->all();

        if ($sources === []) {
            throw new RuntimeException('No ready indexed documents are available for search.');
        }

        $answer = $this->provider->answer($question, $sources);

        return [
            'answer' => $answer['answer'],
            'sources' => $sources,
            'model' => $answer['model'],
            'token_usage' => $answer['token_usage'] ?? null,
        ];
    }

    public function health(): array
    {
        return $this->provider->health();
    }

    private function cosine(array $a, array $b): float
    {
        $dot = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $length = min(count($a), count($b));

        for ($i = 0; $i < $length; $i++) {
            $x = (float) $a[$i];
            $y = (float) $b[$i];
            $dot += $x * $y;
            $normA += $x * $x;
            $normB += $y * $y;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($normA) * sqrt($normB));
    }
}
