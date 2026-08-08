# RAG Continuity And Implementation Specification

Last updated: 2026-08-05  
Application root: `/var/www/html/mnch-master`

This document records the current RAG implementation, the operational configuration, the changes made during the recent debugging sessions, and the next safe work areas. It is intended as a continuity handoff for anyone resuming this work.

Do not paste live secret values into this document. Environment variable names are documented, but API keys are intentionally omitted.

## 1. Current Goal

The knowledge-base chat should:

- accept private document uploads from the Filament admin panel;
- index PDF/PPTX through the local RAG service;
- support DOCX and other text-like formats through Laravel fallback/in-app extraction;
- retrieve useful slide/page/document excerpts quickly;
- use DeepSeek as the answer/query-planning model in hybrid mode;
- use local Ollama models for embedding and local retrieval;
- stream the answer into the chat UI;
- show readable answer/source formatting;
- avoid repeated weak answers such as "the excerpt only says Module 4: Oxygen therapy";
- provide enough trace metadata to understand why a response picked certain sources.

## 2. High-Level Architecture

The active production shape is hybrid RAG:

```text
Filament Chat UI
    |
    | POST /admin/rag/chat/stream
    v
RagChatStreamController
    |
    v
RagClient::askStream()
    |
    +--> local RAG FastAPI service, http://127.0.0.1:8001/search
    |       |
    |       +--> Chroma persistent collection
    |       +--> Ollama embedding model: bge-m3
    |
    +--> Laravel fallback enrichment
    |       |
    |       +--> curriculum fallback
    |       +--> outline fallback
    |       +--> stored DOCX/document fallback
    |
    +--> ExternalAiProvider::answerStream()
            |
            +--> DeepSeek OpenAI-compatible /chat/completions
```

Ingestion path:

```text
Filament RAG Document Upload
    |
    v
RagDocumentResource / CreateRagDocument
    |
    v
ProcessRagDocument queued job
    |
    +--> PDF/PPTX in hybrid/local mode:
    |       RagClient::ingest() -> local FastAPI /ingest
    |
    +--> DOCX/XLSX/TXT/HTML/etc:
            InAppRagEngine::ingest() where applicable,
            or Laravel stored-document fallback at query time
```

## 3. Important Files

Laravel configuration:

- `config/rag.php`
- `.env`
- `.env.example`
- `docs/rag-filament.md`
- `docs/rag-continuity-spec.md`

Chat UI and routes:

- `routes/web.php`
- `resources/views/filament/pages/rag-chat.blade.php`
- `app/Filament/Pages/RagChat.php`
- `app/Http/Controllers/RagChatStreamController.php`

Document management:

- `app/Filament/Resources/RagDocumentResource.php`
- `app/Filament/Resources/RagDocumentResource/Pages/CreateRagDocument.php`
- `app/Jobs/ProcessRagDocument.php`
- `app/Http/Controllers/RagDocumentDownloadController.php`
- `app/Http/Controllers/RagMediaController.php`

RAG services:

- `app/Services/Rag/RagClient.php`
- `app/Services/Rag/ExternalAiProvider.php`
- `app/Services/Rag/InAppRagEngine.php`
- `app/Services/Rag/DocumentTextExtractor.php`
- `app/Support/RagSourceFormatter.php`

Models:

- `app/Models/RagDocument.php`
- `app/Models/RagChunk.php`
- `app/Models/RagDocumentOutline.php`
- `app/Models/RagTermBridge.php`
- `app/Models/RagConversation.php`
- `app/Models/RagMessage.php`

Tests:

- `tests/Unit/RagClientTest.php`
- `tests/Unit/RagSourceFormatterTest.php`
- `tests/Feature/RagAccessTest.php`

Local RAG service outside Laravel:

- `/opt/local-rag/app/main.py`
- `/opt/local-rag/.env`
- `/etc/systemd/system/local-rag.service`
- `/etc/systemd/system/ollama.service.d/local-rag.conf`

## 4. Laravel RAG Configuration

Main config lives in `config/rag.php`.

Current important settings:

```php
'enabled' => env('RAG_ENABLED', false),
'engine' => env('RAG_ENGINE', 'local'),
'base_url' => env('RAG_BASE_URL', 'http://127.0.0.1:8001'),
'connect_timeout' => env('RAG_CONNECT_TIMEOUT', 5),
'request_timeout' => env('RAG_REQUEST_TIMEOUT', 30),
'ingest_timeout' => env('RAG_INGEST_TIMEOUT', 180),
'search_timeout' => env('RAG_SEARCH_TIMEOUT', 3),
'search_max_failures' => env('RAG_SEARCH_MAX_FAILURES', 2),
```

Top-k:

```php
'top_k' => [
    'default' => env('RAG_TOP_K_DEFAULT', 5),
    'min' => env('RAG_TOP_K_MIN', 1),
    'max' => env('RAG_TOP_K_MAX', 10),
],
```

Query planner:

```php
'query_planner' => [
    'enabled' => env('RAG_QUERY_PLANNER_ENABLED', true),
    'timeout' => env('RAG_QUERY_PLANNER_TIMEOUT', 6),
    'max_queries' => env('RAG_QUERY_PLANNER_MAX_QUERIES', 6),
],
```

Upload support:

```php
'allowed_extensions' => [
    'pdf', 'docx', 'pptx', 'xlsx', 'csv', 'txt',
    'md', 'markdown', 'html', 'htm', 'json',
],
```

Chat provider:

```php
'chat' => [
    'provider' => env('RAG_CHAT_PROVIDER', 'openai'),
    'base_url' => env('RAG_CHAT_BASE_URL'),
    'model' => env('RAG_CHAT_MODEL'),
    'timeout' => env('RAG_CHAT_TIMEOUT', 15),
    'max_tokens' => env('RAG_CHAT_MAX_TOKENS', 700),
    'context_per_source_chars' => env('RAG_CHAT_CONTEXT_PER_SOURCE_CHARS', 900),
    'context_total_chars' => env('RAG_CHAT_CONTEXT_TOTAL_CHARS', 4500),
],
```

Embedding provider:

```php
'embeddings' => [
    'provider' => env('RAG_EMBEDDING_PROVIDER', 'openai'),
    'base_url' => env('RAG_EMBEDDING_BASE_URL', 'https://api.openai.com/v1'),
    'model' => env('RAG_EMBEDDING_MODEL', 'text-embedding-3-small'),
],
```

In `hybrid` mode, Laravel does not use `RAG_EMBEDDING_*` for the main local vector search. Local embeddings happen in the FastAPI/Ollama service. The `RAG_EMBEDDING_*` settings are still used by `external` mode and by the in-app engine if configured.

## 5. Runtime Environment Variables

The live app is configured through `.env`. Important variables are:

```env
RAG_ENABLED=true
RAG_ENGINE=hybrid
RAG_BASE_URL=http://127.0.0.1:8001
RAG_CONNECT_TIMEOUT=10
RAG_REQUEST_TIMEOUT=120
RAG_INGEST_TIMEOUT=300
RAG_TOP_K_DEFAULT=1
RAG_TOP_K_MAX=8
RAG_MAX_UPLOAD_SIZE_KB=102400

RAG_CHAT_PROVIDER=deepseek
RAG_CHAT_BASE_URL=https://api.deepseek.com
RAG_CHAT_MODEL=deepseek-v4-flash
RAG_CHAT_API_KEY=
DEEPSEEK_API_KEY=<configured in environment>
RAG_CHAT_MAX_TOKENS=650

RAG_EMBEDDING_PROVIDER=openai
RAG_EMBEDDING_BASE_URL=https://api.openai.com/v1
RAG_EMBEDDING_MODEL=text-embedding-3-small
RAG_EMBEDDING_API_KEY=
```

Important behavior:

- If `RAG_CHAT_API_KEY` is empty and provider is `deepseek`, `ExternalAiProvider` falls back to `DEEPSEEK_API_KEY`.
- DeepSeek is accessed through OpenAI-compatible `/chat/completions`.
- `RAG_TOP_K_DEFAULT=1` keeps the initial retrieval light. The adaptive profile router can raise this for broad questions.
- `RAG_REQUEST_TIMEOUT=120` allows full chat generation to take longer, but streaming makes the UI responsive before completion.

## 6. Local RAG FastAPI Service

The local service is managed by systemd:

```ini
[Unit]
Description=Local DeepSeek RAG API
After=network-online.target ollama.service
Wants=network-online.target
Requires=ollama.service

[Service]
User=mentorship
Group=mentorship
WorkingDirectory=/opt/local-rag/app
EnvironmentFile=/opt/local-rag/.env
ExecStart=/opt/local-rag/venv/bin/uvicorn main:app --host 127.0.0.1 --port 8001 --workers 1
Restart=on-failure
RestartSec=5
TimeoutStartSec=300
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=/opt/local-rag
```

Service endpoints used by Laravel:

- `GET /health`
- `GET /documents`
- `POST /ingest`
- `POST /search`
- `POST /ask` for local-only mode
- media file serving for slide images through `RagMediaController`

Local service environment:

```env
RAG_DATA_DIR=/opt/local-rag/data
OLLAMA_URL=http://127.0.0.1:11434
CHAT_MODEL=qwen2.5:7b-instruct
EMBED_MODEL=bge-m3
COLLECTION_NAME=local_documents
MAX_UPLOAD_MB=100
REQUEST_TIMEOUT=300
DEFAULT_TOP_K=1
MAX_TOP_K=8
CHAT_TEMPERATURE=0.1
CHAT_NUM_CTX=3072
CHAT_NUM_PREDICT=360
CHAT_KEEP_ALIVE=10m
EMBED_KEEP_ALIVE=10m

RETRIEVAL_VARIANTS=1
QUERY_EMBED_CACHE_SIZE=512
CANDIDATE_MULTIPLIER=2
MAX_CANDIDATES=12
LEXICAL_MIN_SCORE=4.0
CORPUS_CACHE_SECONDS=120
```

Local data paths:

- documents: `/opt/local-rag/data/documents`
- Chroma DB: `/opt/local-rag/data/chroma`
- collection: `local_documents`

## 7. Ollama Configuration

Ollama is bound to local loopback:

```ini
[Service]
Environment="OLLAMA_HOST=127.0.0.1:11434"
Environment="OLLAMA_KEEP_ALIVE=10m"
Environment="OLLAMA_NUM_PARALLEL=2"
Environment="OLLAMA_MAX_LOADED_MODELS=2"
```

Models:

- chat model for local service: `qwen2.5:7b-instruct`
- embedding model for local service: `bge-m3`

Why this matters:

- `bge-m3` produces embeddings used for Chroma vector search.
- `qwen2.5:7b-instruct` can answer in local mode, but the active Laravel hybrid path uses DeepSeek for final answers.
- Keep-alive settings reduce cold starts.
- Parallel/max-loaded settings reduce model reload thrash.

## 8. Local RAG Retrieval Optimizations Added

The local FastAPI service was changed to reduce search latency and avoid repeated embedding work.

Added behavior:

- `OrderedDict` query embedding cache.
- `QUERY_EMBED_CACHE_SIZE=512`.
- `RETRIEVAL_VARIANTS=1` to avoid multiplying embedding calls.
- Corpus cache around Chroma `collection.get()`.
- `CORPUS_CACHE_SECONDS=120`.
- Lexical-first retrieval before vector embedding retrieval.
- `LEXICAL_MIN_SCORE=4.0`.
- `CANDIDATE_MULTIPLIER=2`.
- `MAX_CANDIDATES=12`.
- `/health` now reports retrieval/cache information.

Observed benchmark after optimization:

- `/search oxygen therapy top_k=3`: approximately 0.007s, retrieval about 5 ms.
- `/search oxygen therapy pulse oximetry top_k=3`: approximately 0.112s, retrieval about 101 ms.
- `/search neonatal jaundice top_k=3`: approximately 0.009s.
- `/search hypothermia top_k=3`: approximately 0.005s.

The browser chat can still take 30-45 seconds if DeepSeek generation is slow. Retrieval being fast does not mean the full answer is fast. Streaming was added so users see progress and tokens as they arrive.

## 9. Local RAG File Type Limitation

The FastAPI service currently ingests:

- PDF
- PPTX

Its `extract_units()` returns an empty list for other extensions. That means DOCX is not indexed into Chroma by the local service.

Laravel supports extraction for:

- PDF
- DOCX
- PPTX
- XLSX
- CSV
- TXT
- Markdown
- HTML
- JSON

This mismatch caused the EMonC issue:

- The only EMonC file was a DOCX in Laravel storage.
- The local service `/documents` did not list it.
- Vector search therefore could not return it.
- Laravel had to be patched to use stored-document fallback when a title/acronym query such as `emonc` is asked.

## 10. DeepSeek Integration

`ExternalAiProvider` manages DeepSeek/OpenAI-compatible calls.

Roles:

- query planning through `searchQueries()`;
- final answer generation through `answer()`;
- streamed final answer generation through `answerStream()`;
- fallback extractive response if the model returns an empty answer.

Query planner:

- Uses the configured chat provider.
- Asks for JSON with a `queries` array.
- Adds synonyms, parent topics, child topics, abbreviations, and clinical equivalents.
- Uses curriculum/module hints from `database/seeders/data/mentorship_curriculum_2025_10_13.php`.
- Has a short timeout through `RAG_QUERY_PLANNER_TIMEOUT`.
- Falls back to deterministic local queries if DeepSeek fails.

Answer prompt:

- Treats the assistant as a document-grounded clinical mentorship assistant.
- Requires citations like `[1]`, `[2]`.
- Tells the model to synthesize across excerpts, not just the first result.
- Tells the model not to invent facts beyond the excerpts.
- Tells the model to avoid cold "not found in document" phrasing.

Streaming:

- Uses DeepSeek/OpenAI-style SSE chunks from `/chat/completions`.
- Reads `data:` lines.
- Emits each `delta.content` to the Laravel streaming controller.
- Captures model and usage where available.
- Falls back to non-streaming `answer()` if the stream returns no text.

## 11. Chat UI And Streaming

Route:

```php
POST /admin/rag/chat/stream
```

Controller:

```php
app/Http/Controllers/RagChatStreamController.php
```

UI:

```php
resources/views/filament/pages/rag-chat.blade.php
```

Streaming flow:

1. Browser posts question and optional `conversation_id`.
2. Controller validates permission through `RagAccess::canUseChat()`.
3. Controller rate-limits each user with key `rag-ask:{user_id}`.
4. User message is saved in `rag_messages`.
5. Controller emits SSE event `start`.
6. Controller calls `RagClient::askStream()`.
7. Each model token is sent as SSE event `delta`.
8. Final assistant message is saved with answer, citations, retrieved sources, model, latency, and token usage metadata.
9. Controller emits SSE event `done`.
10. Errors are saved and emitted as SSE event `error`.

UI loader changes:

- While waiting, the answer area animates.
- Loader phrases rotate:
  - `Thinking...`
  - `Thinking some more...`
  - `Almost done...`
- Streaming answer text is formatted while it arrives.

Formatting improvements:

- `rag-chat-markdown` parses basic markdown while streaming.
- Citations, paragraphs, bullets, numbered lists, and bold text are rendered more cleanly.
- Source excerpts go through `RagSourceFormatter`.

## 12. Source Formatting Fix

Problem:

Slide text often arrived as dense extracted plain text, for example:

```text
Role of Filtered Sunlight Phototherapy Filtered sunlight Filtered sunlight is noninferior...
Speaker notes: By avoiding endotracheal intubation...
```

This was hard to read in the UI.

Fix:

- `app/Support/RagSourceFormatter.php` splits dense excerpts around known structure:
  - headings;
  - `Speaker notes:`;
  - references;
  - risk statements;
  - numbered or bullet-like content;
  - vertical bars/table separators;
  - malformed dense slide text.

Test:

- `tests/Unit/RagSourceFormatterTest.php`
- Test uses a dense filtered sunlight phototherapy excerpt.

## 13. Adaptive Multi-Stage-Lite Retrieval

The root guide `codex_multistage_rag_implementation_guide.md` described a full multi-stage retrieval system. A safe first slice has been implemented inside `RagClient`.

Implemented now:

- retrieval profiles:
  - `fast`
  - `standard`
  - `deep`
- profile-specific `top_k`;
- profile-specific query limits;
- profile-specific fallback query limits;
- retrieval trace metadata saved in `token_usage.retrieval_trace`.

Profile routing:

- `fast`: simple acronym/definition/page/who/when questions with few search terms.
- `standard`: ordinary explanatory questions.
- `deep`: broad questions such as:
  - `tell me more`
  - `more about`
  - `summarize`
  - `overview`
  - `care of`
  - `management of`
  - `module`
  - `manual`
  - `guideline`

Trace metadata includes:

```json
{
  "profile": "standard",
  "top_k": 5,
  "query_limit": 5,
  "fallback_limit": 3,
  "allow_second_pass": false,
  "primary_queries": [],
  "fallback_queries": [],
  "search_count": 1,
  "source_count": 5,
  "selected_documents": [],
  "selected_locations": [],
  "retrieval_ms": 123
}
```

Where stored:

- `rag_messages.token_usage.retrieval_trace`

Purpose:

- Explain why a question used a fast/standard/deep path.
- Show what queries were sent to local search.
- Show which documents and locations were selected.
- Separate retrieval time from full answer latency.

## 13.1 Dynamic Term Bridges

Deterministic bridge queries are now managed through:

- model: `app/Models/RagTermBridge.php`
- migration: `database/migrations/2026_08_04_000006_create_rag_term_bridges_table.php`
- Filament resource: `app/Filament/Resources/RagTermBridgeResource.php`
- cache key: `rag:term-bridges:v1`

This replaces one-off code intervention for standard wording twists. Each bridge has:

- trigger term;
- optional synonyms;
- expanded search queries;
- category;
- priority;
- enabled/disabled flag;
- notes.

Current configured/default bridges:

- `sepsis`
- `hypothermia`
- `oxygen`
- `resuscitation`

Runtime behavior:

1. `RagClient::searchTerms()` extracts terms from the question.
2. `RagClient::bridgeSearchQueries()` matches the terms and full question against enabled bridges.
3. Matching bridge queries are merged into `primarySearchQueries()`.
4. Standard questions can stay deterministic and skip DeepSeek query planning.
5. Saving or deleting a bridge clears the cache automatically.

If the table is unavailable, `RagClient` falls back to built-in default bridge definitions so existing behavior does not break before migration.

## 14. Curriculum Fallback

The curriculum fallback uses:

```php
database/seeders/data/mentorship_curriculum_2025_10_13.php
```

Purpose:

- Handle cases where indexed slide/vector content is too thin or title-only.
- Provide module/session context for known curriculum topics.
- Avoid weak oxygen/hypothermia responses.

Important behavior:

- Curriculum fallback should not override good slide sources.
- It is used when local/vector sources are empty, weak, or title-only.
- `curriculumAnswer()` contains narrow local answers for oxygen therapy and hypothermia when retrieved content is otherwise insufficient.
- `curriculumScheduleAnswer()` handles module duration/session-breakdown questions directly from the curriculum source, bypassing final DeepSeek answer generation when it can build a grounded deterministic answer.
- Schedule questions trigger curriculum enrichment even when vector search returns a long but unrelated source.

Known fixed cases:

- `oxygen therapy, what is it?`
- `how about hypothermia`
- `resuscitation module should take how long and what is the breakdown of the sessions`

Current deterministic schedule answer shape:

```text
Module 6: Newborn Resuscitation takes 135 minutes total (2 hours 15 minutes) [1].

Session breakdown:
1. Resuscitation video following algorithm - 15 minutes (Video)
2. Skills teaching and practicum - 60 minutes (Demonstration/Practicum)
3. Case scenarios on neonatal resuscitation - 60 minutes (Practicum)
```

## 15. Stored Document Fallback

Problem fixed:

Question:

```text
emonc
```

Before:

- Local vector service returned no EMonC because the only EMonC file was DOCX.
- Stored fallback found the document by title, but discarded the body because `emonc` did not have to appear in each body chunk.

Current behavior:

- Short title/acronym questions trigger stored document lookup.
- DOCX content is extracted and chunked through Laravel `DocumentTextExtractor`.
- Strong title matches are allowed even if the body uses expanded terms such as `Emergency Obstetric & Newborn Care`.
- Stored document chunks are prefixed with:

```text
Document: EmONC Simulation and Drills
```

- Stored document matches receive a ranking boost so they appear before unrelated vector hits.

Relevant code:

- `RagClient::shouldTryStoredDocumentLookup()`
- `RagClient::mergeStoredDocumentSources()`
- `RagClient::sourceCompletenessScore()`

Verified behavior:

- `EmONC Simulation and Drills` is returned first in direct stored retrieval.
- End-to-end `ask("emonc")` returned an answer explaining EmONC using the DOCX.

## 16. Visual / Media Handling

The local FastAPI service extracts slide images from PPTX files and stores them under local document media folders.

Laravel media route:

- `app/Http/Controllers/RagMediaController.php`

Visual request detection:

- `show`
- `display`
- `view`
- `open`
- `select`
- `pick`
- `image`
- `picture`
- `visual`
- `diagram`
- `figure`
- `chart`
- `slide`

Behavior:

- If the user explicitly asks to show/select/present a visual and a media source is available, `RagClient` can return a `local-media` answer without calling DeepSeek.
- If the user asks to describe a visual, media sources are prioritized and DeepSeek receives the relevant excerpts.

## 17. Document Inventory And Module Shortcuts

The streaming controller has two fast local response paths:

1. Document inventory questions:
   - `what documents`
   - `which documents`
   - `list documents`
   - `uploaded documents`
   - `available documents`
   - `indexed documents`

2. Newborn mentorship module list questions:
   - require `newborn` and `mentorship`;
   - require module/list-like wording.

These return immediately with local data and do not call DeepSeek.

## 18. Database Tables

RAG migrations:

- `2026_08_04_000001_create_rag_documents_table.php`
- `2026_08_04_000002_create_rag_conversations_table.php`
- `2026_08_04_000003_create_rag_messages_table.php`
- `2026_08_04_000004_create_rag_chunks_table.php`
- `2026_08_04_000005_create_rag_document_outlines_table.php`

Important tables:

### `rag_documents`

Stores uploaded file metadata:

- title
- original/stored name
- disk/path
- extension/mime
- sha256
- status
- external document id
- page/slide count
- chunk count
- processing timestamps
- metadata
- uploader

### `rag_conversations`

Stores user conversations:

- user id
- title
- last message time
- metadata

### `rag_messages`

Stores chat turns:

- conversation id
- role
- content
- citations
- retrieved sources
- model
- latency
- token usage
- error message

The retrieval trace is currently nested under `token_usage`.

### `rag_chunks`

Used by the Laravel in-app/external engine:

- document id
- chunk index
- locator type
- locator
- content
- content hash
- embedding
- embedding model

### `rag_document_outlines`

Stores extracted outlines/headings when available:

- document id
- sort order
- level
- type
- title
- locator type
- locator
- content
- metadata

## 19. Document Processing Job

`ProcessRagDocument` handles indexing.

Important behavior:

- Marks document `processing`.
- Skips duplicate checksums if another document is processing/ready.
- Uses local service or in-app engine depending on `RAG_ENGINE` and extension.
- Stores external document id, page/slide count, chunk count, and ingest metadata.
- Stores outlines if returned.
- Marks document `ready` or `failed`.
- Has retries/backoff:
  - tries: 3
  - timeout: 900 seconds
  - backoff: 30, 120, 300 seconds
  - unique for: 3600 seconds

Current decision:

```php
private function shouldUseInAppIngestion(RagDocument $document): bool
{
    if (config('rag.engine') === 'external') {
        return true;
    }

    return ! in_array(strtolower((string) $document->extension), ['pdf', 'pptx'], true);
}
```

Important consequence:

- In hybrid mode, PDF/PPTX go to the local FastAPI service.
- Non-PDF/PPTX use Laravel in-app extraction/indexing where configured.
- Stored-document fallback still protects cases where the local vector service cannot see a DOCX.

## 20. Answer Latency Explanation

Two different timings matter:

1. Retrieval time
2. Full answer generation time

After local RAG optimization, retrieval may return in milliseconds. The full chat answer can still take 30-45 seconds because DeepSeek generation is separate and may be slow.

What was done:

- Local search timeout is low: `RAG_SEARCH_TIMEOUT=3`.
- Local retrieval cache was added.
- Streaming answer output was added.
- UI loader was added so the user sees progress before final text.
- Retrieval trace metadata was added so we can distinguish retrieval slowness from model-generation slowness.

## 21. Known Fixed Problems

### Repeated oxygen title-only answer

Symptom:

```text
Based on the excerpt you shared, I can see that oxygen therapy is the topic of Module 4...
```

Fixes:

- Hybrid fallback searches.
- Query planner for synonyms/related clinical terms.
- Curriculum fallback for oxygen/hypothermia.
- Slide sources now stay preferred over curriculum when useful.
- Module-title-only sources are detected and deprioritized.

### Follow-up `how about hypothermia`

Fix:

- Query planner and curriculum lookup include planned queries.
- Hypothermia maps to neonatal thermoregulation curriculum when vector search is empty.

### Resuscitation module timing inaccurate

Symptom:

```text
resuscitation module should take how long and what is the breakdown of the sessions
```

The response could be out of scope or inaccurate when vector retrieval returned weak/unrelated content and the final chat model tried to infer the answer.

Fix:

- Added a `resuscitation` bridge with queries for `Module 6 Newborn Resuscitation`, session duration, and the known session phrases.
- Added curriculum schedule detection for questions containing module/session intent plus time/duration/breakdown wording.
- Added deterministic local answer generation from `database/seeders/data/mentorship_curriculum_2025_10_13.php`.
- This path returns model `local-curriculum` and does not call the final DeepSeek answer endpoint when the curriculum schedule answer is available.

### EMonC no-result

Fix:

- Stored document fallback for title/acronym questions.
- DOCX extraction/chunking at query time.
- Stored document ranking boost.

### Chat appears frozen

Fix:

- Streaming endpoint.
- UI loader text.
- SSE delta rendering.

### Sources are dense plain text

Fix:

- `RagSourceFormatter` improved dense text splitting.
- Streaming answer markdown renderer improved.

## 22. Tests And Verification

Focused tests run after the recent work:

```bash
php artisan test tests/Unit/RagSourceFormatterTest.php tests/Unit/RagClientTest.php
```

Last known result:

```text
15 passed
```

Formatter/lint check:

```bash
vendor/bin/pint --dirty --test
```

Last known result:

```text
PASS
```

Syntax check example:

```bash
php -l app/Services/Rag/RagClient.php
```

Local service health:

```bash
curl -sS --max-time 3 http://127.0.0.1:8001/health
```

Expected health data includes:

- `status: ok`
- `ollama: true`
- `chat_model: qwen2.5:7b-instruct`
- `embedding_model: bge-m3`
- `retrieval_variants: 1`
- `query_embed_cache_size: 512`
- `lexical_min_score: 4.0`
- `corpus_cache_seconds: 120`
- `chunks: <current indexed chunk count>`

## 23. Service Restart Commands

Common restart commands used after code/config changes:

```bash
php artisan config:clear
php artisan view:clear
systemctl restart php8.3-fpm
systemctl restart apache2
systemctl restart local-rag
systemctl restart ollama
```

Use restarts carefully. For Laravel-only changes, usually:

```bash
php artisan config:clear
php artisan view:clear
systemctl restart php8.3-fpm
systemctl restart apache2
```

For local FastAPI/Ollama changes:

```bash
systemctl restart local-rag
systemctl restart ollama
```

## 24. Operational Troubleshooting

### Check whether local RAG is running

```bash
ss -ltnp
curl -sS --max-time 3 http://127.0.0.1:8001/health
```

Port expectations:

- local RAG FastAPI: `127.0.0.1:8001`
- Ollama: `127.0.0.1:11434`

### Check indexed documents in local service

```bash
curl -sS --max-time 5 http://127.0.0.1:8001/documents
```

If a DOCX does not appear there, that is expected unless DOCX support is added to the local service. Laravel fallback may still find it.

### Check Laravel document records

Use Tinker with care:

```bash
php artisan tinker
```

Example query:

```php
App\Models\RagDocument::query()
    ->select('id', 'title', 'status', 'extension', 'disk', 'path')
    ->where('title', 'like', '%emonc%')
    ->get();
```

### Inspect retrieval trace

Retrieval trace is stored in:

```text
rag_messages.token_usage.retrieval_trace
```

Useful fields:

- profile
- primary queries
- fallback queries
- selected documents
- selected locations
- retrieval ms
- source count

### If retrieval is fast but chat is slow

Likely cause:

- DeepSeek generation latency.

Check:

- local `/search` timing;
- `retrieval_trace.retrieval_ms`;
- full `rag_messages.latency_ms`;
- whether streaming deltas are arriving.

### If answer repeats title-only text

Check:

- whether citations are only module/title pages;
- whether useful slide content exists in local `/search`;
- whether `isModuleTitleOnlySource()` is filtering correctly;
- whether curriculum fallback is triggered;
- whether source count is too low for a broad question.

### If a DOCX topic returns no result

Check:

- Laravel `rag_documents` has the DOCX as `ready`;
- local `/documents` likely will not list it;
- `shouldTryStoredDocumentLookup()` should trigger for short title/acronym queries;
- `mergeStoredDocumentSources()` should extract and chunk the stored DOCX.

## 25. Current Limitations

1. Local FastAPI service does not ingest DOCX.
2. Structural expansion from title slide to following body slides is not fully implemented yet.
3. Conversation context resolution is still lightweight; short follow-ups are helped by query planning but not by persisted active-topic state.
4. Retrieval traces are stored inside `token_usage`, not a dedicated `retrieval_traces` table.
5. DeepSeek generation can still take 30-45 seconds.
6. Reranking is not implemented.
7. Full section-aware ingestion from the multi-stage guide is not implemented.
8. There is no formal evaluation dataset yet.

## 26. Recommended Next Work

Follow the multi-stage guide gradually. Do not rewrite everything at once.

### Phase 1: Finish observability

- Add a dedicated `rag_retrieval_traces` table.
- Store trace by message id.
- Add an admin/debug view for selected documents, queries, and timings.

### Phase 2: Structural expansion

- Detect title slides/section heading sources.
- Pull following body slides/chunks.
- For broad questions, include nearby chunks from the same module/section.
- Keep this deterministic before adding a reranker.

### Phase 3: Better DOCX ingestion

- Add DOCX support to local FastAPI service, or fully index DOCX in Laravel with searchable chunks.
- Preserve headings, lists, tables, and order.
- Reprocess the EMonC DOCX after support is ready.

### Phase 4: Conversation context

- Persist active topic/document/section in `rag_conversations.metadata`.
- Resolve follow-ups such as:
  - `how about hypothermia`
  - `continue`
  - `what about feeding`
  - `the next module`

### Phase 5: Evaluation dataset

Create regression questions:

- `oxygen therapy, what is it?`
- `tell me more about oxygen therapy`
- `how about hypothermia`
- `emonc`
- `what does emonc stand for?`
- `tell me more about care of preterms`
- `resuscitation module should take how long and what is the breakdown of the sessions`
- `show me assessment of newborn`
- `describe assessment of newborn`

Each test should assert:

- expected profile;
- expected document/source;
- not title-only;
- citations present;
- deterministic curriculum answers use `local-curriculum` and skip the final chat answer call where expected;
- latency budget where practical.

## 27. Safety Rules For Future Work

- Do not remove the stored DOCX fallback until local DOCX ingestion is proven and EMonC is reindexed.
- Do not let curriculum fallback override useful slide/vector content.
- Do not increase query-planner calls without measuring latency.
- Do not expose `.env` secret values in docs, logs, or UI.
- Do not make local RAG internet-facing; keep it on `127.0.0.1`.
- Do not run destructive database migrations without a backup and rollback plan.
- Keep tests for oxygen, hypothermia, EMonC, resuscitation module timing, streaming, and source formatting.

## 28. Quick Mental Model

If a user asks a question:

1. The chat UI streams through `RagChatStreamController`.
2. `RagClient` chooses a retrieval profile.
3. DeepSeek may formulate search queries.
4. Local RAG searches Chroma using lexical-first and then vector fallback.
5. Laravel enriches with curriculum, outlines, and stored documents when needed.
6. Sources are ranked and formatted.
7. DeepSeek streams the final answer.
8. The assistant message stores citations, retrieved sources, model, latency, token usage, and retrieval trace.

This is the current working design.
