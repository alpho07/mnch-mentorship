<?php

namespace App\Services\Rag;

use App\Models\RagDocument;
use App\Models\RagDocumentOutline;
use App\Support\RagSourceFormatter;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class RagClient
{
    public function __construct(
        private readonly InAppRagEngine $engine,
        private readonly ExternalAiProvider $provider,
        private readonly DocumentTextExtractor $extractor,
    ) {}

    public function ingest(string $absolutePath, string $title): array
    {
        if ($this->usesInAppEngine()) {
            throw new RuntimeException('Use InAppRagEngine::ingest for external RAG document ingestion.');
        }

        if (! is_file($absolutePath) || ! is_readable($absolutePath)) {
            throw new RuntimeException('Document file is not readable.');
        }

        $response = $this->request((int) config('rag.ingest_timeout'))
            ->attach('file', fopen($absolutePath, 'r'), basename($absolutePath))
            ->post('/ingest', [
                'title' => $this->sanitizeTitle($title),
            ]);

        return $this->decodeResponse($response->throw()->json());
    }

    public function ask(string $question, int $topK): array
    {
        $started = hrtime(true);

        try {
            if ($this->usesInAppEngine()) {
                $data = $this->engine->ask($question, $this->clampTopK($topK));
                $answer = $this->stripThink((string) ($data['answer'] ?? ''));

                if ($answer === '') {
                    throw new RuntimeException('RAG service returned an empty answer.');
                }

                return [
                    'answer' => $answer,
                    'citations' => $this->normalizeSources($data['sources'] ?? $data['citations'] ?? []),
                    'retrieved_sources' => $this->normalizeSources($data['sources'] ?? []),
                    'model' => isset($data['model']) ? (string) $data['model'] : null,
                    'latency_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
                    'token_usage' => $this->usageMetadata($data),
                ];
            }

            if ($this->usesHybridEngine()) {
                return $this->askHybrid($question, $topK, $started);
            }

            $payload = [
                'question' => $question,
                'top_k' => $this->clampTopK($topK),
            ];

            $response = $this->request((int) config('rag.request_timeout'))
                ->post('/ask', $payload);

            $data = $this->decodeResponse($response->throw()->json());
            $answer = $this->stripThink((string) ($data['answer'] ?? ''));

            if ($answer === '') {
                throw new RuntimeException('RAG service returned an empty answer.');
            }

            return [
                'answer' => $answer,
                'citations' => $this->normalizeSources($data['sources'] ?? $data['citations'] ?? []),
                'retrieved_sources' => $this->normalizeSources($data['sources'] ?? []),
                'model' => isset($data['model']) ? (string) $data['model'] : null,
                'latency_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
                'token_usage' => $this->usageMetadata($data),
            ];
        } catch (ConnectionException|RequestException|RuntimeException $e) {
            Log::warning('RAG ask failed', [
                'error' => $this->sanitizeError($e->getMessage()),
                'top_k' => $this->clampTopK($topK),
            ]);

            throw new RuntimeException($this->sanitizeError($e->getMessage()), previous: $e);
        }
    }

    public function health(): array
    {
        if ($this->usesInAppEngine()) {
            return $this->engine->health();
        }

        if ($this->usesHybridEngine()) {
            $local = $this->localHealth();

            return [
                'ok' => (bool) ($local['ok'] ?? false) && $this->provider->chatReady(),
                'status' => $local['status'] ?? null,
                'body' => [
                    'engine' => 'hybrid',
                    'local' => $local['body'] ?? null,
                    'chat_provider' => config('rag.chat.provider'),
                    'chat_model' => $this->provider->chatModel(),
                    'local_embeddings' => true,
                ],
                'error' => $local['error'] ?? null,
            ];
        }

        return $this->localHealth();
    }

    public function delete(?string $externalDocumentId): bool
    {
        if ($this->usesInAppEngine()) {
            return true;
        }

        $endpoint = config('rag.delete_endpoint');

        if (blank($endpoint) || blank($externalDocumentId)) {
            return true;
        }

        try {
            $response = $this->request((int) config('rag.request_timeout'))
                ->post($endpoint, ['document_id' => $externalDocumentId]);

            return $response->successful();
        } catch (ConnectionException|RuntimeException $e) {
            Log::warning('RAG remote delete failed safely', [
                'document_id' => Str::limit((string) $externalDocumentId, 80, ''),
                'error' => $this->sanitizeError($e->getMessage()),
            ]);

            return false;
        }
    }

    public function stripThink(string $value): string
    {
        return trim((string) preg_replace('/<think\b[^>]*>.*?<\/think>/is', '', $value));
    }

    public function clampTopK(int $topK): int
    {
        $min = (int) config('rag.top_k.min', 1);
        $max = (int) config('rag.top_k.max', 10);

        return max($min, min($max, $topK));
    }

    public function normalizeSources(mixed $sources): array
    {
        if (! is_array($sources)) {
            return [];
        }

        return collect($sources)
            ->filter(fn ($source) => is_array($source))
            ->map(function (array $source): array {
                $locator = $source['locator'] ?? $source['page'] ?? $source['slide'] ?? null;
                $locatorType = isset($source['locator_type']) ? (string) $source['locator_type'] : null;
                $page = $source['page'] ?? ($locatorType === 'page' ? $locator : null);
                $slide = $source['slide'] ?? ($locatorType === 'slide' ? $locator : null);

                return [
                    'document' => Str::limit(strip_tags((string) ($source['document'] ?? $source['title'] ?? 'Document')), 255, ''),
                    'document_id' => filled($source['document_id'] ?? null) ? (string) $source['document_id'] : null,
                    'page' => is_numeric($page) ? (int) $page : null,
                    'slide' => is_numeric($slide) ? (int) $slide : null,
                    'locator_type' => $locatorType ?: ($source['slide'] ?? null ? 'slide' : 'page'),
                    'locator' => is_numeric($locator) ? (int) $locator : (filled($locator) ? (string) $locator : null),
                    'content' => filled($source['content'] ?? null) ? Str::limit(RagSourceFormatter::plain((string) $source['content']), 5000) : null,
                    'media' => $this->normalizeMedia($source['media'] ?? [], (string) ($source['document_id'] ?? '')),
                ];
            })
            ->values()
            ->all();
    }

    private function normalizeMedia(mixed $media, string $externalDocumentId): array
    {
        if (! is_array($media) || ! Str::isUuid($externalDocumentId)) {
            return [];
        }

        return collect($media)
            ->filter(fn ($item) => is_array($item) && filled($item['filename'] ?? null))
            ->map(function (array $item) use ($externalDocumentId): array {
                $filename = basename((string) $item['filename']);

                return [
                    'filename' => $filename,
                    'content_type' => (string) ($item['content_type'] ?? 'application/octet-stream'),
                    'alt' => Str::limit(strip_tags((string) ($item['alt'] ?? 'Slide image')), 255, ''),
                    'url' => route('rag.media.show', [
                        'externalDocumentId' => $externalDocumentId,
                        'filename' => $filename,
                    ]),
                ];
            })
            ->values()
            ->all();
    }

    private function mergeOutlineSources(string $question, array $sources, int $topK): array
    {
        $outlineSources = $this->outlineSources($question, min(3, max(1, $topK)));

        if ($outlineSources === []) {
            return $sources;
        }

        return collect($outlineSources)
            ->merge($sources)
            ->unique(fn (array $source): string => implode('|', [
                $source['document'] ?? '',
                $source['locator_type'] ?? '',
                $source['locator'] ?? '',
                $source['content'] ?? '',
            ]))
            ->take(max($topK, count($outlineSources)))
            ->values()
            ->all();
    }

    private function outlineSources(string $question, int $limit): array
    {
        if (! $this->isOutlineUsefulQuestion($question)) {
            return [];
        }

        $terms = $this->searchTerms($question);
        if ($terms === []) {
            return [];
        }

        return RagDocumentOutline::query()
            ->with('document:id,title,status')
            ->whereHas('document', fn ($query) => $query->where('status', RagDocument::STATUS_READY))
            ->get()
            ->map(function (RagDocumentOutline $outline) use ($terms, $question): array {
                $normalizedQuestion = Str::lower($question);
                $title = Str::lower((string) $outline->title);
                $haystack = Str::lower(implode(' ', [
                    $outline->document?->title,
                    $outline->title,
                    $outline->content,
                ]));

                $score = collect($terms)
                    ->sum(fn (string $term): int => (Str::contains($haystack, $term) ? 1 : 0) + (Str::contains($title, $term) ? 2 : 0));

                if (Str::contains($haystack, ['table of content', 'contents', 'module', 'modules', 'key topic'])) {
                    $score += 2;
                }

                if (Str::contains($normalizedQuestion, ['module', 'modules']) && in_array($outline->type, ['module', 'topic'], true)) {
                    $score += 4;
                }

                if (Str::contains($normalizedQuestion, ['module', 'modules']) && $outline->type === 'contents') {
                    $score -= 1;
                }

                return [
                    'score' => $score,
                    'document' => $outline->document?->title ?? 'Document',
                    'page' => $outline->locator_type === 'page' && is_numeric($outline->locator) ? (int) $outline->locator : null,
                    'slide' => $outline->locator_type === 'slide' && is_numeric($outline->locator) ? (int) $outline->locator : null,
                    'locator_type' => $outline->locator_type,
                    'locator' => $outline->locator,
                    'content' => trim(implode("\n", array_filter([
                        "Document outline: {$outline->title}",
                        $outline->content,
                    ]))),
                ];
            })
            ->filter(fn (array $source): bool => $source['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->map(fn (array $source): array => collect($source)->except('score')->all())
            ->values()
            ->all();
    }

    private function mergeStoredDocumentSources(string $question, array $sources, int $topK): array
    {
        $terms = $this->searchTerms($question);
        if ($terms === []) {
            return $sources;
        }

        $documentHints = collect($sources)
            ->pluck('document')
            ->filter()
            ->map(fn (string $title): string => Str::lower($title))
            ->values();

        $matches = RagDocument::query()
            ->where('status', RagDocument::STATUS_READY)
            ->get()
            ->map(function (RagDocument $document) use ($terms, $documentHints): array {
                $title = Str::lower($document->title);
                $titleScore = collect($terms)->sum(fn (string $term): int => Str::contains($title, $term) ? 3 : 0);

                if ($documentHints->contains(fn (string $hint): bool => $hint === $title || Str::contains($hint, $title) || Str::contains($title, $hint))) {
                    $titleScore += 8;
                }

                return ['document' => $document, 'score' => $titleScore];
            })
            ->filter(fn (array $match): bool => $match['score'] > 0)
            ->sortByDesc('score')
            ->take(2)
            ->values();

        if ($matches->isEmpty()) {
            return $sources;
        }

        $storedSources = [];

        foreach ($matches as $match) {
            /** @var RagDocument $document */
            $document = $match['document'];

            if (! $document->fileExists()) {
                continue;
            }

            try {
                $sections = $this->extractor->extract(Storage::disk($document->disk)->path($document->path), $document->extension);
            } catch (\Throwable $e) {
                Log::debug('RAG stored document fallback extraction failed', [
                    'document_id' => $document->id,
                    'error' => $this->sanitizeError($e->getMessage()),
                ]);

                continue;
            }

            $sectionSources = collect($sections)
                ->map(function (array $section) use ($document, $terms, $match): array {
                    $content = RagSourceFormatter::plain((string) ($section['content'] ?? ''));
                    $haystack = Str::lower(str_replace(['-', '‑'], '', $content));
                    $contentScore = collect($terms)->sum(fn (string $term): int => Str::contains($haystack, $term) ? 1 : 0);

                    return [
                        'score' => $match['score'] + $contentScore,
                        'document' => $document->title,
                        'page' => ($section['locator_type'] ?? null) === 'page' && is_numeric($section['locator'] ?? null) ? (int) $section['locator'] : null,
                        'slide' => ($section['locator_type'] ?? null) === 'slide' && is_numeric($section['locator'] ?? null) ? (int) $section['locator'] : null,
                        'locator_type' => $section['locator_type'] ?? 'document',
                        'locator' => $section['locator'] ?? null,
                        'content' => $content,
                    ];
                })
                ->filter(fn (array $source): bool => $source['score'] > $match['score'])
                ->sortByDesc('score')
                ->take(max(1, $topK))
                ->map(fn (array $source): array => collect($source)->except('score')->all())
                ->values()
                ->all();

            array_push($storedSources, ...$sectionSources);
        }

        if ($storedSources === []) {
            return $sources;
        }

        return collect($sources)
            ->merge($storedSources)
            ->unique(fn (array $source): string => implode('|', [
                $source['document'] ?? '',
                $source['locator_type'] ?? '',
                $source['locator'] ?? '',
                $source['content'] ?? '',
            ]))
            ->take(max($topK, min(5, count($sources) + count($storedSources))))
            ->values()
            ->all();
    }

    private function isOutlineUsefulQuestion(string $question): bool
    {
        return Str::contains(Str::lower($question), [
            'summarize',
            'summary',
            'overview',
            'tell me more',
            'module',
            'modules',
            'topic',
            'topics',
            'table of content',
            'contents',
            'list',
            'show me',
            'what are',
            'which',
            'guidance',
            'guideline',
            'manual',
        ]);
    }

    private function searchTerms(string $question): array
    {
        $stopWords = [
            'about', 'all', 'and', 'are', 'can', 'does', 'for', 'from', 'give', 'how',
            'list', 'manual', 'me', 'of', 'please', 'show',
            'more', 'summarize', 'summary', 'tell', 'the', 'this', 'to', 'what', 'which',
            'with', 'you',
        ];

        preg_match_all('/[a-z0-9][a-z0-9_-]{2,}/i', Str::lower($question), $matches);

        return collect($matches[0] ?? [])
            ->reject(fn (string $term): bool => in_array($term, $stopWords, true))
            ->map(fn (string $term): string => Str::endsWith($term, 's') && strlen($term) > 4 ? substr($term, 0, -1) : $term)
            ->unique()
            ->take(18)
            ->values()
            ->all();
    }

    private function usageMetadata(array $data): ?array
    {
        $metadata = [];

        if (is_array($data['token_usage'] ?? null)) {
            $metadata['token_usage'] = $data['token_usage'];
        }

        if (is_array($data['timings'] ?? null)) {
            $metadata['timings'] = $data['timings'];
        }

        return $metadata ?: null;
    }

    public function sanitizeError(string $message): string
    {
        if (str_contains($message, 'cURL error 28') || str_contains(strtolower($message), 'timed out')) {
            return 'AI service timed out while generating an answer. Try again, lower the Sources value, or confirm the local model is not still loading.';
        }

        $message = preg_replace('/https?:\/\/[^\s]+/i', '[url]', $message) ?? $message;
        $message = preg_replace('/Bearer\s+[A-Za-z0-9._\-]+/i', 'Bearer [redacted]', $message) ?? $message;

        return Str::limit(trim(strip_tags($message)), 500);
    }

    private function request(int $timeout): PendingRequest
    {
        $baseUrl = rtrim((string) config('rag.base_url', 'http://127.0.0.1:8001'), '/');

        if (! Str::startsWith($baseUrl, ['http://', 'https://'])) {
            throw new RuntimeException('RAG base URL must use http or https.');
        }

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->connectTimeout((int) config('rag.connect_timeout', 5))
            ->timeout($timeout)
            ->retry((int) config('rag.retry_count', 1), 250, throw: false);
    }

    private function usesInAppEngine(): bool
    {
        return config('rag.engine') === 'external';
    }

    private function usesHybridEngine(): bool
    {
        return config('rag.engine') === 'hybrid';
    }

    private function askHybrid(string $question, int $topK, int $started): array
    {
        $payload = [
            'question' => $question,
            'top_k' => $this->clampTopK($topK),
        ];

        $response = $this->request((int) config('rag.request_timeout'))
            ->post('/search', $payload);

        $data = $this->decodeResponse($response->throw()->json());
        $sources = $this->normalizeSources($data['sources'] ?? []);
        $sources = $this->mergeOutlineSources($question, $sources, $this->clampTopK($topK));
        $sources = $this->mergeStoredDocumentSources($question, $sources, $this->clampTopK($topK));

        if ($sources === []) {
            throw new RuntimeException('No relevant indexed documents are available for search.');
        }

        $visualMode = $this->visualRequestMode($question);

        if ($visualMode && $this->hasMediaSource($sources)) {
            $sources = $this->prioritizeMediaSources($sources);

            if ($visualMode === 'present') {
                return [
                    'answer' => $this->visualAnswer($sources),
                    'citations' => $sources,
                    'retrieved_sources' => $sources,
                    'model' => 'local-media',
                    'latency_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
                    'token_usage' => [
                        'token_usage' => null,
                        'timings' => $data['timings'] ?? null,
                    ],
                ];
            }
        }

        $answer = $this->provider->answer($question, $sources);

        return [
            'answer' => $this->stripThink($answer['answer']),
            'citations' => $sources,
            'retrieved_sources' => $sources,
            'model' => $answer['model'],
            'latency_ms' => (int) round((hrtime(true) - $started) / 1_000_000),
            'token_usage' => [
                'token_usage' => $answer['token_usage'] ?? null,
                'timings' => $data['timings'] ?? null,
            ],
        ];
    }

    private function visualRequestMode(string $question): ?string
    {
        $normalized = Str::lower($question);

        if (Str::contains($normalized, [
            'show me',
            'show the',
            'show',
            'display',
            'view',
            'open',
            'pull up',
            'bring up',
            'select',
            'pick',
            'find the image',
            'find image',
            'find picture',
            'find slide',
            'illustrate',
            'illustration',
            'visualize',
            'visualise',
        ])) {
            return 'present';
        }

        if (Str::contains($normalized, [
            'describe',
            'explain',
            'interpret',
            'walk me through',
            'what is in',
            'what does',
            'summarize the visual',
            'summarise the visual',
            'summarize the image',
            'summarise the image',
            'summarize the slide',
            'summarise the slide',
        ])) {
            return 'describe';
        }

        if (Str::contains($normalized, [
            'image',
            'images',
            'picture',
            'pictures',
            'visual',
            'diagram',
            'photo',
            'slide',
            'chart',
            'figure',
            'flowchart',
            'algorithm',
            'visual',
        ])) {
            return 'present';
        }

        return null;
    }

    private function hasMediaSource(array $sources): bool
    {
        return collect($sources)->contains(fn (array $source): bool => ! empty($source['media']));
    }

    private function prioritizeMediaSources(array $sources): array
    {
        return collect($sources)
            ->sortByDesc(fn (array $source): int => count($source['media'] ?? []))
            ->values()
            ->all();
    }

    private function visualAnswer(array $sources): string
    {
        $mediaSources = collect($sources)
            ->filter(fn (array $source): bool => ! empty($source['media']))
            ->values();

        $first = $mediaSources->first();
        $count = $mediaSources->sum(fn (array $source): int => count($source['media'] ?? []));
        $locator = filled($first['locator'] ?? null)
            ? Str::headline((string) ($first['locator_type'] ?? 'source')).' '.$first['locator']
            : 'the cited source';

        return implode("\n", [
            "I found the relevant visual for this in **{$first['document']}**, {$locator}.",
            '',
            $count === 1
                ? 'Open the source below to view the image.'
                : "Open the sources below to view the {$count} related images.",
        ]);
    }

    private function localHealth(): array
    {
        return Cache::remember('rag.health.'.config('rag.engine'), max(1, (int) config('rag.health_cache_seconds')), function () {
            try {
                $response = $this->request(5)->get('/health');

                return [
                    'ok' => $response->successful(),
                    'status' => $response->status(),
                    'body' => $response->successful() && is_array($response->json()) ? $response->json() : null,
                ];
            } catch (ConnectionException|RuntimeException $e) {
                return [
                    'ok' => false,
                    'status' => null,
                    'body' => null,
                    'error' => $this->sanitizeError($e->getMessage()),
                ];
            }
        });
    }

    private function decodeResponse(mixed $data): array
    {
        if (! is_array($data)) {
            throw new RuntimeException('RAG service returned malformed JSON.');
        }

        return $data;
    }

    private function sanitizeTitle(string $title): string
    {
        return Str::limit(trim(strip_tags($title)), 255, '');
    }
}
