<?php

namespace App\Services\Rag;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class RagClient
{
    public function ingest(string $absolutePath, string $title): array
    {
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
                'token_usage' => is_array($data['token_usage'] ?? null) ? $data['token_usage'] : null,
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
        return Cache::remember('rag.health', max(1, (int) config('rag.health_cache_seconds')), function () {
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

    public function delete(?string $externalDocumentId): bool
    {
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

                return [
                    'document' => Str::limit(strip_tags((string) ($source['document'] ?? $source['title'] ?? 'Document')), 255, ''),
                    'page' => isset($source['page']) ? (int) $source['page'] : null,
                    'slide' => isset($source['slide']) ? (int) $source['slide'] : null,
                    'locator_type' => isset($source['locator_type']) ? (string) $source['locator_type'] : ($source['slide'] ?? null ? 'slide' : 'page'),
                    'locator' => is_numeric($locator) ? (int) $locator : (filled($locator) ? (string) $locator : null),
                    'content' => filled($source['content'] ?? null) ? Str::limit(strip_tags((string) $source['content']), 1000) : null,
                ];
            })
            ->values()
            ->all();
    }

    public function sanitizeError(string $message): string
    {
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
