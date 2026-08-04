<?php

namespace App\Jobs;

use App\Models\RagDocument;
use App\Services\Rag\RagClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class ProcessRagDocument implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 120, 300];

    public int $uniqueFor = 3600;

    public function __construct(public int $documentId)
    {
        $this->onQueue('default');
    }

    public function uniqueId(): string
    {
        return (string) $this->documentId;
    }

    public function handle(RagClient $client): void
    {
        $document = RagDocument::findOrFail($this->documentId);

        if (! config('rag.enabled')) {
            $this->markFailed($document, 'RAG is disabled.');

            return;
        }

        if ($document->status === RagDocument::STATUS_READY) {
            return;
        }

        $duplicate = RagDocument::query()
            ->whereKeyNot($document->getKey())
            ->where('sha256', $document->sha256)
            ->whereIn('status', [RagDocument::STATUS_PROCESSING, RagDocument::STATUS_READY])
            ->first();

        if ($duplicate) {
            $this->markFailed($document, 'Duplicate document checksum already exists; indexing skipped.');

            return;
        }

        $document->forceFill([
            'status' => RagDocument::STATUS_PROCESSING,
            'processing_started_at' => now(),
            'failed_at' => null,
            'error_message' => null,
        ])->save();

        try {
            if (! $document->fileExists()) {
                throw new RuntimeException('Stored document file is missing.');
            }

            $absolutePath = Storage::disk($document->disk)->path($document->path);
            $response = $client->ingest($absolutePath, $document->title);

            $document->forceFill([
                'status' => RagDocument::STATUS_READY,
                'external_document_id' => $response['document_id'] ?? $response['id'] ?? $response['external_document_id'] ?? $document->external_document_id,
                'page_or_slide_count' => $response['page_count'] ?? $response['slide_count'] ?? $response['page_or_slide_count'] ?? $document->page_or_slide_count,
                'chunk_count' => $response['chunk_count'] ?? $response['chunks'] ?? $document->chunk_count,
                'metadata' => array_merge($document->metadata ?? [], [
                    'ingest_response' => $this->metadataSummary($response),
                ]),
                'processed_at' => now(),
                'failed_at' => null,
                'error_message' => null,
            ])->save();
        } catch (\Throwable $e) {
            $message = $client->sanitizeError($e->getMessage());
            $this->markFailed($document, $message);

            Log::warning('RAG document ingestion failed', [
                'document_id' => $document->id,
                'status' => $document->status,
                'error' => $message,
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        $document = RagDocument::find($this->documentId);

        if ($document) {
            $this->markFailed($document, app(RagClient::class)->sanitizeError($exception->getMessage()));
        }
    }

    private function markFailed(RagDocument $document, string $message): void
    {
        $document->forceFill([
            'status' => RagDocument::STATUS_FAILED,
            'failed_at' => now(),
            'error_message' => $message,
        ])->save();
    }

    private function metadataSummary(array $response): array
    {
        return collect($response)
            ->except(['content', 'text', 'chunks'])
            ->take(20)
            ->all();
    }
}
