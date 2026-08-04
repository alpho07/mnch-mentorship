<?php

return [
    'enabled' => env('RAG_ENABLED', false),

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

    'uploads' => [
        'disk' => env('RAG_UPLOAD_DISK', 'local'),
        'directory' => env('RAG_UPLOAD_DIRECTORY', 'private/knowledge-base'),
        'max_size_kb' => (int) env('RAG_MAX_UPLOAD_SIZE_KB', 51200),
        'allowed_extensions' => ['pdf', 'pptx'],
        'allowed_mime_types' => [
            'application/pdf',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ],
    ],

    'health_cache_seconds' => (int) env('RAG_HEALTH_CACHE_SECONDS', 30),

    'delete_endpoint' => env('RAG_DELETE_ENDPOINT'),
];
