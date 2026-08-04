<?php

namespace Tests\Unit;

use App\Services\Rag\RagClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RagClientTest extends TestCase
{
    public function test_ask_normalizes_citations_and_strips_think_blocks(): void
    {
        config()->set('rag.engine', 'local');
        config()->set('rag.base_url', 'http://127.0.0.1:8001');

        Http::fake([
            '127.0.0.1:8001/ask' => Http::response([
                'answer' => '<think>hidden</think>Use magnesium sulfate.',
                'sources' => [
                    ['document' => 'EmONC', 'page' => 17, 'content' => '<b>source</b>'],
                ],
                'model' => 'local',
            ]),
        ]);

        $response = app(RagClient::class)->ask('What is used for eclampsia?', 50);

        $this->assertSame('Use magnesium sulfate.', $response['answer']);
        $this->assertSame(17, $response['citations'][0]['page']);
        $this->assertSame('source', $response['citations'][0]['content']);
        $this->assertSame('local', $response['model']);
    }

    public function test_ask_rejects_malformed_json(): void
    {
        config()->set('rag.engine', 'local');
        config()->set('rag.base_url', 'http://127.0.0.1:8001');

        Http::fake([
            '127.0.0.1:8001/ask' => Http::response('not-json', 200),
        ]);

        $this->expectException(\RuntimeException::class);

        app(RagClient::class)->ask('Question?', 5);
    }

    public function test_hybrid_ask_uses_local_search_and_external_chat(): void
    {
        config()->set('rag.engine', 'hybrid');
        config()->set('rag.base_url', 'http://127.0.0.1:8001');
        config()->set('rag.chat.provider', 'deepseek');
        config()->set('rag.chat.base_url', 'https://api.deepseek.com');
        config()->set('rag.chat.api_key', 'test-key');
        config()->set('rag.chat.model', 'deepseek-chat');

        Http::fake([
            '127.0.0.1:8001/search' => Http::response([
                'sources' => [
                    [
                        'document' => 'Module 1',
                        'locator_type' => 'slide',
                        'locator' => 3,
                        'content' => 'Triage is sorting and prioritizing patients.',
                    ],
                ],
                'timings' => ['retrieval_ms' => 10],
            ]),
            'api.deepseek.com/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Triage sorts and prioritizes patients [1].']],
                ],
                'model' => 'deepseek-chat',
            ]),
        ]);

        $response = app(RagClient::class)->ask('What is triage?', 5);

        $this->assertSame('Triage sorts and prioritizes patients [1].', $response['answer']);
        $this->assertSame('deepseek-chat', $response['model']);
        $this->assertSame('Module 1', $response['citations'][0]['document']);
    }

    public function test_hybrid_visual_request_returns_media_without_external_chat(): void
    {
        config()->set('rag.engine', 'hybrid');
        config()->set('rag.base_url', 'http://127.0.0.1:8001');

        Http::fake([
            '127.0.0.1:8001/search' => Http::response([
                'sources' => [
                    [
                        'document_id' => 'daedc522-7aa7-46f6-b0d3-8b66e89e4d18',
                        'document' => 'Module 3. Essential Newborn Care',
                        'locator_type' => 'slide',
                        'locator' => 8,
                        'content' => 'Assessment of The Newborn',
                        'media' => [
                            ['filename' => 'slide-8-image-4.png', 'content_type' => 'image/png'],
                        ],
                    ],
                ],
                'timings' => ['retrieval_ms' => 10],
            ]),
        ]);

        $response = app(RagClient::class)->ask('show me assessment of newborn', 8);

        $this->assertSame('local-media', $response['model']);
        $this->assertStringContainsString('Module 3. Essential Newborn Care', $response['answer']);
        $this->assertCount(1, $response['citations'][0]['media']);
        Http::assertSentCount(1);
    }

    public function test_hybrid_select_request_returns_media_without_external_chat(): void
    {
        config()->set('rag.engine', 'hybrid');
        config()->set('rag.base_url', 'http://127.0.0.1:8001');

        Http::fake([
            '127.0.0.1:8001/search' => Http::response([
                'sources' => [
                    [
                        'document_id' => 'daedc522-7aa7-46f6-b0d3-8b66e89e4d18',
                        'document' => 'Module 3. Essential Newborn Care',
                        'locator_type' => 'slide',
                        'locator' => 8,
                        'content' => 'Assessment of The Newborn',
                        'media' => [
                            ['filename' => 'slide-8-image-4.png', 'content_type' => 'image/png'],
                        ],
                    ],
                ],
            ]),
        ]);

        $response = app(RagClient::class)->ask('select assessment of newborn', 8);

        $this->assertSame('local-media', $response['model']);
        $this->assertCount(1, $response['citations'][0]['media']);
        Http::assertSentCount(1);
    }

    public function test_hybrid_describe_request_prioritizes_media_and_uses_external_chat(): void
    {
        config()->set('rag.engine', 'hybrid');
        config()->set('rag.base_url', 'http://127.0.0.1:8001');
        config()->set('rag.chat.provider', 'deepseek');
        config()->set('rag.chat.base_url', 'https://api.deepseek.com');
        config()->set('rag.chat.api_key', 'test-key');
        config()->set('rag.chat.model', 'deepseek-chat');

        Http::fake([
            '127.0.0.1:8001/search' => Http::response([
                'sources' => [
                    [
                        'document' => 'Text-only page',
                        'locator_type' => 'page',
                        'locator' => 2,
                        'content' => 'Assessment notes.',
                    ],
                    [
                        'document_id' => 'daedc522-7aa7-46f6-b0d3-8b66e89e4d18',
                        'document' => 'Module 3. Essential Newborn Care',
                        'locator_type' => 'slide',
                        'locator' => 8,
                        'content' => 'Image text: Assessment of The Newborn',
                        'media' => [
                            ['filename' => 'slide-8-image-4.png', 'content_type' => 'image/png'],
                        ],
                    ],
                ],
            ]),
            'api.deepseek.com/chat/completions' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'The visual summarizes newborn assessment steps [1].']],
                ],
                'model' => 'deepseek-chat',
            ]),
        ]);

        $response = app(RagClient::class)->ask('describe assessment of newborn', 8);

        $this->assertSame('deepseek-chat', $response['model']);
        $this->assertSame('Module 3. Essential Newborn Care', $response['citations'][0]['document']);
        $this->assertCount(1, $response['citations'][0]['media']);
        $this->assertStringContainsString('newborn assessment', $response['answer']);
        Http::assertSentCount(2);
    }

    public function test_top_k_is_clamped(): void
    {
        config()->set('rag.top_k.min', 1);
        config()->set('rag.top_k.max', 10);

        $client = app(RagClient::class);

        $this->assertSame(1, $client->clampTopK(0));
        $this->assertSame(10, $client->clampTopK(99));
    }
}
