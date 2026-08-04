<?php

return [
    'enabled' => env('RAG_ENABLED', false),

    'engine' => env('RAG_ENGINE', 'local'),

    'base_url' => env('RAG_BASE_URL', 'http://127.0.0.1:8001'),

    'connect_timeout' => (int) env('RAG_CONNECT_TIMEOUT', 5),
    'request_timeout' => (int) env('RAG_REQUEST_TIMEOUT', 30),
    'ingest_timeout' => (int) env('RAG_INGEST_TIMEOUT', 180),
    'retry_count' => (int) env('RAG_RETRY_COUNT', 1),

    'top_k' => [
        'default' => (int) env('RAG_TOP_K_DEFAULT', 5),
        'min' => (int) env('RAG_TOP_K_MIN', 1),
        'max' => (int) env('RAG_TOP_K_MAX', 10),
    ],

    'search_pool_limit' => (int) env('RAG_SEARCH_POOL_LIMIT', 1000),

    'uploads' => [
        'disk' => env('RAG_UPLOAD_DISK', 'local'),
        'directory' => env('RAG_UPLOAD_DIRECTORY', 'private/knowledge-base'),
        'max_size_kb' => (int) env('RAG_MAX_UPLOAD_SIZE_KB', 51200),
        'allowed_extensions' => ['pdf', 'docx', 'pptx', 'xlsx', 'csv', 'txt', 'md', 'markdown', 'html', 'htm', 'json'],
        'allowed_mime_types' => [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/csv',
            'text/plain',
            'text/markdown',
            'text/html',
            'application/json',
        ],
    ],

    'chunking' => [
        'max_chars' => (int) env('RAG_CHUNK_MAX_CHARS', 3500),
        'overlap_chars' => (int) env('RAG_CHUNK_OVERLAP_CHARS', 400),
    ],

    'chat' => [
        'provider' => env('RAG_CHAT_PROVIDER', 'openai'),
        'base_url' => env('RAG_CHAT_BASE_URL'),
        'api_key' => env('RAG_CHAT_API_KEY')
            ?: (env('RAG_CHAT_PROVIDER', 'openai') === 'deepseek'
                ? env('DEEPSEEK_API_KEY')
                : env('OPENAI_API_KEY')),
        'model' => env('RAG_CHAT_MODEL'),
        'temperature' => (float) env('RAG_CHAT_TEMPERATURE', 0.2),
        'max_tokens' => (int) env('RAG_CHAT_MAX_TOKENS', 1200),
    ],

    'embeddings' => [
        'provider' => env('RAG_EMBEDDING_PROVIDER', 'openai'),
        'base_url' => env('RAG_EMBEDDING_BASE_URL', 'https://api.openai.com/v1'),
        'api_key' => env('RAG_EMBEDDING_API_KEY', env('OPENAI_API_KEY')),
        'model' => env('RAG_EMBEDDING_MODEL', 'text-embedding-3-small'),
        'batch_size' => (int) env('RAG_EMBEDDING_BATCH_SIZE', 32),
    ],

    'health_cache_seconds' => (int) env('RAG_HEALTH_CACHE_SECONDS', 30),

    'delete_endpoint' => env('RAG_DELETE_ENDPOINT'),
];
