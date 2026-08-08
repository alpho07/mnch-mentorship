# RAG Adaptive Intelligence Implementation Specification

Version: 1.0
Date: 2026-08-05
Application root: `/var/www/html/mnch-master`
Local service root: `/opt/local-rag`
Supersedes: nothing. Extends `docs/rag-continuity-spec.md` (referred to below as **CONT**).

This document specifies the implementation required to move the knowledge-base RAG from a hardcoded, open-loop pipeline to a measured, self-tuning, self-learning one.

Do not paste live secret values into this document.

---

## 0. Summary Of Intent

### 0.1 Problems being solved

| ID | Symptom | Current cause |
|----|---------|---------------|
| P1 | Slow responses (30–45 s) | Every question pays for LLM query planning and DeepSeek generation regardless of difficulty. No latency budget. |
| P2 | No results returned | Absolute `LEXICAL_MIN_SCORE=4.0` is length-dependent; DOCX not indexed; corpus cache dead window; search circuit breaker trips during ingest. |
| P3 | Hallucinations | A weak-context prompt still demands an answer. Nothing enforces citations or verifies numbers. |
| P4 | Deterministic behaviour requires hand-written words | 4 manual term bridges, keyword lists in `curriculumAnswer()`, `curriculumScheduleAnswer()`, visual-request word list, document-inventory word list, module-list word list. |
| P5 | Cannot tell why a response was bad | Trace nested in `token_usage`; no signal values; no per-stage timing. |

### 0.2 Design principles

1. **Measure, then decide.** Every routing choice is driven by a computed signal, never by a keyword list.
2. **Abstain rather than invent.** Insufficient context produces a grounded corpus listing, not a generated answer.
3. **Escalate on evidence.** Expensive stages run only when cheap stages provably failed.
4. **Learn the vocabulary from the corpus.** Acronyms, synonyms, morphological variants and aliases are derived from the indexed documents and from production traces, not authored.
5. **Tune from a fixture.** Thresholds are optimised nightly against an evaluation set; `.env` holds fallbacks only.
6. **Shadow before enforce.** Every decision mechanism runs in scoring-only mode first, and is promoted only when traces show it would not regress a known-good case.

### 0.3 Deliverables

| # | Deliverable | Type |
|---|-------------|------|
| D1 | `rag_retrieval_traces` table + admin trace viewer | Observability |
| D2 | `Answerability` gate with 6 signals and 3 decisions | Decision |
| D3 | `Deadline` budget + `StageLadder` escalation | Latency |
| D4 | `AnswerModelRouter` (local qwen vs DeepSeek vs abstain) | Latency |
| D5 | `SemanticAnswerCache` + `corpus_version` invalidation | Latency |
| D6 | `GroundednessVerifier` with numeric guard | Hallucination |
| D7 | `Lexicon` (auto acronyms, PMI, outline, curriculum, trace distillation) | Self-learning |
| D8 | `RagSetting` runtime store + nightly `RagAutoTune` sweep | Self-tuning |
| D9 | Local service: BM25 normalisation, trigram index, DOCX, non-blocking ingest, `/lexicon` | Retrieval |
| D10 | `rag_eval_cases` fixture + harness + harvesting | Evaluation |
| D11 | Retirement of hardcoded branches, gated on trace evidence | Cleanup |
| D12 | Security fixes (authorisation, injection isolation, SSE hardening) | Hardening |

---

## 1. Configuration Conflicts Requiring Confirmation Before Implementation

These conflict with CONT §4/§5 and must be confirmed before any code lands. Each is listed with the proposed resolution.

| # | Conflict | Proposed resolution | Blocking |
|---|----------|--------------------|----------|
| C1 | `RAG_CHAT_TIMEOUT` default 15 s vs observed 30–45 s DeepSeek generation (CONT §4 vs §20) | Set `RAG_CHAT_TIMEOUT=90`. Non-streaming `answer()` and extractive fallback currently time out mid-generation. | Yes |
| C2 | `RAG_EMBEDDING_PROVIDER=openai` with `RAG_EMBEDDING_API_KEY=` empty, while CONT §4 states the in-app engine uses these settings | Switch to `ollama` / `http://127.0.0.1:11434` / `bge-m3`. This likely repairs in-app DOCX indexing at config level. **Verify first** with §1.1 below. | Yes |
| C3 | `RAG_TOP_K_DEFAULT=1` is dead config once the profile router assigns `top_k` (CONT §5 vs §13) | Remove profile `top_k` constants; `top_k` becomes a ladder-stage parameter. Keep env as stage-1 value only. | No |
| C4 | Local service runs `--workers 1` with `RAG_INGEST_TIMEOUT=300`; ingest blocks `/search`, tripping `RAG_SEARCH_MAX_FAILURES=2` | Move ingest to a thread pool (§9.5). Do not raise workers until Chroma write serialisation is in place. | Yes |
| C5 | `RAG_SEARCH_TIMEOUT=3` and `RAG_SEARCH_MAX_FAILURES=2` absent from CONT §5 runtime listing | Document live values; add circuit-breaker half-open semantics (§9.7). | No |
| C6 | `RAG_CHAT_MODEL=deepseek-v4-flash` unverified against provider catalogue | Confirm model string; add a startup preflight that logs a warning on 400/404 from `/chat/completions`. | No |
| C7 | `MAX_UPLOAD_MB=100` / `RAG_MAX_UPLOAD_SIZE_KB=102400` not reconciled with PHP/Apache limits | Document required `upload_max_filesize`, `post_max_size`, `LimitRequestBody`. | No |

### 1.1 Pre-implementation verification (run before writing code)

```sql
-- Is in-app DOCX indexing actually producing embeddings?
SELECT d.id, d.title, d.status, d.extension,
       COUNT(c.id)                       AS chunks,
       SUM(c.embedding IS NULL)          AS null_embeddings,
       MAX(c.embedding_model)            AS embedding_model
FROM rag_documents d
LEFT JOIN rag_chunks c ON c.rag_document_id = d.id
GROUP BY d.id
ORDER BY d.extension, d.id;
```

```bash
# Does the local service see the corpus it should?
curl -sS --max-time 5 http://127.0.0.1:8001/documents | jq '.[] | {id, name, chunks}'
curl -sS --max-time 3 http://127.0.0.1:8001/health   | jq
```

Record both outputs in the implementation ticket. If `chunks = 0` or `null_embeddings = chunks` for DOCX rows, C2 is confirmed as a live defect and Phase 3 of CONT §26 is re-scoped from "add DOCX to FastAPI" to "fix in-app embedding configuration", which is a config change.

---

## 2. Target Architecture

```text
Filament Chat UI
    |
    | POST /admin/rag/chat/stream            (SSE)
    v
RagChatStreamController
    |  - authorise, rate limit, persist user message
    |  - open Deadline(total_ms)
    v
RagPipeline                                  (new orchestrator)
    |
    +-- 1. SemanticAnswerCache::lookup()          ~1-10 ms
    |        hit -> stream cached answer, done
    |
    +-- 2. StageLadder::run()                     escalates while gate says 'expand'
    |        |
    |        +-- stage: lexicon_bridge      (Lexicon-expanded queries)     ~10 ms
    |        +-- stage: vector              (raw question)                 ~100 ms
    |        +-- stage: structural          (neighbour/outline expansion)  ~50 ms
    |        +-- stage: stored_document     (Laravel DOCX/text fallback)   ~200 ms
    |        +-- stage: planner             (DeepSeek query planning)      ~1-6 s
    |        |
    |        after each stage: Answerability::assess()
    |
    +-- 3. AnswerModelRouter::route()
    |        'abstain' -> CorpusListingAnswer (no LLM)
    |        'local'   -> Ollama qwen2.5:7b-instruct     ~1-3 s
    |        'remote'  -> DeepSeek stream                ~30-45 s
    |
    +-- 4. GroundednessVerifier               (sentence-gated during stream)
    |
    +-- 5. Persist: rag_messages + rag_retrieval_traces
    |
    +-- 6. SemanticAnswerCache::store()       (only if verified + not abstained)

Asynchronous / scheduled
    BuildRagLexicon      after each ingest + nightly
    RagAutoTune          nightly
    HarvestRagEvalCases  nightly
```

`RagClient` is retained as the retrieval transport (local `/search`, `/ingest`, `/documents`). All decision logic moves out of `RagClient` into the new classes. This keeps CONT §22 unit tests meaningful and limits blast radius.

---

## 3. New And Modified Files

### 3.1 New PHP files

```text
app/Services/Rag/RagPipeline.php
app/Services/Rag/Answerability.php
app/Services/Rag/Signals/TopScore.php
app/Services/Rag/Signals/Margin.php
app/Services/Rag/Signals/TermCoverage.php
app/Services/Rag/Signals/ContentDensity.php
app/Services/Rag/Signals/Agreement.php
app/Services/Rag/Signals/SignalContract.php
app/Services/Rag/Deadline.php
app/Services/Rag/StageLadder.php
app/Services/Rag/Stages/StageContract.php
app/Services/Rag/Stages/LexiconBridgeStage.php
app/Services/Rag/Stages/VectorStage.php
app/Services/Rag/Stages/StructuralStage.php
app/Services/Rag/Stages/StoredDocumentStage.php
app/Services/Rag/Stages/PlannerStage.php
app/Services/Rag/AnswerModelRouter.php
app/Services/Rag/LocalAiProvider.php
app/Services/Rag/CorpusListingAnswer.php
app/Services/Rag/SemanticAnswerCache.php
app/Services/Rag/GroundednessVerifier.php
app/Services/Rag/SentenceGate.php
app/Services/Rag/Lexicon/Lexicon.php
app/Services/Rag/Lexicon/Tokenizer.php
app/Services/Rag/Lexicon/AcronymMiner.php
app/Services/Rag/Lexicon/CooccurrenceMiner.php
app/Services/Rag/Lexicon/OutlineMiner.php
app/Services/Rag/Lexicon/CurriculumMiner.php
app/Services/Rag/Lexicon/TraceDistiller.php
app/Services/Rag/Lexicon/VariantMatcher.php
app/Services/Rag/Settings/RagSettings.php
app/Services/Rag/Eval/EvalHarness.php
app/Services/Rag/Eval/ThresholdSweeper.php
app/Services/Rag/Eval/CaseHarvester.php

app/Models/RagRetrievalTrace.php
app/Models/RagLexiconTerm.php
app/Models/RagLexiconEdge.php
app/Models/RagAnswerCache.php
app/Models/RagSetting.php
app/Models/RagEvalCase.php
app/Models/RagEvalRun.php

app/Jobs/BuildRagLexicon.php
app/Jobs/RagAutoTune.php
app/Jobs/HarvestRagEvalCases.php

app/Filament/Resources/RagRetrievalTraceResource.php
app/Filament/Resources/RagLexiconTermResource.php
app/Filament/Resources/RagEvalCaseResource.php
app/Filament/Pages/RagEvalDashboard.php

app/Console/Commands/RagEvalCommand.php
app/Console/Commands/RagTuneCommand.php
app/Console/Commands/RagLexiconCommand.php
app/Console/Commands/RagDoctorCommand.php
```

### 3.2 Modified PHP files

| File | Change |
|------|--------|
| `app/Http/Controllers/RagChatStreamController.php` | Delegate to `RagPipeline`; sentence-gated SSE; persist trace row; emit `signal` and `warn` events |
| `app/Services/Rag/RagClient.php` | Strip decision logic; keep transport + source normalisation; expose `search(array $queries, int $topK)` |
| `app/Services/Rag/ExternalAiProvider.php` | Add `streamSentences()`; accept injected prompt; return usage + finish reason |
| `app/Services/Rag/InAppRagEngine.php` | Honour `ollama` embedding provider (C2) |
| `app/Support/RagSourceFormatter.php` | Expose `prose(string): string` used by `ContentDensity` |
| `app/Jobs/ProcessRagDocument.php` | Bump `corpus_version`; dispatch `BuildRagLexicon`; invalidate caches |
| `app/Models/RagTermBridge.php` | Deprecated; migrate rows into `rag_lexicon_edges` with `source='manual'` |
| `config/rag.php` | New sections (§4) |
| `routes/web.php` | Trace viewer + eval dashboard routes |

### 3.3 Modified local service files

```text
/opt/local-rag/app/main.py
/opt/local-rag/app/retrieval.py        (new: BM25 + trigram)
/opt/local-rag/app/extract.py          (new: unit extraction incl. DOCX)
/opt/local-rag/app/lexicon.py          (new: corpus statistics endpoint)
/opt/local-rag/.env
/etc/systemd/system/local-rag.service
```

---

## 4. Configuration Additions

`config/rag.php`. Every value below is a **fallback**; the runtime value is read from `rag_settings` when present (§8).

```php
'runtime_settings' => [
    'enabled'   => env('RAG_RUNTIME_SETTINGS', true),
    'cache_key' => 'rag:settings:v1',
    'cache_ttl' => 60,
],

'budget' => [
    'total_ms'          => env('RAG_BUDGET_TOTAL_MS', 12000),
    'reserve_answer_ms' => env('RAG_BUDGET_RESERVE_ANSWER_MS', 4000),
    'stage_default_ms'  => env('RAG_BUDGET_STAGE_DEFAULT_MS', 500),
],

'gate' => [
    'mode'       => env('RAG_GATE_MODE', 'shadow'), // shadow | enforce
    'sufficient' => env('RAG_GATE_SUFFICIENT', 0.62),
    'expand'     => env('RAG_GATE_EXPAND', 0.28),
    'weights'    => [
        'top_score'       => 0.24,
        'margin'          => 0.12,
        'term_coverage'   => 0.28,
        'content_density' => 0.20,
        'agreement'       => 0.10,
        'source_count'    => 0.06,
    ],
],

'ladder' => [
    'stages' => ['lexicon_bridge', 'vector', 'structural', 'stored_document', 'planner'],
    'top_k'  => [
        'lexicon_bridge'  => 4,
        'vector'          => 6,
        'structural'      => 8,
        'stored_document' => 4,
        'planner'         => 8,
    ],
    'max_sources' => env('RAG_LADDER_MAX_SOURCES', 10),
],

'router' => [
    'local_max_sources'   => env('RAG_ROUTER_LOCAL_MAX_SOURCES', 3),
    'local_min_score'     => env('RAG_ROUTER_LOCAL_MIN_SCORE', 0.72),
    'local_max_context'   => env('RAG_ROUTER_LOCAL_MAX_CONTEXT', 2400),
    'remote_model'        => env('RAG_CHAT_MODEL'),
    'local_model'         => env('RAG_LOCAL_CHAT_MODEL', 'qwen2.5:7b-instruct'),
    'local_base_url'      => env('RAG_LOCAL_CHAT_BASE_URL', 'http://127.0.0.1:11434'),
    'local_timeout'       => env('RAG_LOCAL_CHAT_TIMEOUT', 25),
],

'answer_cache' => [
    'enabled'        => env('RAG_ANSWER_CACHE_ENABLED', true),
    'exact'          => env('RAG_ANSWER_CACHE_EXACT', true),
    'semantic'       => env('RAG_ANSWER_CACHE_SEMANTIC', true),
    'min_similarity' => env('RAG_ANSWER_CACHE_MIN_SIMILARITY', 0.97),
    'max_rows'       => env('RAG_ANSWER_CACHE_MAX_ROWS', 5000),
    'ttl_days'       => env('RAG_ANSWER_CACHE_TTL_DAYS', 30),
],

'grounding' => [
    'mode'              => env('RAG_GROUNDING_MODE', 'shadow'), // shadow | warn | strip
    'min_support'       => env('RAG_GROUNDING_MIN_SUPPORT', 0.34),
    'numeric_guard'     => env('RAG_GROUNDING_NUMERIC_GUARD', true),
    'require_citations' => env('RAG_GROUNDING_REQUIRE_CITATIONS', true),
    'semantic_tier'     => env('RAG_GROUNDING_SEMANTIC_TIER', true),
    'ambiguous_band'    => [0.20, 0.55],
],

'lexicon' => [
    'enabled'              => env('RAG_LEXICON_ENABLED', true),
    'cache_key'            => 'rag:lexicon:v1',
    'cache_ttl'            => 900,
    'stopword_df'          => env('RAG_LEXICON_STOPWORD_DF', 0.60),
    'min_term_length'      => env('RAG_LEXICON_MIN_TERM_LENGTH', 3),
    'pmi_min'              => env('RAG_LEXICON_PMI_MIN', 2.0),
    'pmi_min_cooccur'      => env('RAG_LEXICON_PMI_MIN_COOCCUR', 4),
    'edges_per_term'       => env('RAG_LEXICON_EDGES_PER_TERM', 8),
    'expansion_per_query'  => env('RAG_LEXICON_EXPANSION_PER_QUERY', 6),
    'trigram_min_score'    => env('RAG_LEXICON_TRIGRAM_MIN_SCORE', 0.62),
],

'autotune' => [
    'enabled'          => env('RAG_AUTOTUNE_ENABLED', false),
    'schedule'         => env('RAG_AUTOTUNE_SCHEDULE', '03:20'),
    'iterations'       => env('RAG_AUTOTUNE_ITERATIONS', 240),
    'min_cases'        => env('RAG_AUTOTUNE_MIN_CASES', 25),
    'latency_p95_ms'   => env('RAG_AUTOTUNE_LATENCY_P95_MS', 12000),
    'max_unsupported'  => env('RAG_AUTOTUNE_MAX_UNSUPPORTED', 0.05),
    'require_no_regression' => true,
],

'trace' => [
    'enabled'      => env('RAG_TRACE_ENABLED', true),
    'retain_days'  => env('RAG_TRACE_RETAIN_DAYS', 90),
    'store_queries'=> env('RAG_TRACE_STORE_QUERIES', true),
],
```

New `.env` keys (names only):

```env
RAG_CHAT_TIMEOUT=90
RAG_EMBEDDING_PROVIDER=ollama
RAG_EMBEDDING_BASE_URL=http://127.0.0.1:11434
RAG_EMBEDDING_MODEL=bge-m3

RAG_BUDGET_TOTAL_MS=12000
RAG_GATE_MODE=shadow
RAG_GROUNDING_MODE=shadow
RAG_LEXICON_ENABLED=true
RAG_AUTOTUNE_ENABLED=false
RAG_ANSWER_CACHE_ENABLED=true
RAG_LOCAL_CHAT_MODEL=qwen2.5:7b-instruct
RAG_LOCAL_CHAT_BASE_URL=http://127.0.0.1:11434
```

---

## 5. Database Schema

Migration prefix: `2026_08_06_*`.

### 5.1 `rag_retrieval_traces`

```php
Schema::create('rag_retrieval_traces', function (Blueprint $table) {
    $table->id();
    $table->foreignId('rag_message_id')->nullable()->constrained('rag_messages')->nullOnDelete();
    $table->foreignId('rag_conversation_id')->nullable()->constrained('rag_conversations')->nullOnDelete();
    $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

    $table->text('question');
    $table->string('question_hash', 64)->index();

    // decision
    $table->string('decision', 16);            // answer | expand | abstain
    $table->decimal('gate_score', 5, 4)->nullable();
    $table->json('gate_signals')->nullable();  // per-signal raw + normalised
    $table->string('gate_mode', 8);            // shadow | enforce
    $table->string('shadow_decision', 16)->nullable(); // decision the gate WOULD have made

    // ladder
    $table->json('stages')->nullable();        // [{name, ms, sources_added, gate_score, skipped_reason}]
    $table->string('final_stage', 32)->nullable();
    $table->unsignedSmallInteger('search_count')->default(0);
    $table->json('primary_queries')->nullable();
    $table->json('expanded_queries')->nullable();
    $table->json('lexicon_edges_used')->nullable();

    // sources
    $table->unsignedSmallInteger('source_count')->default(0);
    $table->json('selected_documents')->nullable();
    $table->json('selected_locators')->nullable();

    // answer
    $table->string('answer_route', 16)->nullable();   // cache | local | remote | listing
    $table->string('answer_model')->nullable();
    $table->boolean('cache_hit')->default(false);
    $table->string('cache_kind', 16)->nullable();     // exact | semantic
    $table->decimal('cache_similarity', 5, 4)->nullable();

    // grounding
    $table->decimal('grounding_min_support', 5, 4)->nullable();
    $table->unsignedSmallInteger('sentence_count')->default(0);
    $table->unsignedSmallInteger('unsupported_count')->default(0);
    $table->unsignedSmallInteger('numeric_violation_count')->default(0);
    $table->json('unsupported_sentences')->nullable();

    // timing
    $table->unsignedInteger('retrieval_ms')->default(0);
    $table->unsignedInteger('answer_ms')->default(0);
    $table->unsignedInteger('total_ms')->default(0);
    $table->unsignedInteger('budget_ms')->default(0);
    $table->boolean('budget_exhausted')->default(false);

    // context
    $table->unsignedBigInteger('corpus_version')->default(0);
    $table->string('settings_version', 32)->nullable();
    $table->string('fallback_reason')->nullable();
    $table->text('error_message')->nullable();

    $table->timestamps();

    $table->index(['decision', 'created_at']);
    $table->index(['answer_route', 'created_at']);
    $table->index(['corpus_version', 'created_at']);
});
```

### 5.2 `rag_lexicon_terms`

```php
Schema::create('rag_lexicon_terms', function (Blueprint $table) {
    $table->id();
    $table->string('term', 128);
    $table->string('normalised', 128)->index();
    $table->unsignedInteger('document_frequency')->default(0);
    $table->unsignedInteger('chunk_frequency')->default(0);
    $table->decimal('df_ratio', 6, 5)->default(0);      // chunk_frequency / total_chunks
    $table->boolean('is_stopword')->default(false);      // derived: df_ratio > lexicon.stopword_df
    $table->boolean('is_acronym')->default(false);
    $table->json('trigrams')->nullable();                // cached char-trigram set
    $table->unsignedBigInteger('corpus_version')->default(0);
    $table->timestamps();
    $table->unique(['normalised', 'corpus_version']);
    $table->index(['is_stopword', 'df_ratio']);
});
```

### 5.3 `rag_lexicon_edges`

```php
Schema::create('rag_lexicon_edges', function (Blueprint $table) {
    $table->id();
    $table->string('from_term', 128)->index();
    $table->string('to_term', 256);
    $table->string('kind', 32);      // acronym_expansion | expansion_acronym | cooccurrence
                                     // | heading_child | curriculum_alias
                                     // | planner_distilled | variant | manual
    $table->string('source', 16);    // auto | manual
    $table->decimal('weight', 8, 5)->default(0);
    $table->unsignedSmallInteger('priority')->default(100); // lower = applied first
    $table->boolean('enabled')->default(true);
    $table->unsignedInteger('hits')->default(0);
    $table->unsignedInteger('successes')->default(0);  // led to decision=answer
    $table->text('notes')->nullable();
    $table->unsignedBigInteger('corpus_version')->default(0);
    $table->timestamps();

    $table->index(['from_term', 'enabled', 'priority']);
    $table->index(['kind', 'source']);
});
```

Manual rows are never deleted by `BuildRagLexicon`. `source='manual'` always outranks `source='auto'` at equal `kind`.

### 5.4 `rag_answer_cache`

```php
Schema::create('rag_answer_cache', function (Blueprint $table) {
    $table->id();
    $table->string('question_hash', 64);
    $table->text('question');
    $table->text('question_normalised');
    $table->binary('embedding')->nullable();       // float32 packed, bge-m3
    $table->unsignedSmallInteger('embedding_dim')->default(0);
    $table->longText('answer');
    $table->json('citations')->nullable();
    $table->json('retrieved_sources')->nullable();
    $table->string('answer_model')->nullable();
    $table->string('answer_route', 16)->nullable();
    $table->decimal('gate_score', 5, 4)->nullable();
    $table->unsignedBigInteger('corpus_version');
    $table->unsignedInteger('hits')->default(0);
    $table->timestamp('last_hit_at')->nullable();
    $table->timestamps();

    $table->unique(['question_hash', 'corpus_version']);
    $table->index(['corpus_version', 'last_hit_at']);
});
```

### 5.5 `rag_settings`

```php
Schema::create('rag_settings', function (Blueprint $table) {
    $table->id();
    $table->string('key')->unique();
    $table->json('value');
    $table->string('version', 32);        // settings bundle version
    $table->string('source', 16);         // seed | autotune | manual
    $table->text('notes')->nullable();
    $table->timestamps();
});
```

Reserved keys: `corpus_version`, `gate.*`, `grounding.*`, `ladder.*`, `router.*`, `budget.*`, `lexicon.*`, `stage_p95`, `settings_version`.

### 5.6 `rag_eval_cases` and `rag_eval_runs`

```php
Schema::create('rag_eval_cases', function (Blueprint $table) {
    $table->id();
    $table->text('question');
    $table->string('question_hash', 64)->unique();
    $table->string('origin', 16);                 // seed | harvested
    $table->boolean('frozen')->default(false);    // regression set, cannot be auto-edited
    $table->boolean('enabled')->default(true);

    $table->json('expected_documents')->nullable();   // substrings/ids, any-of
    $table->json('expected_locators')->nullable();
    $table->json('must_include')->nullable();         // strings/numbers required in answer
    $table->json('must_not_include')->nullable();
    $table->string('expected_decision', 16)->nullable();
    $table->string('expected_route', 16)->nullable();
    $table->boolean('forbid_title_only')->default(true);
    $table->boolean('require_citations')->default(true);
    $table->unsignedInteger('max_latency_ms')->nullable();
    $table->text('notes')->nullable();
    $table->timestamps();
});

Schema::create('rag_eval_runs', function (Blueprint $table) {
    $table->id();
    $table->string('label');
    $table->json('settings');                  // threshold vector under test
    $table->unsignedSmallInteger('cases_total')->default(0);
    $table->unsignedSmallInteger('cases_passed')->default(0);
    $table->decimal('accuracy', 5, 4)->default(0);
    $table->unsignedInteger('latency_p50_ms')->default(0);
    $table->unsignedInteger('latency_p95_ms')->default(0);
    $table->decimal('unsupported_rate', 5, 4)->default(0);
    $table->decimal('abstain_rate', 5, 4)->default(0);
    $table->json('failures')->nullable();
    $table->boolean('promoted')->default(false);
    $table->timestamps();
});
```

### 5.7 Data migration from `rag_term_bridges`

```php
// 2026_08_06_000009_migrate_rag_term_bridges_to_lexicon.php
foreach (DB::table('rag_term_bridges')->get() as $bridge) {
    foreach (json_decode($bridge->queries ?? '[]', true) ?: [] as $query) {
        DB::table('rag_lexicon_edges')->insert([
            'from_term' => Str::lower($bridge->term),
            'to_term'   => $query,
            'kind'      => 'manual',
            'source'    => 'manual',
            'weight'    => 1.0,
            'priority'  => (int) ($bridge->priority ?? 10),
            'enabled'   => (bool) ($bridge->enabled ?? true),
            'notes'     => 'migrated from rag_term_bridges',
            'created_at'=> now(), 'updated_at' => now(),
        ]);
    }
}
```

Keep `rag_term_bridges` table and model in place, read-path disabled, for one release cycle. Drop only after §12 exit criteria are met.

---

## 6. Component Specifications

### 6.1 `Tokenizer` — "picks all words" without a hand-written dictionary

The requirement that the system find resources without manually authored keywords rests on three properties: no hardcoded stopword list, no hardcoded synonym list, and tolerance of unseen morphological and orthographic variants.

```php
namespace App\Services\Rag\Lexicon;

final class Tokenizer
{
    /** Unicode-aware segmentation. Keeps digits, hyphenated forms, and acronyms. */
    public function tokens(string $text): array
    {
        $text = Str::lower($this->foldDiacritics($text));
        preg_match_all('/[\p{L}\p{N}]+(?:[-\'’][\p{L}\p{N}]+)*/u', $text, $m);
        return $m[0];
    }

    /**
     * Content terms = tokens that are not corpus-derived stopwords and are long enough.
     * Stopwords are DERIVED: any term whose chunk-level df_ratio exceeds
     * lexicon.stopword_df is treated as a stopword. No authored list.
     */
    public function contentTerms(string $text, Lexicon $lexicon): array
    {
        $min = (int) $lexicon->setting('min_term_length');
        return collect($this->tokens($text))
            ->reject(fn ($t) => mb_strlen($t) < $min && ! $lexicon->isAcronym($t))
            ->reject(fn ($t) => $lexicon->isStopword($t))
            ->unique()->values()->all();
    }

    /** Character trigrams, used for variant/typo matching. */
    public function trigrams(string $term): array
    {
        $p = '  ' . $term . ' ';
        $out = [];
        for ($i = 0, $n = mb_strlen($p) - 2; $i < $n; $i++) {
            $out[] = mb_substr($p, $i, 3);
        }
        return array_values(array_unique($out));
    }
}
```

Notes.

- **Derived stopwords** adapt to the corpus: in a clinical mentorship corpus, `patient` or `newborn` may legitimately become near-stopwords, and the system will discover that rather than being told.
- **Acronyms bypass the length floor**, so `ART`, `KMC`, `EmONC` survive tokenisation.
- `foldDiacritics()` uses `Normalizer::normalize(..., FORM_D)` plus mark stripping. No language list.

### 6.2 `VariantMatcher` — unseen word forms

Applied when a question term has no exact match in `rag_lexicon_terms`.

```php
public function resolve(string $term, Lexicon $lexicon): array
{
    // 1. exact
    if ($lexicon->hasTerm($term)) return [$term];

    // 2. shared-prefix stem match (morphology, no stemmer rules authored)
    //    "resuscitations" -> "resuscitation"; "hypothermic" -> "hypothermia"
    $prefix = mb_substr($term, 0, max(4, (int) floor(mb_strlen($term) * 0.7)));
    $stemHits = $lexicon->termsWithPrefix($prefix, limit: 5);

    // 3. character-trigram Jaccard for typos and orthographic drift
    $fuzzy = $lexicon->trigramNeighbours($term,
        min: (float) $lexicon->setting('trigram_min_score'), limit: 5);

    return collect([$term])->merge($stemHits)->merge($fuzzy)->unique()->take(6)->all();
}
```

Prefix-stemming plus trigram Jaccard covers plural/adjectival variants and misspellings without an authored morphology table, and works on mixed English/clinical/Swahili terms present in this corpus.

### 6.3 `Lexicon` — expansion at query time

```php
final class Lexicon
{
    /** @return array{queries: array<string>, edges: array<array>} */
    public function expand(string $question): array
    {
        $terms = $this->tokenizer->contentTerms($question, $this);
        $resolved = collect($terms)
            ->flatMap(fn ($t) => $this->variants->resolve($t, $this))
            ->unique();

        $edges = RagLexiconEdge::query()
            ->whereIn('from_term', $resolved)
            ->where('enabled', true)
            ->orderBy('priority')
            ->orderByDesc('weight')
            ->limit((int) $this->setting('expansion_per_query') * 3)
            ->get()
            ->groupBy('kind');

        $queries = collect([$question]);

        // deterministic ordering: acronym expansion, then aliases, then structure, then cooccurrence
        foreach (['manual', 'acronym_expansion', 'expansion_acronym',
                  'curriculum_alias', 'heading_child',
                  'planner_distilled', 'cooccurrence', 'variant'] as $kind) {
            foreach ($edges->get($kind, []) as $edge) {
                $queries->push($this->compose($question, $edge));
            }
        }

        return [
            'queries' => $queries->unique()->take((int) $this->setting('expansion_per_query'))->values()->all(),
            'edges'   => $edges->flatten()->map->only(['id','from_term','to_term','kind','weight'])->all(),
        ];
    }
}
```

`compose()` produces both a substituted query (`emonc` → `Emergency Obstetric & Newborn Care`) and an augmented query (`oxygen therapy pulse oximetry saturation`), because CONT §8 shows both forms retrieve differently.

Ordering is fixed and weights are stable within a `corpus_version`, so expansion is **deterministic** for a given corpus and settings version — a required property under the caching-discipline constraints.

### 6.4 `Answerability` — the gate

```php
final class Answerability
{
    /**
     * @param array<int, array{score: float, content: string, document: string,
     *                         locator: string, locator_type: string}> $sources
     * @return array{score: float, decision: string, signals: array}
     */
    public function assess(string $question, array $sources, Lexicon $lexicon): array
    {
        $terms = $this->tokenizer->contentTerms($question, $lexicon);

        $signals = [
            'top_score'       => $this->topScore->value($sources),
            'margin'          => $this->margin->value($sources),
            'term_coverage'   => $this->termCoverage->value($terms, $sources, $lexicon),
            'content_density' => $this->contentDensity->value($sources),
            'agreement'       => $this->agreement->value($terms, $sources),
            'source_count'    => $this->normaliseCount(count($sources)),
        ];

        $weights = $this->settings->get('gate.weights');
        $score = 0.0;
        foreach ($weights as $name => $w) {
            $score += $w * max(0.0, min(1.0, $signals[$name]));
        }

        $decision = match (true) {
            $score >= (float) $this->settings->get('gate.sufficient') => 'answer',
            $score >= (float) $this->settings->get('gate.expand')     => 'expand',
            default                                                    => 'abstain',
        };

        return compact('score', 'decision', 'signals');
    }
}
```

#### Signal definitions

| Signal | Definition | Replaces |
|--------|-----------|----------|
| `top_score` | Best source similarity, normalised to `[0,1]`. Chroma cosine distance `d` → `1 - d/2`; BM25 → `bm25 / (bm25 + k)` with `k` from settings. | ad-hoc "weak source" checks |
| `margin` | `clamp((top1 - top3) / max(top1, ε), 0, 1)`. Low margin means everything is equally mediocre. | none |
| `term_coverage` | `|{content terms of question matched in union of sources}| / |content terms|`. Matching uses `VariantMatcher`, so morphological variants count. | keyword presence checks |
| `content_density` | `prose_chars / total_chars` after `RagSourceFormatter::prose()` strips headings, `Speaker notes:` labels, bullet glyphs, table separators, reference lines. | `isModuleTitleOnlySource()` |
| `agreement` | Fraction of question content terms attested in **≥2 distinct locators**. Corroboration proxy. | none |
| `source_count` | `min(count / ladder.max_sources, 1)`. | "source count too low" |

`content_density` is the key generalisation: a module title slide scores near zero on prose ratio without any knowledge of the word "module", so the title-only failure class is handled for documents nobody has written a case for.

#### Decisions

| Decision | Behaviour |
|----------|-----------|
| `answer` | Stop escalating. Route to answer model. |
| `expand` | Run the next ladder stage, re-assess. |
| `abstain` | Do not call any answer model. Return `CorpusListingAnswer`. |

#### Shadow mode

When `gate.mode = shadow`, the gate computes and persists `gate_score`, `gate_signals`, and `shadow_decision`, but the pipeline behaves as today. `enforce` activates decisions. Promotion criteria in §12.

### 6.5 `CorpusListingAnswer` — grounded abstention

No LLM call. Deterministic template built from what retrieval did find:

```text
I could not find enough in the indexed documents to answer that reliably.

Closest material in the corpus:
1. EmONC Simulation and Drills — "Emergency Obstetric & Newborn Care" (document)
2. Module 4: Oxygen therapy — slide 12 (heading only, no body text indexed)

Try naming the module, document, or a more specific term. You can also ask
"what documents are available" for the full list.
```

Rules.

- Never assert a fact about content.
- Always list the nearest documents/locators with their density status.
- Persisted with `answer_route='listing'`, `decision='abstain'`, `fallback_reason` set.
- **Never cached** (§6.8) and always harvested as an eval case candidate (§6.11).

Abstention is the primary anti-hallucination lever: it removes the demand for an answer that a weak-context prompt currently creates.

### 6.6 `Deadline` and `StageLadder`

```php
final class Deadline
{
    private float $startNs;

    public function __construct(private int $totalMs, private int $reserveMs) {
        $this->startNs = hrtime(true);
    }

    public function elapsedMs(): int { return (int) ((hrtime(true) - $this->startNs) / 1e6); }
    public function remainingMs(): int { return max(0, $this->totalMs - $this->elapsedMs()); }

    /** Reserve headroom for answer generation. */
    public function allows(int $estimateMs): bool
    {
        return ($this->remainingMs() - $this->reserveMs) >= $estimateMs;
    }
}
```

```php
final class StageLadder
{
    public function run(string $question, Deadline $deadline): LadderResult
    {
        $sources = [];
        $trace = [];
        $gate = null;

        foreach ($this->settings->get('ladder.stages') as $name) {
            $stage = $this->stages[$name];
            $estimate = $this->p95For($name);          // observed, not guessed

            if (! $deadline->allows($estimate)) {
                $trace[$name] = ['skipped_reason' => 'budget', 'estimate_ms' => $estimate];
                $result->budgetExhausted = true;
                break;
            }

            $t0 = hrtime(true);
            $added = $stage->run($question, $sources, $deadline);
            $sources = $this->merge($sources, $added);
            $gate = $this->answerability->assess($question, $sources, $this->lexicon);

            $trace[$name] = [
                'ms'            => (int) ((hrtime(true) - $t0) / 1e6),
                'sources_added' => count($added),
                'gate_score'    => $gate['score'],
                'signals'       => $gate['signals'],
            ];

            if ($gate['decision'] === 'answer') { break; }
            if ($gate['decision'] === 'abstain' && $name === 'planner') { break; }
        }

        return new LadderResult($sources, $gate, $trace);
    }

    /** p95 of stage duration over the last 7 days, from rag_retrieval_traces. */
    private function p95For(string $name): int
    {
        return (int) ($this->settings->get('stage_p95')[$name]
            ?? config('rag.budget.stage_default_ms'));
    }
}
```

Consequences.

- The planner (1–6 s, CONT §10) runs only when cheap stages fail the gate. Given CONT §8 shows lexical retrieval at 5–10 ms, most questions should terminate at `lexicon_bridge` or `vector`.
- `stage_p95` is populated nightly from traces, so the ladder self-calibrates to the corpus and hardware. Never estimate from a constant when history exists.
- Budget exhaustion routes to `abstain`, with `fallback_reason='budget_exhausted'`. Exhaustion must never fall through to unconstrained generation.

`merge()` preserves the stored-document ranking boost from CONT §15 and deduplicates by `document + locator`, keeping the highest score.

### 6.7 `AnswerModelRouter`

```php
public function route(array $gate, array $sources, Deadline $deadline): string
{
    if ($gate['decision'] === 'abstain')             return 'listing';
    if (! $deadline->allows($this->localEstimateMs)) return 'listing';

    $fitsLocal = count($sources) <= $this->settings->get('router.local_max_sources')
        && ($gate['signals']['top_score'] ?? 0) >= $this->settings->get('router.local_min_score')
        && $this->contextChars($sources) <= $this->settings->get('router.local_max_context');

    if ($fitsLocal && $this->ollamaHealthy()) return 'local';

    return $deadline->allows($this->remoteEstimateMs) ? 'remote' : 'local';
}
```

| Route | Model | Typical latency | Use |
|-------|-------|-----------------|-----|
| `listing` | none | <50 ms | abstain / budget exhausted |
| `local` | `qwen2.5:7b-instruct` via Ollama | 1–3 s | high-confidence extractive answers, ≤3 sources |
| `remote` | DeepSeek stream | 30–45 s | multi-source synthesis |

Ollama is already resident with a 10 m keep-alive (CONT §7) and otherwise idle in hybrid mode. Routing high-confidence short answers to it removes the dominant latency cost for the common case at zero marginal infrastructure.

`LocalAiProvider` mirrors `ExternalAiProvider`'s interface against `/api/chat` with `stream: true`, so the SSE path is identical.

### 6.8 `SemanticAnswerCache`

```php
public function lookup(string $question): ?RagAnswerCache
{
    $version = $this->settings->corpusVersion();

    if ($this->settings->get('answer_cache.exact')) {
        $hit = RagAnswerCache::where('question_hash', $this->hash($question))
            ->where('corpus_version', $version)->first();
        if ($hit) return $this->touch($hit, 'exact', 1.0);
    }

    if (! $this->settings->get('answer_cache.semantic')) return null;

    $embedding = $this->embedder->embed($question);        // bge-m3, ~10 ms warm
    $min = (float) $this->settings->get('answer_cache.min_similarity');

    $best = null; $bestSim = 0.0;
    foreach (RagAnswerCache::where('corpus_version', $version)
                 ->orderByDesc('last_hit_at')
                 ->limit($this->settings->get('answer_cache.max_rows'))
                 ->cursor() as $row) {
        $sim = $this->cosine($embedding, $this->unpack($row->embedding));
        if ($sim > $bestSim) { $bestSim = $sim; $best = $row; }
    }

    return ($best && $bestSim >= $min) ? $this->touch($best, 'semantic', $bestSim) : null;
}
```

- `question_hash` is over the **normalised** question: diacritics folded, lowercased, punctuation collapsed, whitespace squeezed, corpus-derived stopwords removed. So `"what does EmONC stand for?"` and `"EmONC stand for"` share a key.
- `corpus_version` scoping is the correctness mechanism. It also fixes the CONT §8 `CORPUS_CACHE_SECONDS=120` post-upload dead window: version is bumped on ingest completion rather than waiting for a TTL.
- Brute-force cosine over ≤5000 rows in PHP is ~10–20 ms and acceptable. Above that, move the scan to the local service.
- **Never cache** `abstain`/`listing` responses, or answers with `unsupported_count > 0`.
- Eviction: LRU by `last_hit_at` beyond `max_rows`, plus `ttl_days`.

For a mentorship-training corpus, question repetition across users is high. This converts a large fraction of 30–45 s requests into sub-100 ms responses with no quality risk.

### 6.9 `GroundednessVerifier` and `SentenceGate`

Streaming means tokens reach the screen before verification is possible. Switch from token passthrough to **sentence-gated streaming**: buffer the delta stream to sentence boundaries, verify, then emit. Perceived latency is effectively unchanged; retraction becomes possible.

```php
final class SentenceGate
{
    /** Feed raw model deltas; yields complete sentences. */
    public function push(string $delta): array
    {
        $this->buffer .= $delta;
        $out = [];
        while (preg_match('/^(.*?[.!?:](?:\s|$)|.*?\n)/s', $this->buffer, $m)) {
            $out[] = trim($m[1]);
            $this->buffer = mb_substr($this->buffer, mb_strlen($m[1]));
        }
        // Force-flush on overlong buffer so a model without terminal punctuation still streams.
        if (mb_strlen($this->buffer) > 400) { $out[] = $this->buffer; $this->buffer = ''; }
        return $out;
    }
}
```

```php
final class GroundednessVerifier
{
    /** @return array{support: float, numeric_ok: bool, cited: bool, action: string} */
    public function verify(string $sentence, array $sources): array
    {
        $cited = $this->citationIndices($sentence);            // from [1], [2]
        $chunks = $cited ? $this->chunksFor($cited, $sources) : $sources;

        $numericOk = ! $this->settings->get('grounding.numeric_guard')
            || $this->numbersAttested($sentence, $chunks);

        // Tier 1: lexical, ~0 ms
        $support = $this->lexicalSupport($sentence, $chunks);

        // Tier 2: semantic, only in the ambiguous band, ~10 ms
        [$lo, $hi] = $this->settings->get('grounding.ambiguous_band');
        if ($this->settings->get('grounding.semantic_tier') && $support >= $lo && $support <= $hi) {
            $support = max($support, $this->semanticSupport($sentence, $chunks));
        }

        $min = (float) $this->settings->get('grounding.min_support');
        $needsCite = $this->settings->get('grounding.require_citations') && $this->isFactual($sentence);

        $fails = (! $numericOk) || $support < $min || ($needsCite && ! $cited);

        return [
            'support'    => $support,
            'numeric_ok' => $numericOk,
            'cited'      => (bool) $cited,
            'action'     => match ($this->settings->get('grounding.mode')) {
                'strip' => $fails ? 'strip' : 'emit',
                'warn'  => $fails ? 'warn'  : 'emit',
                default => 'emit',                     // shadow: log only
            },
        ];
    }
}
```

**Tier 1 — lexical support.** `max` over cited chunks of the greater of: content-word overlap ratio (question-independent, sentence vs chunk), and normalised longest common n-gram length (n ≥ 3). Zero network cost. Catches fabricated numbers, durations, module names and invented session titles — the dominant failure mode for this corpus.

**Tier 2 — semantic support.** Embed the sentence and each cited chunk with the already-warm `bge-m3`, take max cosine. Only invoked inside the ambiguous band, so typical added cost is a small fraction of sentences.

**Numeric guard.** Every numeral, duration, dosage, percentage and `Module\s*\d+` token in the sentence must appear in a cited chunk after unit normalisation (`135 minutes` ≡ `2 hours 15 minutes` ≡ `2h15`). CONT §14's resuscitation-timing defect is exactly this class and is caught with no model call.

**Citation requirement.** `isFactual()` = sentence contains a content term or a numeral and is not a hedge/transition. Currently CONT §10 *requests* citations in the prompt; nothing enforces them.

**Actions.** `shadow` logs only; `warn` emits an SSE `warn` event and marks the sentence in the UI; `strip` suppresses the sentence and appends a single note that unsupported content was removed. Start in `shadow`.

Per-document and per-stage `unsupported_count` aggregated from traces becomes the hallucination metric, replacing user reports.

### 6.10 `BuildRagLexicon` — self-learning vocabulary

Dispatched after every successful ingest (with `unique_for`) and nightly at 02:40. Replaces manual bridge authoring.

| Miner | Input | Extraction | Edge kind | Answers |
|-------|-------|-----------|-----------|---------|
| `AcronymMiner` | all chunk text | `/\b([\p{Lu}]{2,8})\b\s*[\(\[]([^)\]]{4,80})[\)\]]/u` and inverse `/([\w\s&\-]{6,80})\s*[\(\[]([\p{Lu}]{2,8})[\)\]]/u`; validated by initial-letter agreement ≥ 0.6 | `acronym_expansion`, `expansion_acronym` | The CONT §15 EmONC case, learned rather than coded |
| `OutlineMiner` | `rag_document_outlines` | heading terms → child-heading and sibling terms, weighted by depth | `heading_child` | Title-slide → body-slide expansion (CONT §25 item 2) |
| `CurriculumMiner` | `database/seeders/data/mentorship_curriculum_2025_10_13.php` | module/session names → module number, duration phrases, method labels | `curriculum_alias` | The resuscitation bridge, derived |
| `CooccurrenceMiner` | chunk-level term pairs | PMI `log2(p(a,b)/(p(a)p(b)))`, keep `pmi ≥ pmi_min` and `cooccur ≥ pmi_min_cooccur`, top `edges_per_term` | `cooccurrence` | `oxygen → pulse oximetry, saturation, hypoxaemia` |
| `TraceDistiller` | `rag_retrieval_traces` | traces where an early stage scored `abstain`/`expand` and `planner` then reached `answer`: emit `question terms → the planner query that worked` | `planner_distilled` | Every expensive planner success becomes a cheap deterministic edge |

`TraceDistiller` is the mechanism that satisfies "self-learning": the system becomes faster and more deterministic the more it is used, because LLM planning output is continuously distilled into lexicon edges that the cheap first stage can apply.

Distiller safeguards:

- require ≥2 independent traces with the same successful query before emitting an edge;
- cap `planner_distilled` edges at 500 per corpus version;
- decay `weight` by `successes / max(hits,1)`; disable at `hits ≥ 20 && successes/hits < 0.15`.

Term statistics recomputed each run: `document_frequency`, `chunk_frequency`, `df_ratio`, `is_stopword` (derived from `df_ratio > lexicon.stopword_df`), `is_acronym`, cached `trigrams`. Corpus statistics are pulled from the local service `/lexicon` endpoint (§9.6) to avoid re-reading all chunks in PHP.

Idempotency: rows written with the current `corpus_version`; previous `auto` rows for older versions deleted after the run succeeds. `manual` rows untouched.

### 6.11 `RagAutoTune` and `HarvestRagEvalCases`

**Harvesting** (nightly 02:10). Insert candidate `rag_eval_cases` with `origin='harvested'`, `enabled=false` pending admin review, from traces where any of:

- `decision='abstain'` (or `shadow_decision='abstain'`);
- `unsupported_count > 0` or `numeric_violation_count > 0`;
- `total_ms > budget_ms`;
- user sent a rephrase within 60 s of a response (dissatisfaction proxy).

Deduplicate by `question_hash`. Admin approves in `RagEvalCaseResource`, optionally setting `expected_documents` / `must_include`.

**Tuning** (nightly 03:20, `autotune.enabled` gated). Do not hand-pick thresholds.

```
tunable vector T = {
  gate.sufficient, gate.expand, gate.weights[*],
  grounding.min_support, grounding.ambiguous_band,
  router.local_min_score, router.local_max_sources,
  lexicon.pmi_min, lexicon.trigram_min_score,
  ladder.top_k[*]
}

objective  maximise  accuracy(T)             over enabled rag_eval_cases
subject to latency_p95(T)     <= autotune.latency_p95_ms
           unsupported_rate(T) <= autotune.max_unsupported
           accuracy(T) on frozen cases  >=  accuracy(current) on frozen cases
```

Search: random restarts + coordinate descent, `autotune.iterations` budget, retrieval results memoised per case so each candidate re-scores the gate rather than re-querying. Requires `cases_total ≥ autotune.min_cases`.

Promotion writes to `rag_settings` with `source='autotune'` and a new `settings_version`, recorded in `rag_eval_runs.promoted`. Non-promotion is logged with the failing constraint. The CONT §27 safety rules become assertions on frozen cases rather than prose.

`LEXICAL_MIN_SCORE` normalisation (§9.2) is tuned here rather than guessed.

### 6.12 `EvalHarness`

`php artisan rag:eval [--cases=frozen|all] [--settings=<version>] [--json]`

Per case, run the full pipeline in a non-persisting mode and assert:

| Assertion | Source |
|-----------|--------|
| expected document appears in `selected_documents` | `expected_documents` |
| expected locator appears in `selected_locators` | `expected_locators` |
| answer contains all `must_include`, none of `must_not_include` | fixture |
| `decision` matches `expected_decision` | fixture |
| `answer_route` matches `expected_route` | fixture |
| `content_density` of top source above title-only floor | `forbid_title_only` |
| citations present | `require_citations` |
| `total_ms <= max_latency_ms` | fixture |
| `unsupported_count == 0` | always |

Seed cases (from CONT §26, frozen):

```
oxygen therapy, what is it?
tell me more about oxygen therapy
how about hypothermia
emonc
what does emonc stand for?
tell me more about care of preterms
resuscitation module should take how long and what is the breakdown of the sessions
show me assessment of newborn
describe assessment of newborn
what documents are available
```

Plus negative controls (must `abstain`, never fabricate):

```
what is the dosage of surfactant in module 12
who signed the 2027 national guideline
how many sessions are in module 99
```

---

## 7. `RagChatStreamController` Contract

```text
POST /admin/rag/chat/stream
body: { question: string, conversation_id?: int }
```

Authorisation and rate limiting unchanged from CONT §11 (`RagAccess::canUseChat()`, key `rag-ask:{user_id}`). Rate-limit values must be documented (currently unspecified).

SSE events:

| Event | Payload | When |
|-------|---------|------|
| `start` | `{conversation_id, message_id, trace_id}` | immediately |
| `stage` | `{name, ms, sources_added, gate_score}` | after each ladder stage |
| `signal` | `{gate_score, signals, decision}` | after final gate assessment |
| `route` | `{route, model}` | after router |
| `delta` | `{text}` | per **verified sentence** |
| `warn` | `{sentence_index, reason, support}` | grounding `warn`/`strip` |
| `sources` | `{sources: [...]}` | before `done` |
| `done` | `{latency_ms, retrieval_ms, model, usage, decision}` | end |
| `error` | `{message}` | failure |

Persistence per request: one `rag_messages` row (unchanged shape) and one `rag_retrieval_traces` row linked by `rag_message_id`. `token_usage.retrieval_trace` is retained as a mirror for one release, then removed (CONT §25 item 4).

### 7.1 SSE transport hardening (undocumented in CONT, likely cause of recurrent "frozen chat")

Required on the stream route:

```apache
<Location /admin/rag/chat/stream>
    SetEnv no-gzip 1
    SetEnv dont-vary 1
    RequestHeader unset Accept-Encoding
</Location>
```

Response headers: `Content-Type: text/event-stream`, `Cache-Control: no-cache, no-transform`, `X-Accel-Buffering: no`, `Connection: keep-alive`.

PHP: `set_time_limit(0)`, `ignore_user_abort(false)`, explicit `ob_flush(); flush();` per event, `output_buffering=Off` for the route, and an SSE comment heartbeat (`: ping\n\n`) every 15 s so proxies do not idle-close during a 45 s remote generation.

---

## 8. Runtime Settings Resolution

```php
RagSettings::get('gate.sufficient');
```

Resolution order:

1. `rag_settings` row (cached under `rag:settings:v1`, TTL `runtime_settings.cache_ttl`);
2. `config('rag.*')`;
3. hardcoded default in the accessor.

Rules.

- Writes go through `RagSettings::put()`, which bumps `settings_version` and flushes the cache.
- `settings_version` is stamped on every trace row, so any behaviour change is attributable.
- `corpus_version` is a monotonic counter in `rag_settings`, incremented by `ProcessRagDocument` on `ready`/`failed`/delete, and mirrored to the local service so `/health` can be compared against Laravel's view.
- A single kill switch per feature (`gate.mode=shadow`, `grounding.mode=shadow`, `lexicon.enabled=false`, `answer_cache.enabled=false`, `autotune.enabled=false`) allows instant rollback without deployment.

---

## 9. Local FastAPI Service Changes

### 9.1 Module split

`main.py` retains routing and app wiring. Extract `retrieval.py`, `extract.py`, `lexicon.py`.

### 9.2 BM25 replaces the absolute lexical threshold

CONT §8's `LEXICAL_MIN_SCORE=4.0` is an absolute cut on a length-varying score, which is why 2-term queries hit lexical at ~5 ms and 4-term queries fall through to vector at ~101 ms. Replace with Okapi BM25 and a normalised gate:

```python
# retrieval.py
def bm25_scores(query_terms, index, k1=1.4, b=0.75):
    ...  # standard BM25 with corpus idf and average chunk length

def normalised_lexical(scores, query_terms, k=None):
    k = k or float(env("LEXICAL_NORM_K", 6.0))
    # length-independent: divide by ideal max for this query, then squash
    ideal = sum(index.idf[t] for t in query_terms) or 1.0
    return {cid: (s / ideal) for cid, s in scores.items()}
```

New env, replacing `LEXICAL_MIN_SCORE`:

```env
LEXICAL_MIN_NORM=0.34        # tuned by RagAutoTune, not guessed
LEXICAL_NORM_K=6.0
BM25_K1=1.4
BM25_B=0.75
```

`/search` returns both `score_raw` and `score_norm`; Laravel consumes `score_norm` so `top_score` and `margin` are comparable across queries. Retain `LEXICAL_MIN_SCORE` reading for one release with a deprecation log line.

### 9.3 Character-trigram index

Build alongside the lexical index at load. Purpose: match unseen morphological and misspelled forms server-side, so retrieval finds resources without an authored synonym list.

```python
def trigram_candidates(term, min_score=0.62, limit=8):
    tg = trigrams(term)
    scored = ((t, jaccard(tg, index.trigrams[t])) for t in index.candidate_terms(tg))
    return [t for t, s in sorted(scored, key=lambda x: -x[1])[:limit] if s >= min_score]
```

Applied to query terms with zero postings before BM25 scoring. Cost: ~2–5 ms.

```env
TRIGRAM_INDEX=1
TRIGRAM_MIN_SCORE=0.62
```

### 9.4 DOCX ingestion

`extract_units()` currently returns `[]` for anything other than PDF/PPTX (CONT §9). Add DOCX via `python-docx`:

- one unit per heading-delimited section, `locator_type='section'`, `locator='<heading path>'`;
- preserve list nesting as `- ` / `1. ` prefixes;
- tables emitted as pipe-delimited rows with a header row marker;
- `locator` falls back to paragraph range when no headings exist;
- emit `outline` entries so `OutlineMiner` and `StructuralStage` can use them.

Reprocess the EmONC DOCX after deployment. Per CONT §27, **do not remove the Laravel stored-document fallback** until reindexing is verified and eval cases `emonc` / `what does emonc stand for?` pass through the vector path.

### 9.5 Non-blocking ingest (C4)

Single-worker uvicorn plus 300 s synchronous ingest blocks `/search`, which trips `RAG_SEARCH_MAX_FAILURES=2` and silently degrades Laravel to fallbacks — reproducing the "title-only answer" symptom as an apparent quality regression.

```python
INGEST_POOL = ThreadPoolExecutor(max_workers=1, thread_name_prefix="ingest")
INGEST_JOBS: dict[str, dict] = {}

@app.post("/ingest")
async def ingest(...):
    job_id = uuid4().hex
    INGEST_JOBS[job_id] = {"status": "queued", "document": name}
    INGEST_POOL.submit(_run_ingest, job_id, path, name)
    return {"job_id": job_id, "status": "queued"}

@app.get("/ingest/{job_id}")
async def ingest_status(job_id: str): return INGEST_JOBS.get(job_id, {"status": "unknown"})
```

Chroma writes serialised by a module-level `threading.Lock`. `ProcessRagDocument` polls `/ingest/{job_id}` with backoff up to `RAG_INGEST_TIMEOUT` instead of holding a single long request. Do not raise `--workers` above 1 until write serialisation is verified under concurrent ingest.

### 9.6 `/lexicon` endpoint

```http
GET /lexicon?since_version=<n>
```

```json
{
  "corpus_version": 42,
  "total_chunks": 1873,
  "terms": [{"term": "oxygen", "df": 61, "cf": 129}],
  "acronyms": [{"acronym": "emonc", "expansion": "Emergency Obstetric & Newborn Care", "count": 7}],
  "pairs": [{"a": "oxygen", "b": "oximetry", "cooccur": 23, "pmi": 3.41}]
}
```

Computed from the already-loaded lexical index; keeps `BuildRagLexicon` from re-reading the corpus through PHP.

### 9.7 Corpus version, cache invalidation, circuit breaker

- `corpus_version` incremented in-process on ingest completion; returned by `/health`, `/search`, `/documents`, `/lexicon`.
- All corpus caches keyed by `corpus_version` rather than relying on `CORPUS_CACHE_SECONDS` TTL. Retain the TTL as a backstop.
- Laravel compares its `rag_settings.corpus_version` against `/health`; a mismatch raises a `RagDoctorCommand` warning.
- Circuit breaker semantics, currently undocumented: after `RAG_SEARCH_MAX_FAILURES` consecutive failures, open for `RAG_SEARCH_BREAKER_OPEN_SECONDS` (new, default 30), then half-open — allow one probe; success closes, failure re-opens with doubling up to 300 s. Breaker state recorded in traces as `fallback_reason='breaker_open'`.

### 9.8 Health contract additions

```json
{
  "status": "ok", "ollama": true,
  "chat_model": "qwen2.5:7b-instruct", "embedding_model": "bge-m3",
  "corpus_version": 42, "chunks": 1873,
  "retrieval": {
    "bm25_k1": 1.4, "bm25_b": 0.75,
    "lexical_min_norm": 0.34, "trigram_index": true,
    "query_embed_cache_size": 512, "corpus_cache_seconds": 120
  },
  "ingest": {"active": 0, "queued": 0},
  "extractors": ["pdf", "pptx", "docx"]
}
```

---

## 10. Security And Correctness Hardening

| # | Issue | Requirement |
|---|-------|-------------|
| S1 | `RagDocumentDownloadController` and `RagMediaController` authorisation unstated (CONT §3) | Both must call `RagAccess` and authorise the document; media paths validated against the document's own media directory with `realpath()` containment. No path traversal. |
| S2 | No per-user/role document scoping — "private uploads" appear globally retrievable | Add `rag_documents.visibility` (`all`, `roles`, `owner`) plus `rag_document_role` pivot. `RagClient::search()` filters sources post-retrieval by the requesting user's grants; filtered documents are excluded from `CorpusListingAnswer`. |
| S3 | Prompt injection via uploaded documents (CONT §10 injects excerpts into an instruction-bearing prompt) | Wrap excerpts in explicit delimiters, declare them untrusted data in the system prompt, strip instruction-like lines during extraction, and enforce a citation/format contract at output. `GroundednessVerifier` is the backstop: injected instructions cannot produce supported sentences. |
| S4 | `RAG_CHAT_API_KEY` fallback to `DEEPSEEK_API_KEY` (CONT §5) | Keep, but log which source was used at boot without logging the value. Never render key state in UI or traces. |
| S5 | Trace rows store full questions | Honour `trace.store_queries`; apply `trace.retain_days` pruning; exclude trace viewer from non-admin roles. |
| S6 | Chroma has no backup procedure while CONT §27 forbids destructive migrations | Nightly `tar` of `/opt/local-rag/data/chroma` to a retained location, with a documented restore + reindex runbook. |
| S7 | Duplicate-checksum skip has no re-index path (CONT §19) | Add a `Reprocess` action that clears `external_document_id`, resets status, and re-dispatches; add a stale-`processing` reaper (>2× job timeout). |

---

## 11. Retiring The Hardcoded Branches

Each item is removed **only** when its exit criterion is met on frozen eval cases. Until then both paths run and the trace records which fired.

| Hardcoded item (CONT ref) | Replacement | Exit criterion |
|---|---|---|
| `isModuleTitleOnlySource()` (§21) | `content_density` signal | Both agree on ≥95% of 30 days of traces |
| Oxygen/hypothermia branches in `curriculumAnswer()` (§14) | Lexicon `cooccurrence` + `curriculum_alias` edges | `oxygen therapy, what is it?`, `tell me more about oxygen therapy`, `how about hypothermia` pass with `answer_route != listing` and `unsupported_count = 0` for 14 consecutive nightly runs |
| `curriculumScheduleAnswer()` (§14) | Generic structured-field lookup triggered by gate signals: high `term_coverage`, low `content_density`, numeral-intent in question | `resuscitation module should take how long...` passes with `must_include: ["135","15","60"]` and numeric guard clean |
| 4 manual bridges in `rag_term_bridges` (§13.1) | `rag_lexicon_edges` (`source='manual'` retained, `auto` grows) | Migration applied; `auto` edges cover each manual trigger term |
| Visual-request word list (§16) | `locator_type='media'` presence in sources + gate; verb-agnostic | `show me assessment of newborn` and `describe assessment of newborn` pass with the correct route |
| Document-inventory and module-list word lists (§17) | `abstain` → `CorpusListingAnswer`, plus a corpus-metadata intent detected by `term_coverage` against document titles rather than a phrase list | `what documents are available` passes; no LLM call |
| Retrieval profiles `fast`/`standard`/`deep` (§13) | `StageLadder` + `Deadline` | Ladder p95 ≤ profile p95 on frozen cases, accuracy not lower |
| `token_usage.retrieval_trace` (§18/§25) | `rag_retrieval_traces` | Trace viewer in use; one release of dual-write completed |

Per CONT §27, the stored-DOCX fallback (`StoredDocumentStage`) is the last thing removed, and only after §9.4 reindexing is proven.

---

## 12. Rollout Plan

Every phase is independently revertable via its settings kill switch.

### Phase 0 — Observability and shadow scoring (no behaviour change)

Scope: `rag_retrieval_traces`, `RagSetting`, `RagSettings`, `Answerability` in `shadow`, `Tokenizer`, trace viewer, `RagDoctorCommand`, C1/C2 config confirmation, §1.1 verification.

Exit criteria:
- 14 days of traces with populated `gate_signals` on ≥95% of requests;
- `shadow_decision='abstain'` fires on all negative controls;
- `shadow_decision='abstain'` fires on **none** of the CONT §21 known-fixed cases;
- trace overhead p95 < 15 ms.

### Phase 1 — Semantic answer cache

Scope: `rag_answer_cache`, `SemanticAnswerCache`, `corpus_version` bump + invalidation (§9.7).

Exit criteria: cache hit rate ≥ 20% after 7 days; zero stale answers across a corpus version bump (verify by ingesting a document and confirming a version change invalidates); p95 latency on hits < 150 ms.

Revert: `answer_cache.enabled=false`.

### Phase 2 — Gate enforcement and numeric guard

Scope: `gate.mode=enforce`, `CorpusListingAnswer`, `grounding.mode=warn` with `numeric_guard=true`.

Exit criteria: all frozen eval cases pass; abstain rate ≤ 15% of requests; zero numeric violations on the resuscitation-timing case; no regression on the CONT §21 cases.

Revert: `gate.mode=shadow`, `grounding.mode=shadow`.

### Phase 3 — Ladder, budget, and model routing

Scope: `StageLadder`, `Deadline`, `AnswerModelRouter`, `LocalAiProvider`, removal of profile constants, `stage_p95` population.

Exit criteria: planner stage invoked on ≤25% of requests; p95 total latency ≤ `budget.total_ms`; accuracy on frozen cases not below Phase 2; `local` route serves ≥30% of answered requests with no accuracy loss on its subset.

Revert: pin `ladder.stages` to `['vector','planner']` and `router` to `remote`.

### Phase 4 — Lexicon self-learning

Scope: local `/lexicon`, BM25 normalisation, trigram index, `BuildRagLexicon` with all five miners, `rag_term_bridges` read-path disabled.

Exit criteria: `auto` edges cover every manual bridge trigger term; `emonc` resolves via `acronym_expansion` with the manual bridge disabled; `term_coverage` improves on ≥10 harvested cases; no accuracy regression on frozen cases.

Revert: `lexicon.enabled=false` (falls back to manual edges, then to raw question).

### Phase 5 — Groundedness strip mode and DOCX ingestion

Scope: `grounding.mode=strip`, semantic tier enabled, local DOCX extractor, EmONC reindex.

Exit criteria: unsupported rate < 2%; strip actions ≤ 1% of sentences (higher indicates a threshold or prompt fault, not a model fault); EmONC answers via the vector path with `StoredDocumentStage` disabled in a staging run.

Revert: `grounding.mode=warn`.

### Phase 6 — Auto-tuning

Scope: `HarvestRagEvalCases`, `ThresholdSweeper`, `RagAutoTune`, `autotune.enabled=true`.

Exit criteria: ≥40 enabled eval cases with ≥15 frozen; three consecutive nightly runs that either promote with measured improvement or decline with a logged constraint; no promotion ever regresses frozen accuracy.

Revert: `autotune.enabled=false`; `rag_settings` rows revertable by `settings_version`.

### Phase 7 — Cleanup

Retire hardcoded branches per §11 exit criteria; drop `rag_term_bridges`; remove `token_usage.retrieval_trace` mirror.

---

## 13. Testing Requirements

### 13.1 Unit

```text
tests/Unit/Rag/TokenizerTest.php
    - derived stopwords: high-df term excluded, acronym retained below length floor
    - diacritic folding, hyphenated forms, digit retention
tests/Unit/Rag/VariantMatcherTest.php
    - "resuscitations" -> "resuscitation"; "hypthermia" -> "hypothermia"
tests/Unit/Rag/AnswerabilitySignalsTest.php
    - content_density ~0 for a module title slide, high for prose slide
    - term_coverage counts morphological variants
    - margin low when all scores equal
    - agreement requires two distinct locators
tests/Unit/Rag/AnswerabilityDecisionTest.php
    - answer / expand / abstain boundaries at configured thresholds
tests/Unit/Rag/DeadlineTest.php
    - allows() respects reserve; remainingMs monotonic
tests/Unit/Rag/StageLadderTest.php
    - stops at first 'answer'; skips on budget; records per-stage trace
tests/Unit/Rag/AnswerModelRouterTest.php
    - listing on abstain; local within limits; remote on multi-source
tests/Unit/Rag/SemanticAnswerCacheTest.php
    - exact hit; semantic hit at threshold; miss across corpus_version bump
    - abstain and unsupported answers never stored
tests/Unit/Rag/SentenceGateTest.php
    - boundary splitting; force flush on overlong buffer
tests/Unit/Rag/GroundednessVerifierTest.php
    - numeric guard rejects "135 minutes" absent from cited chunk
    - unit-equivalent "2 hours 15 minutes" accepted
    - uncited factual sentence flagged
    - lexical support high on paraphrase of cited chunk
tests/Unit/Rag/Lexicon/AcronymMinerTest.php
    - "Emergency Obstetric & Newborn Care (EmONC)" both directions
tests/Unit/Rag/Lexicon/CooccurrenceMinerTest.php
    - PMI ordering and thresholds
tests/Unit/Rag/Lexicon/TraceDistillerTest.php
    - requires 2 independent traces; disables low-success edges
tests/Unit/Rag/Settings/RagSettingsTest.php
    - resolution order; version bump; cache flush
```

Retain and keep passing: `tests/Unit/RagClientTest.php`, `tests/Unit/RagSourceFormatterTest.php` (CONT §22 baseline: 15 passed).

### 13.2 Feature

```text
tests/Feature/Rag/RagChatStreamTest.php
    - SSE event sequence and ordering
    - sentence-gated deltas
    - warn event in warn mode
    - trace row persisted and linked to message
tests/Feature/Rag/RagAbstentionTest.php
    - negative controls produce listing, no outbound LLM call (Http::fake assertions)
tests/Feature/Rag/RagAccessTest.php        (extend existing)
    - document download / media authorisation
    - visibility scoping excludes non-granted documents from sources and listing
tests/Feature/Rag/ProcessRagDocumentTest.php
    - corpus_version bump; lexicon job dispatched; cache invalidated
    - docx produces chunks with non-null embeddings (guards C2)
tests/Feature/Rag/RagEvalHarnessTest.php
    - harness runs frozen cases without persisting messages
```

### 13.3 Local service

```text
/opt/local-rag/tests/test_bm25.py            normalisation is query-length independent
/opt/local-rag/tests/test_trigram.py         variant candidate recall
/opt/local-rag/tests/test_extract_docx.py    headings, lists, tables, outline emission
/opt/local-rag/tests/test_ingest_async.py    /search latency during active ingest < 200 ms
/opt/local-rag/tests/test_lexicon_endpoint.py
```

### 13.4 Gates in CI

```bash
php artisan test --testsuite=Unit --filter=Rag
php artisan test tests/Feature/Rag
vendor/bin/pint --dirty --test
php artisan rag:eval --cases=frozen --json
cd /opt/local-rag && venv/bin/pytest -q
```

`rag:eval --cases=frozen` must pass before any deployment that touches retrieval, gating, or lexicon code.

---

## 14. Operations

### 14.1 Scheduler

```php
// app/Console/Kernel.php
$schedule->job(new HarvestRagEvalCases)->dailyAt('02:10');
$schedule->job(new BuildRagLexicon)->dailyAt('02:40');
$schedule->job(new RagAutoTune)->dailyAt('03:20')
         ->when(fn () => (bool) RagSettings::get('autotune.enabled'));
$schedule->command('rag:eval --cases=frozen')->dailyAt('04:00');
$schedule->command('model:prune')->daily();   // trace + cache retention
```

`BuildRagLexicon` is additionally dispatched from `ProcessRagDocument` on success with `uniqueFor(600)`.

### 14.2 `rag:doctor`

Single command replacing the ad-hoc checks in CONT §24. Exit non-zero on any failure.

```text
[ok]   local-rag /health           corpus_version=42 chunks=1873
[ok]   ollama                      bge-m3 + qwen2.5:7b-instruct resident
[ok]   corpus_version parity       laravel=42 service=42
[warn] docx embeddings             3 documents with null embeddings   -> see C2
[ok]   extractors                  pdf, pptx, docx
[ok]   deepseek preflight          model=deepseek-v4-flash 200
[ok]   settings_version            2026-08-06.3 (autotune)
[ok]   lexicon                     1,412 terms / 3,880 edges (auto 3,864, manual 16)
[ok]   answer cache                412 rows, hit rate 27% (7d)
[warn] abstain rate                18% (7d) exceeds 15% target
[ok]   unsupported rate            1.2% (7d)
[ok]   frozen eval                 15/15 passing
```

### 14.3 Restart procedure

Unchanged from CONT §23. Laravel-only changes:

```bash
php artisan config:clear && php artisan view:clear
systemctl restart php8.3-fpm && systemctl restart apache2
```

Local service changes:

```bash
systemctl restart local-rag        # ollama restart only for model/keep-alive changes
```

Settings changes require **no** restart — `rag_settings` is read at runtime with a 60 s cache. This is the intended path for threshold and kill-switch changes.

### 14.4 Diagnostic decision tree

| Symptom | First check | Then |
|---------|-------------|------|
| Slow answer | `traces.answer_route` | `remote` on an easy question → check `gate_score` and why local was rejected; `retrieval_ms` high → check `stages` for planner invocation |
| No result | `traces.decision`, `fallback_reason` | `breaker_open` → local service blocked, check `ingest.active`; `abstain` with high `term_coverage` → `content_density` low, document indexed heading-only |
| Wrong document | `selected_documents`, `lexicon_edges_used` | A bad `cooccurrence` or `planner_distilled` edge → disable it in `RagLexiconTermResource`; add the question as an eval case |
| Hallucination | `unsupported_sentences`, `numeric_violation_count` | Raise `grounding.mode`; if support was high, the cited chunk genuinely contains the claim — an extraction fault, not a model fault |
| Frozen chat | `traces.total_ms` vs last `delta` timestamp | §7.1 transport (gzip, buffering, heartbeat) before suspecting the model |

---

## 15. Acceptance Criteria

Measured over 14 days post-Phase-6, against the enabled eval set and production traces.

| # | Criterion | Target |
|---|-----------|--------|
| A1 | p50 end-to-end latency | ≤ 2.5 s |
| A2 | p95 end-to-end latency | ≤ `budget.total_ms` (12 s) |
| A3 | Requests reaching the planner stage | ≤ 25% |
| A4 | Answers served from cache | ≥ 20% |
| A5 | Answers served by local model | ≥ 30% of non-cached answered requests |
| A6 | Unsupported-sentence rate | < 2% |
| A7 | Numeric guard violations reaching the user | 0 |
| A8 | Abstain rate | ≤ 15%, and 100% on negative controls |
| A9 | Frozen eval cases passing | 15/15 on every nightly run |
| A10 | Manually authored lexicon edges required for new documents | 0 |
| A11 | Hardcoded topic branches remaining in `RagClient` | 0 |
| A12 | Nightly autotune runs completing with a promote-or-decline decision | 100% |

A10 and A11 are the criteria that correspond directly to the requirement for a system that finds resources without manually built deterministic words.

---

## 16. Risks

| Risk | Mitigation |
|------|-----------|
| Gate abstains too often, users perceive a regression | Shadow for 14 days with an explicit "must not abstain" list drawn from CONT §21; `gate.mode` kill switch; abstain rate in acceptance criteria |
| Autotune overfits a small fixture | Frozen regression subset excluded from optimisation; `min_cases` floor; promote only on no frozen regression |
| Auto-mined `cooccurrence` edges pull in irrelevant documents | PMI floor plus min co-occurrence; per-edge `hits`/`successes` with auto-disable; edges surfaced and disableable in Filament |
| Local model produces weaker answers than DeepSeek | Router requires high `top_score` and ≤3 sources; per-route accuracy tracked in eval runs; router thresholds tunable |
| Sentence gating increases perceived latency | Force-flush at 400 chars; first sentence typically arrives within the same window as today's first tokens; measure first-delta time in traces |
| DOCX ingestion changes chunk boundaries and invalidates cache and lexicon | `corpus_version` bump handles both automatically; reindex in staging first |
| Trace table growth | `trace.retain_days=90` pruning; JSON columns only for structured detail; indexed on decision/route/version |
| Scope creep across seven phases | Each phase independently revertable and independently valuable; Phase 0 and Phase 1 deliver measurable benefit alone |

---

## 17. Open Questions

1. Confirm C1–C7 resolutions, particularly C2 (embedding provider) and C4 (ingest concurrency), before Phase 0 code.
2. Confirm `RAG_CHAT_MODEL=deepseek-v4-flash` is current.
3. Confirm the document visibility model required by S2: is per-role scoping needed now, or is a single "all chat users" grant acceptable for this deployment?
4. Confirm rate-limit values for `rag-ask:{user_id}`.
5. Confirm chunking parameters used by the local service and by `InAppRagEngine` (size, overlap, strategy) — absent from CONT throughout, and they affect retrieval quality more than `top_k`.
6. Confirm whether the eval fixture may contain real clinical content in a shared environment, or whether it must be seeded separately.
