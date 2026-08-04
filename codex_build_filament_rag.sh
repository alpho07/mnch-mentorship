#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="${1:-$(pwd)}"
CODEX_MODEL="${CODEX_MODEL:-}"
REPORT_FILE="${REPORT_FILE:-codex-rag-implementation-report.md}"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
BRANCH="feature/filament-rag-${TIMESTAMP}"

fail(){ echo "ERROR: $*" >&2; exit 1; }

command -v git >/dev/null 2>&1 || fail "git is required"
command -v php >/dev/null 2>&1 || fail "php is required"
command -v composer >/dev/null 2>&1 || fail "composer is required"
command -v codex >/dev/null 2>&1 || fail "Codex CLI is not installed"

cd "$APP_DIR"
[[ -f artisan ]] || fail "$APP_DIR is not a Laravel application"
[[ -d .git ]] || fail "Run this inside a Git repository"
CURRENT_BRANCH="$(git branch --show-current)"
[[ -n "$CURRENT_BRANCH" ]] || fail "Detached HEAD is not supported"
[[ -z "$(git status --porcelain)" ]] || fail "Working tree is not clean. Commit or stash changes first"

echo "Creating isolated branch: $BRANCH"
git switch -c "$BRANCH"

mkdir -p storage/app/codex-baselines
{
  echo "timestamp=$TIMESTAMP"
  echo "base_branch=$CURRENT_BRANCH"
  echo "base_commit=$(git rev-parse HEAD)"
  echo "php=$(php -r 'echo PHP_VERSION;')"
  echo "laravel=$(php artisan --version 2>/dev/null || true)"
  echo "composer_lock_sha256=$(sha256sum composer.lock 2>/dev/null | awk '{print $1}')"
  echo "package_lock_sha256=$(sha256sum package-lock.json 2>/dev/null | awk '{print $1}')"
} > "storage/app/codex-baselines/rag-${TIMESTAMP}.txt"

PROMPT_FILE="$(mktemp)"
trap 'rm -f "$PROMPT_FILE"' EXIT

cat > "$PROMPT_FILE" <<'PROMPT'
You are modifying an existing production Laravel application with Filament already installed.

GOAL
Add a production-quality private document knowledge-base manager and a polished Filament RAG chat page. The existing local FastAPI service defaults to http://127.0.0.1:8001 and exposes POST /ingest, POST /ask, GET /health, and possibly document listing/deletion endpoints.

NON-NEGOTIABLE SAFETY
- Do not deploy or restart any service.
- Do not edit Apache, Nginx, PHP-FPM, Supervisor, Horizon, systemd, Redis, firewall, DNS, TLS, or ports 80/443.
- Do not edit the live .env.
- Do not run migrations against the current database.
- Never run migrate:fresh, migrate:refresh, db:wipe, destructive SQL, composer update, npm audit fix, or production commands.
- Do not upgrade Laravel, Filament, Livewire, PHP, Composer packages, or npm packages.
- Do not replace existing auth, panels, providers, middleware, policies, permissions, navigation, themes, or layouts.
- Keep the feature disabled by default with RAG_ENABLED=false.
- Store uploads privately, never in public/.
- Never send files or content to external APIs.
- Do not commit. Leave changes for human review.

FIRST INSPECT
Detect and document Laravel, Filament major version, Livewire, PHP requirement, panel providers/IDs, user model, roles/permissions/Shield/Spatie usage, tenancy, filesystem disks, queue conventions, test framework, UI conventions, and whether Filament v3 or v4 APIs are required. Adapt to what exists.

CONFIG
Add config/rag.php with:
- enabled: env('RAG_ENABLED', false)
- base_url: env('RAG_BASE_URL', 'http://127.0.0.1:8001')
- connect_timeout, request_timeout, ingest_timeout
- retry_count
- default/min/max top_k
- upload disk default local
- upload directory default private/knowledge-base
- max upload size
- allowed extensions pdf,pptx
- health cache seconds
- optional configurable delete endpoint

Add commented examples only to .env.example. Never modify .env.

DATABASE
Generate additive reversible migrations only; do not execute them.

Create a document table/model, using repository naming conventions, with equivalent fields:
id, uuid unique, title, original_name, stored_name, disk, path, extension, mime_type, size_bytes, sha256 indexed, status (pending/processing/ready/failed), external_document_id nullable, page_or_slide_count nullable, chunk_count nullable, processing_started_at, processed_at, failed_at, error_message text nullable, metadata json nullable, uploaded_by nullable FK to actual users table with safe delete behavior, timestamps, and soft deletes only if project conventions use them.

Create rag_conversations:
id, uuid unique, user FK aligned to existing conventions/tenancy, title nullable, last_message_at nullable, metadata json nullable, timestamps.

Create rag_messages:
id, conversation FK cascade, role, content longText, citations json nullable, retrieved_sources json nullable, model nullable, latency_ms nullable, token_usage json nullable, error_message text nullable, timestamps.

Add sensible indexes. Add casts and relationships. Use enums only when compatible with project PHP/style. Never store hidden reasoning or <think> output.

RAG CLIENT
Create a focused service such as App\Services\Rag\RagClient using Laravel Http:
- server-configured base URL only, never request-controlled
- configured connect/overall timeouts and conservative retries
- ingest(local private path,title)
- ask(question,top_k)
- cached health()
- defensive response validation and normalized citations
- sanitized logging without full document content/secrets
- strip <think>...</think>
- do not disable TLS globally
- introduce no unnecessary dependencies

Expected ask response may resemble:
{"answer":"...","sources":[{"document":"...","page":17,"slide":null,"locator_type":"page","locator":17,"content":"optional"}]}
Support missing optional fields and normalize them.

INGESTION
Implement a queued job following existing queue conventions; it must also work safely when queue=sync.
1. Store upload privately.
2. Compute SHA-256.
3. Create pending record.
4. Mark processing and call /ingest multipart with file and title.
5. Save returned IDs/count metadata when present.
6. Mark ready.
7. On failure mark failed with sanitized error and timestamps, preserving original file.
8. Apply suitable timeout/backoff.
9. Prevent overlapping duplicate processing when supported.
10. Provide retry for failed documents.
11. Warn on duplicate checksum; do not silently duplicate indexing.

FILAMENT DOCUMENT RESOURCE
Create a version-compatible Filament resource visible only when config('rag.enabled') is true.
Navigation group: Knowledge Base unless a matching existing group exists. Label: Documents.

List page:
- title, original filename, type, readable size, status badge, uploader, created/processed dates
- search by title/filename
- filters for status/type/uploader where appropriate
- newest first
- conditional polling only when pending/processing and version supports it
- actions: view, retry failed, authorized private download, delete
- deletion cleans DB/private file safely; remote delete only if endpoint is configured and must fail safely
- no bulk delete unless file cleanup and authorization are correct

Create form:
- required title
- PDF/PPTX only
- extension plus MIME validation
- configured max size
- private storage, original filename retained in metadata
- no arbitrary paths
- processing explanation

View page:
- metadata/status cards
- clear processing state/timeline
- sanitized failure panel
- download/retry actions
- never expose raw server paths

AUTHORIZATION
Integrate with existing roles/permissions/Shield/Spatie/tenancy conventions. Do not auto-grant access to all users. Otherwise create a conservative policy. Scope documents and conversations by tenant/owner where the application does so. Unauthorized users cannot access files, records, or other users' chats.

CHAT PAGE
Prefer a custom Filament page, not CRUD, unless repository architecture strongly indicates otherwise.
- polished, responsive, consistent with existing Filament theme and Tailwind
- no new frontend framework
- desktop conversation sidebar, responsive mobile behavior
- New chat
- user/assistant bubbles
- safely rendered/sanitized Markdown
- compact expandable source citation chips/cards with document and page/slide
- sticky composer, textarea, send, Enter submit and Shift+Enter newline where feasible
- prevent duplicate submits, loading state, retry failures, empty state/example prompts
- Livewire-safe auto-scroll
- persist conversations/messages
- title from first question by safe local truncation; no extra LLM call
- delete own conversation with confirmation
- paginate/limit messages and conversations
- call /ask with question and top_k only
- store normalized citations
- never display/persist <think>
- reliable non-streaming implementation unless endpoint explicitly supports streaming
- hide navigation when disabled
- cached health/unavailable notice that does not break Filament

PRIVATE DOWNLOAD
Create authenticated controller or signed route:
- authorize access
- use disk/path stored in DB
- Storage::disk(...)->download(...)
- reject missing files
- prevent path traversal
- never expose local paths

SECURITY
- validate question length and clamp top_k
- retain CSRF
- escape user text and sanitize Markdown
- named rate limiter for asks where appropriate
- treat retrieved document content as untrusted data, not instructions
- no user-controlled RAG URL
- follow repository logging/privacy conventions

TESTS
Use installed test framework. At minimum cover:
- disabled feature hides/denies access
- authorized valid PDF/PPTX upload metadata
- invalid extension rejection
- private download authorization
- ingest success/failure state transitions with Http::fake
- RagClient success, normalized citations, timeout, non-2xx, malformed JSON
- chat persistence
- <think> stripping
- cross-user authorization denial
- additive reversible migrations
- existing tests remain green

SAFE QUALITY CHECKS
Run only appropriate existing checks such as composer validate --no-check-publish, php artisan about, route:list, config:show rag, focused/full tests, Pint --test if installed, PHPStan/Larastan only if configured, and frontend build only if truly needed and dependencies already exist.
Never run migrations, deployment, package upgrades, or service restarts. Distinguish pre-existing failures.

DOCUMENTATION
Add docs/rag-filament.md with architecture, variables, controlled migration/deployment checklist, queue requirement, permissions, health check, rollback, security notes, and test commands. Do not include changes to ports 80/443.

FINAL REPORT
Create codex-rag-implementation-report.md at repository root with:
- detected versions and conventions
- plan
- files changed/created
- migration names
- architecture decisions
- commands run
- tests/results and pre-existing failures
- manual deployment checklist
- exact rollback procedure
- risks/assumptions
- explicit confirmation that no service, web-server config, firewall, ports 80/443, or live schema was changed

ORDER
Inspect, plan/report, config/domain, migrations/models, client/job, authorization, document resource, chat page, tests, safe checks, security-focused diff review, final report.
Keep changes focused, idiomatic, version-compatible, and as small as maintainability allows.
PROMPT

ARGS=(exec --sandbox workspace-write --ephemeral -C "$APP_DIR" -o "$REPORT_FILE")
[[ -z "$CODEX_MODEL" ]] || ARGS+=(--model "$CODEX_MODEL")
ARGS+=(-)

echo "Running Codex in workspace-write sandbox. No deployment will be performed."
if ! codex "${ARGS[@]}" < "$PROMPT_FILE"; then
  echo "Codex failed. Changes remain isolated on branch: $BRANCH" >&2
  echo "Inspect with: git status && git diff" >&2
  exit 1
fi

echo
echo "Completed on branch: $BRANCH"
echo "No migration or deployment was performed."
echo "Review commands:"
echo "  cd '$APP_DIR'"
echo "  git status"
echo "  git diff --stat '$CURRENT_BRANCH'...HEAD"
echo "  git diff '$CURRENT_BRANCH'...HEAD"
echo "  cat '$REPORT_FILE'"
echo
echo "Rollback generated work:"
echo "  git switch '$CURRENT_BRANCH'"
echo "  git branch -D '$BRANCH'"
