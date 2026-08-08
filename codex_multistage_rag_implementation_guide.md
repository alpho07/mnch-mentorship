# Codex Implementation Guide: Fast Multi-Stage Document Retrieval Pipeline

## 1. Purpose

Implement a production-grade, document-grounded AI assistant that:

- understands the user’s current conversational context;
- distinguishes narrow questions from broad questions;
- expands retrieval only when necessary;
- retrieves complete sections instead of isolated title slides;
- stays faithful to uploaded documents;
- maintains low latency;
- supports citations back to documents, pages, slides, and sections;
- avoids unnecessary autonomous-agent loops;
- measures retrieval quality and performance.

This specification is written for Codex. Treat it as the implementation contract.

## 2. Primary Problem

The current pipeline performs a single retrieval query and may return only one highly similar chunk.

Example:

User query:

> Tell me more about care of preterms.

Current behavior:

1. Embed the literal query.
2. Retrieve the nearest chunk.
3. Return a title slide such as “Care of Preterm Infants.”
4. Generate an answer using incomplete context.

Required behavior:

1. Detect that “tell me more” is a broad explanatory request.
2. Resolve “preterms” to the active topic and document context.
3. Search headings, section summaries, keyword index, and vector index.
4. Expand relevant title slides into their following body slides.
5. identify expected facets such as thermal care, feeding, respiratory care, infection prevention, monitoring, and discharge.
6. retrieve additional evidence only when coverage is inadequate.
7. generate an answer using diverse, cited, document-supported passages.

## 3. Core Design Principles

### 3.1 Adaptive retrieval

Do not run the full pipeline for every query.

Use three retrieval profiles:

- `fast`: narrow factual questions;
- `standard`: normal explanatory questions;
- `deep`: broad, ambiguous, comparative, or incomplete-context questions.

### 3.2 Cheap recall before expensive precision

Use inexpensive operations first:

1. rule-based query classification;
2. metadata filtering;
3. hybrid retrieval;
4. deterministic neighbor expansion;
5. lightweight reranking;
6. optional targeted second pass.

Do not call the main generation model repeatedly during retrieval unless deterministic approaches are insufficient.

### 3.3 Document structure is first-class data

Documents are not unordered bags of chunks.

Preserve:

- document;
- page;
- slide;
- chapter;
- section;
- subsection;
- heading;
- chunk order;
- previous and next chunk relationships;
- parent section relationships.

### 3.4 Evidence coverage matters more than single-result similarity

A high similarity score does not imply completeness.

A broad answer should include multiple relevant facets and sections.

### 3.5 One optional recovery pass

The default maximum is two retrieval rounds:

- initial retrieval;
- one targeted recovery pass for missing facets.

Do not create an unbounded retrieval loop.

### 3.6 Faithfulness

The final response must distinguish:

- document-supported facts;
- reasonable synthesis across document passages;
- missing or unsupported information.

Do not invent details to fill document gaps.

## 4. Target Architecture

```text
User Query
    |
    v
Conversation Context Resolver
    |
    v
Query Normalizer
    |
    v
Retrieval Profile Router
    |
    +----------------------------+
    |                            |
    v                            v
Fast Path                    Expanded Path
    |                            |
    v                            v
Hybrid Search               Query Expansion
    |                            |
    v                            v
Neighbor Expansion          Parallel Hybrid Searches
    |                            |
    +-------------+--------------+
                  |
                  v
          Reciprocal Rank Fusion
                  |
                  v
          Metadata/Section Expansion
                  |
                  v
             Deduplication
                  |
                  v
              Reranking
                  |
                  v
           Coverage Evaluation
                  |
          +-------+-------+
          |               |
      sufficient      insufficient
          |               |
          |               v
          |       One Targeted Search
          |               |
          +-------+-------+
                  |
                  v
          Context Pack Builder
                  |
                  v
          Grounded Generation
                  |
                  v
         Answer with Citations
```

## 5. Suggested Technology Assumptions

Codex may adapt these to the existing stack.

Suggested baseline:

- Application: Laravel 11 or later
- Database: PostgreSQL with `pgvector`, or an existing vector database
- Queue: Redis-backed Laravel queues
- Cache: Redis
- Full-text retrieval: PostgreSQL FTS, Meilisearch, Elasticsearch, or OpenSearch
- Embeddings: local or remote embedding model
- Reranker: lightweight cross-encoder or local reranking model
- Generator: Qwen or another instruction-tuned model
- Document parsing: PDF, DOCX, PPTX, and plain text extraction service

Do not replace an existing production component unless required.

## 6. Data Model

Create or adapt the following entities.

### 6.1 `documents`

```sql
CREATE TABLE documents (
    id BIGSERIAL PRIMARY KEY,
    tenant_id BIGINT NULL,
    title VARCHAR(500) NOT NULL,
    source_type VARCHAR(50) NOT NULL,
    mime_type VARCHAR(150) NULL,
    storage_path TEXT NOT NULL,
    checksum VARCHAR(128) NOT NULL,
    version INTEGER NOT NULL DEFAULT 1,
    language VARCHAR(20) NULL,
    page_count INTEGER NULL,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    processing_status VARCHAR(50) NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);

CREATE INDEX documents_tenant_idx ON documents (tenant_id);
CREATE UNIQUE INDEX documents_checksum_version_idx
    ON documents (checksum, version);
```

### 6.2 `document_sections`

```sql
CREATE TABLE document_sections (
    id BIGSERIAL PRIMARY KEY,
    document_id BIGINT NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    parent_section_id BIGINT NULL REFERENCES document_sections(id) ON DELETE CASCADE,
    title TEXT NOT NULL,
    normalized_title TEXT NOT NULL,
    level INTEGER NOT NULL DEFAULT 1,
    start_order INTEGER NOT NULL,
    end_order INTEGER NULL,
    summary TEXT NULL,
    topics JSONB NOT NULL DEFAULT '[]'::jsonb,
    embedding VECTOR(1024) NULL,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);

CREATE INDEX document_sections_document_idx
    ON document_sections (document_id);

CREATE INDEX document_sections_parent_idx
    ON document_sections (parent_section_id);

CREATE INDEX document_sections_order_idx
    ON document_sections (document_id, start_order, end_order);
```

Adjust the vector dimension to match the selected embedding model.

### 6.3 `document_chunks`

```sql
CREATE TABLE document_chunks (
    id BIGSERIAL PRIMARY KEY,
    document_id BIGINT NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    section_id BIGINT NULL REFERENCES document_sections(id) ON DELETE SET NULL,
    parent_chunk_id BIGINT NULL REFERENCES document_chunks(id) ON DELETE SET NULL,
    previous_chunk_id BIGINT NULL REFERENCES document_chunks(id) ON DELETE SET NULL,
    next_chunk_id BIGINT NULL REFERENCES document_chunks(id) ON DELETE SET NULL,
    chunk_order INTEGER NOT NULL,
    page_number INTEGER NULL,
    slide_number INTEGER NULL,
    content_type VARCHAR(50) NOT NULL DEFAULT 'paragraph',
    heading TEXT NULL,
    content TEXT NOT NULL,
    searchable_text TEXT NOT NULL,
    token_count INTEGER NOT NULL DEFAULT 0,
    topics JSONB NOT NULL DEFAULT '[]'::jsonb,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    embedding VECTOR(1024) NULL,
    search_vector TSVECTOR NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);

CREATE UNIQUE INDEX document_chunks_order_unique
    ON document_chunks (document_id, chunk_order);

CREATE INDEX document_chunks_document_idx
    ON document_chunks (document_id);

CREATE INDEX document_chunks_section_idx
    ON document_chunks (section_id);

CREATE INDEX document_chunks_page_idx
    ON document_chunks (document_id, page_number);

CREATE INDEX document_chunks_slide_idx
    ON document_chunks (document_id, slide_number);

CREATE INDEX document_chunks_search_idx
    ON document_chunks USING GIN (search_vector);
```

### 6.4 `conversation_document_states`

```sql
CREATE TABLE conversation_document_states (
    id BIGSERIAL PRIMARY KEY,
    conversation_id VARCHAR(100) NOT NULL,
    user_id BIGINT NULL,
    active_document_ids JSONB NOT NULL DEFAULT '[]'::jsonb,
    active_section_ids JSONB NOT NULL DEFAULT '[]'::jsonb,
    active_topic TEXT NULL,
    active_entities JSONB NOT NULL DEFAULT '[]'::jsonb,
    last_query TEXT NULL,
    last_resolved_query TEXT NULL,
    last_intent VARCHAR(50) NULL,
    last_retrieved_chunk_ids JSONB NOT NULL DEFAULT '[]'::jsonb,
    state_version INTEGER NOT NULL DEFAULT 1,
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);

CREATE UNIQUE INDEX conversation_document_states_conversation_unique
    ON conversation_document_states (conversation_id);
```

### 6.5 `retrieval_traces`

Use this table for debugging and evaluation.

```sql
CREATE TABLE retrieval_traces (
    id BIGSERIAL PRIMARY KEY,
    conversation_id VARCHAR(100) NULL,
    query TEXT NOT NULL,
    resolved_query TEXT NOT NULL,
    retrieval_profile VARCHAR(20) NOT NULL,
    expanded_queries JSONB NOT NULL DEFAULT '[]'::jsonb,
    document_filters JSONB NOT NULL DEFAULT '[]'::jsonb,
    section_candidates JSONB NOT NULL DEFAULT '[]'::jsonb,
    retrieved_candidates JSONB NOT NULL DEFAULT '[]'::jsonb,
    selected_chunks JSONB NOT NULL DEFAULT '[]'::jsonb,
    expected_facets JSONB NOT NULL DEFAULT '[]'::jsonb,
    covered_facets JSONB NOT NULL DEFAULT '[]'::jsonb,
    missing_facets JSONB NOT NULL DEFAULT '[]'::jsonb,
    coverage_score NUMERIC(5,4) NULL,
    second_pass_used BOOLEAN NOT NULL DEFAULT FALSE,
    timings JSONB NOT NULL DEFAULT '{}'::jsonb,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMP NOT NULL
);
```

## 7. Ingestion Pipeline

Heavy work should happen during ingestion, not during user queries.

### 7.1 Ingestion stages

```text
Upload
  -> Parse
  -> Normalize
  -> Detect structure
  -> Create sections
  -> Create chunks
  -> Link neighbors
  -> Generate searchable text
  -> Extract topics
  -> Generate section summaries
  -> Generate embeddings
  -> Build indexes
  -> Mark ready
```

### 7.2 Parsing requirements

For each file type, preserve structure.

#### PDF

Capture:

- page number;
- headings where detectable;
- paragraphs;
- tables;
- image captions;
- reading order.

#### PPTX

Capture:

- slide number;
- slide title;
- body text;
- speaker notes;
- table text;
- chart labels where available.

A title-only slide must be marked as `title_slide` or `section_heading`.

#### DOCX

Capture:

- heading levels;
- paragraph order;
- tables;
- lists;
- section hierarchy.

### 7.3 Chunking strategy

Do not use fixed token windows alone.

Preferred chunking order:

1. preserve complete semantic units;
2. keep heading with body;
3. keep table headers with table rows;
4. split only when a semantic unit exceeds the token limit;
5. retain limited overlap for split units.

Suggested chunk size:

- target: 300-700 tokens;
- maximum: 900 tokens;
- overlap: 50-100 tokens only when splitting a long unit.

### 7.4 Chunk content types

Use a controlled enum:

```php
enum ChunkContentType: string
{
    case TitleSlide = 'title_slide';
    case SectionHeading = 'section_heading';
    case SlideBody = 'slide_body';
    case Paragraph = 'paragraph';
    case Table = 'table';
    case FigureCaption = 'figure_caption';
    case Notes = 'notes';
    case Summary = 'summary';
}
```

### 7.5 Searchable text

Build `searchable_text` using contextual prefixes.

Example:

```text
Document: Essential Newborn Care
Section: Care of the Preterm Infant
Subsection: Feeding
Page: 24

Actual chunk text...
```

Do not embed raw body text without section context.

### 7.6 Neighbor linking

After chunks are inserted:

```php
$chunks = DocumentChunk::query()
    ->where('document_id', $documentId)
    ->orderBy('chunk_order')
    ->get();

foreach ($chunks as $index => $chunk) {
    $chunk->update([
        'previous_chunk_id' => $chunks[$index - 1]->id ?? null,
        'next_chunk_id' => $chunks[$index + 1]->id ?? null,
    ]);
}
```

### 7.7 Section summaries

Generate concise section summaries at ingestion.

Each summary should state:

- section purpose;
- main concepts;
- procedures;
- key terms;
- likely user questions answered by the section.

Embed the summary separately.

### 7.8 Topic metadata

Topic tags may be generated by:

- deterministic heading analysis;
- domain dictionary matching;
- one low-cost model call during ingestion.

Do not depend entirely on generated topic tags. They are supplementary metadata.

## 8. Runtime Pipeline Interfaces

Create explicit data-transfer objects.

```php
final readonly class RetrievalRequest
{
    public function __construct(
        public string $conversationId,
        public string $query,
        public array $allowedDocumentIds,
        public ?int $userId = null,
        public ?string $language = null,
    ) {}
}
```

```php
final readonly class ResolvedQuery
{
    public function __construct(
        public string $original,
        public string $resolved,
        public string $primaryTopic,
        public array $entities,
        public array $documentIds,
        public array $preferredSectionIds,
        public string $intent,
        public float $confidence,
    ) {}
}
```

```php
final readonly class RetrievalProfile
{
    public function __construct(
        public string $name,
        public int $queryExpansionLimit,
        public int $vectorLimit,
        public int $keywordLimit,
        public int $sectionLimit,
        public int $rerankLimit,
        public int $finalChunkLimit,
        public int $neighborBefore,
        public int $neighborAfter,
        public bool $allowSecondPass,
        public int $maxContextTokens,
    ) {}
}
```

```php
final readonly class RetrievalCandidate
{
    public function __construct(
        public int $chunkId,
        public int $documentId,
        public ?int $sectionId,
        public float $vectorScore,
        public float $keywordScore,
        public float $headingScore,
        public float $fusionScore,
        public array $matchedQueries,
        public array $metadata,
    ) {}
}
```

```php
final readonly class CoverageResult
{
    public function __construct(
        public float $score,
        public array $expectedFacets,
        public array $coveredFacets,
        public array $missingFacets,
        public bool $sufficient,
    ) {}
}
```

## 9. Stage 1: Conversation Context Resolution

### 9.1 Goal

Resolve short or ambiguous follow-ups without passing the full chat transcript into retrieval.

Examples:

```text
Previous topic: care of preterm infants
User: What about feeding?
Resolved query: Feeding of preterm infants
```

```text
Previous document: Essential Newborn Care
User: Explain the next part.
Resolved query: Explain the section following the currently active section in Essential Newborn Care
```

### 9.2 Stored state

Maintain:

- active documents;
- active sections;
- active topic;
- recent entities;
- last resolved query;
- last retrieved chunks;
- last intent.

### 9.3 Resolution rules

Use deterministic rules first.

```php
final class ConversationContextResolver
{
    public function resolve(
        RetrievalRequest $request,
        ?ConversationDocumentState $state
    ): ResolvedQuery {
        $query = trim($request->query);
        $normalized = mb_strtolower($query);

        $documentIds = $request->allowedDocumentIds;
        $preferredSectionIds = [];
        $primaryTopic = $query;
        $intent = 'unknown';
        $confidence = 0.60;

        if ($state) {
            $documentIds = array_values(array_intersect(
                $request->allowedDocumentIds,
                $state->active_document_ids ?? []
            )) ?: $request->allowedDocumentIds;

            $preferredSectionIds = $state->active_section_ids ?? [];

            if ($this->isFollowUp($normalized) && $state->active_topic) {
                $primaryTopic = $state->active_topic;
                $query = $this->combineTopicAndFollowUp(
                    $state->active_topic,
                    $request->query
                );
                $confidence = 0.85;
            }
        }

        $intent = $this->inferIntent($normalized);

        return new ResolvedQuery(
            original: $request->query,
            resolved: $query,
            primaryTopic: $primaryTopic,
            entities: [],
            documentIds: $documentIds,
            preferredSectionIds: $preferredSectionIds,
            intent: $intent,
            confidence: $confidence,
        );
    }
}
```

### 9.4 Follow-up markers

Examples:

- “what about…”;
- “tell me more”;
- “continue”;
- “the next one”;
- “why?”;
- “how?”;
- pronouns such as “it,” “this,” “that,” “they”;
- short noun phrases after a broader prior topic.

Use a small LLM only when deterministic resolution confidence is below a threshold such as `0.65`.

## 10. Stage 2: Retrieval Profile Routing

### 10.1 Profiles

```php
final class RetrievalProfiles
{
    public static function fast(): RetrievalProfile
    {
        return new RetrievalProfile(
            name: 'fast',
            queryExpansionLimit: 1,
            vectorLimit: 8,
            keywordLimit: 8,
            sectionLimit: 2,
            rerankLimit: 8,
            finalChunkLimit: 4,
            neighborBefore: 0,
            neighborAfter: 1,
            allowSecondPass: false,
            maxContextTokens: 3000,
        );
    }

    public static function standard(): RetrievalProfile
    {
        return new RetrievalProfile(
            name: 'standard',
            queryExpansionLimit: 2,
            vectorLimit: 12,
            keywordLimit: 12,
            sectionLimit: 3,
            rerankLimit: 14,
            finalChunkLimit: 7,
            neighborBefore: 1,
            neighborAfter: 2,
            allowSecondPass: false,
            maxContextTokens: 5000,
        );
    }

    public static function deep(): RetrievalProfile
    {
        return new RetrievalProfile(
            name: 'deep',
            queryExpansionLimit: 4,
            vectorLimit: 18,
            keywordLimit: 18,
            sectionLimit: 5,
            rerankLimit: 20,
            finalChunkLimit: 10,
            neighborBefore: 1,
            neighborAfter: 4,
            allowSecondPass: true,
            maxContextTokens: 8000,
        );
    }
}
```

### 10.2 Routing rules

Use broad language patterns:

```php
final class RetrievalProfileRouter
{
    private const DEEP_PATTERNS = [
        'tell me more',
        'explain in detail',
        'discuss',
        'overview',
        'everything about',
        'care of',
        'management of',
        'how do we manage',
        'compare',
        'summarize the section',
        'teach me',
    ];

    private const FAST_PATTERNS = [
        'what is the definition',
        'what does',
        'when',
        'which page',
        'who',
        'state the',
        'list the name',
    ];

    public function route(ResolvedQuery $query): RetrievalProfile
    {
        $normalized = mb_strtolower($query->resolved);

        foreach (self::DEEP_PATTERNS as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return RetrievalProfiles::deep();
            }
        }

        foreach (self::FAST_PATTERNS as $pattern) {
            if (str_contains($normalized, $pattern)) {
                return RetrievalProfiles::fast();
            }
        }

        if ($query->intent === 'broad_explanation') {
            return RetrievalProfiles::deep();
        }

        if (str_word_count($normalized) <= 7) {
            return RetrievalProfiles::standard();
        }

        return RetrievalProfiles::standard();
    }
}
```

Do not use query length alone.

## 11. Stage 3: Query Expansion

### 11.1 Goal

Generate a few high-value alternate queries, not dozens of variants.

For:

```text
Tell me more about care of preterms
```

Produce:

```json
[
  "care of preterm infants",
  "feeding and thermal care of premature infants",
  "respiratory support and infection prevention in preterm babies",
  "monitoring and discharge criteria for preterm infants"
]
```

### 11.2 Expansion methods

Use, in order:

1. domain synonym dictionary;
2. abbreviation dictionary;
3. active section titles;
4. heading matches;
5. optional small-model expansion.

### 11.3 Domain synonym example

```php
return [
    'preterms' => [
        'preterm infants',
        'premature infants',
        'preterm neonates',
        'low birth weight babies',
    ],
    'kmc' => [
        'kangaroo mother care',
        'skin-to-skin care',
    ],
    'respiratory distress' => [
        'breathing difficulty',
        'respiratory support',
        'cpap',
        'oxygen therapy',
    ],
];
```

### 11.4 Expansion prompt

When a model is required, use a constrained prompt:

```text
You generate retrieval queries for a document search system.

Return JSON only.

Generate at most {limit} concise search queries.
Use synonyms, parent topics, child topics, and expert-related facets.
Do not answer the user.
Do not invent facts.
Keep each query under 14 words.
Prefer queries likely to match document headings and body text.

User query:
{resolved_query}

Active topic:
{active_topic}

Known section titles:
{section_titles}
```

Expected response:

```json
{
  "queries": [
    "care of preterm infants",
    "feeding and thermal care of premature babies",
    "respiratory support and infection prevention in preterms"
  ]
}
```

Validate and truncate model output.

## 12. Stage 4: Hierarchical Section Retrieval

Search section headings and section summaries before searching all chunks.

### 12.1 Section vector search

```sql
SELECT
    id,
    document_id,
    title,
    summary,
    1 - (embedding <=> :query_embedding) AS similarity
FROM document_sections
WHERE document_id = ANY(:document_ids)
ORDER BY embedding <=> :query_embedding
LIMIT :section_limit;
```

### 12.2 Heading keyword search

Boost exact and partial heading matches.

```sql
SELECT
    id,
    document_id,
    title,
    CASE
        WHEN normalized_title = :normalized_query THEN 1.0
        WHEN normalized_title LIKE '%' || :normalized_query || '%' THEN 0.8
        ELSE 0.4
    END AS heading_score
FROM document_sections
WHERE document_id = ANY(:document_ids)
  AND normalized_title ILIKE '%' || :keyword || '%'
ORDER BY heading_score DESC
LIMIT :section_limit;
```

### 12.3 Section restriction

When high-confidence sections are found, search chunks primarily inside them.

Do not make section restriction absolute unless confidence is high. Preserve a small global search path to avoid missing cross-section evidence.

Suggested policy:

- 80% of chunk candidates from selected sections;
- 20% from global document search.

## 13. Stage 5: Parallel Hybrid Retrieval

Run vector search and keyword search concurrently for each expanded query.

### 13.1 Vector search

```sql
SELECT
    id,
    document_id,
    section_id,
    chunk_order,
    content_type,
    heading,
    content,
    page_number,
    slide_number,
    1 - (embedding <=> :query_embedding) AS vector_score
FROM document_chunks
WHERE document_id = ANY(:document_ids)
  AND (
      :section_ids_is_empty = TRUE
      OR section_id = ANY(:section_ids)
  )
ORDER BY embedding <=> :query_embedding
LIMIT :limit;
```

### 13.2 Keyword search

```sql
SELECT
    id,
    document_id,
    section_id,
    chunk_order,
    content_type,
    heading,
    content,
    page_number,
    slide_number,
    ts_rank_cd(search_vector, websearch_to_tsquery(:query)) AS keyword_score
FROM document_chunks
WHERE document_id = ANY(:document_ids)
  AND search_vector @@ websearch_to_tsquery(:query)
ORDER BY keyword_score DESC
LIMIT :limit;
```

### 13.3 Parallel execution

Use concurrent HTTP calls, async jobs, or database promises depending on infrastructure.

Do not dispatch slow queue jobs for a synchronous user query unless the queue round trip is demonstrably faster than local concurrency.

## 14. Stage 6: Reciprocal Rank Fusion

Use RRF to combine results from:

- vector search;
- full-text search;
- heading search;
- section-summary search;
- multiple expanded queries.

```php
final class ReciprocalRankFusion
{
    public function fuse(array $rankedLists, int $k = 60): array
    {
        $scores = [];
        $metadata = [];

        foreach ($rankedLists as $listName => $items) {
            foreach ($items as $rank => $item) {
                $id = $item['chunk_id'];

                $scores[$id] = ($scores[$id] ?? 0.0)
                    + (1.0 / ($k + $rank + 1));

                $metadata[$id] ??= $item;
                $metadata[$id]['matched_lists'][] = $listName;
            }
        }

        arsort($scores);

        return array_map(
            function (int $chunkId) use ($scores, $metadata): array {
                return [
                    ...$metadata[$chunkId],
                    'fusion_score' => $scores[$chunkId],
                ];
            },
            array_keys($scores)
        );
    }
}
```

Do not compare raw vector and full-text scores directly unless they are normalized.

## 15. Stage 7: Structural Expansion

### 15.1 Purpose

Convert isolated structural matches into useful content.

### 15.2 Expansion rules

```php
final class ChunkExpansionPolicy
{
    public function rangeFor(DocumentChunk $chunk): array
    {
        return match ($chunk->content_type) {
            'title_slide' => [0, 5],
            'section_heading' => [0, 8],
            'slide_body' => [1, 2],
            'table' => [1, 1],
            'paragraph' => [1, 1],
            default => [0, 1],
        };
    }
}
```

### 15.3 Section expansion

If a section heading is highly ranked, retrieve:

- section heading;
- first body chunks;
- high-scoring chunks from within the section;
- optionally section summary.

Do not automatically retrieve an entire very long section.

### 15.4 Neighbor query

```sql
SELECT *
FROM document_chunks
WHERE document_id = :document_id
  AND chunk_order BETWEEN :start_order AND :end_order
ORDER BY chunk_order;
```

### 15.5 Deduplicate expansion

A chunk may be discovered by multiple candidates. Add it once and preserve all provenance.

## 16. Stage 8: Deduplication and Diversity

### 16.1 Exact duplicates

Normalize content and hash it.

```php
function normalizedChunkHash(string $text): string
{
    $normalized = mb_strtolower($text);
    $normalized = preg_replace('/\s+/u', ' ', $normalized);
    $normalized = trim($normalized);

    return hash('xxh3', $normalized);
}
```

### 16.2 Near duplicates

Use one of:

- embedding similarity;
- MinHash;
- SimHash;
- token overlap.

Default threshold:

```text
cosine similarity greater than 0.92
```

Keep the candidate with:

1. higher rerank score;
2. more complete heading context;
3. stronger citation metadata.

### 16.3 Diversity constraints

For deep questions, final context should normally represent multiple sections or facets.

Do not let ten near-identical chunks from one slide deck region consume the context budget.

Use maximal marginal relevance or a simpler penalty:

```text
adjusted_score = relevance_score - diversity_penalty
```

Apply a penalty when:

- same section already contributes several chunks;
- same page or slide already contributes repeated content;
- candidate is highly similar to selected content.

## 17. Stage 9: Reranking

### 17.1 Candidate limits

Recommended:

- fused candidates: 30-50;
- structurally expanded candidates: up to 40;
- reranker input: top 15-20;
- final selected chunks: 4-10.

### 17.2 Reranker input

Include:

```text
Query: {resolved_query}
Document: {document_title}
Section: {section_title}
Page/Slide: {location}
Content type: {content_type}
Candidate text: {content}
```

### 17.3 Reranker output

Return a score and optional labels:

```json
{
  "score": 0.91,
  "directly_relevant": true,
  "supports_broad_coverage": true
}
```

### 17.4 Fallback

If the reranker fails or times out:

- use RRF order;
- apply metadata and diversity rules;
- continue generating.

The assistant should degrade gracefully.

## 18. Stage 10: Coverage Evaluation

### 18.1 Expected facets

Expected facets are not facts. They are retrieval targets.

For a broad medical topic such as preterm care:

```json
[
  "thermal care",
  "feeding",
  "respiratory support",
  "infection prevention",
  "monitoring",
  "discharge"
]
```

Generate facets using:

1. document section titles;
2. topic dictionary;
3. selected section summaries;
4. optional constrained model output.

Prefer document-derived facets over generic model knowledge.

### 18.2 Coverage algorithm

```php
final class CoverageEvaluator
{
    public function evaluate(
        array $expectedFacets,
        Collection $chunks
    ): CoverageResult {
        if ($expectedFacets === []) {
            return new CoverageResult(
                score: 1.0,
                expectedFacets: [],
                coveredFacets: [],
                missingFacets: [],
                sufficient: true,
            );
        }

        $corpus = mb_strtolower(
            $chunks->map(fn ($chunk) => implode(' ', [
                $chunk->heading,
                $chunk->section?->title,
                $chunk->searchable_text,
                json_encode($chunk->topics),
            ]))->implode(' ')
        );

        $covered = [];
        $missing = [];

        foreach ($expectedFacets as $facet) {
            if ($this->facetAppears($facet, $corpus)) {
                $covered[] = $facet;
            } else {
                $missing[] = $facet;
            }
        }

        $score = count($covered) / max(count($expectedFacets), 1);

        return new CoverageResult(
            score: $score,
            expectedFacets: $expectedFacets,
            coveredFacets: $covered,
            missingFacets: $missing,
            sufficient: $score >= 0.70,
        );
    }
}
```

Use synonym-aware matching rather than exact string matching only.

### 18.3 Second-pass criteria

Run a second pass only when all are true:

```text
profile allows second pass
AND coverage is below 0.70
AND at least one missing facet is document-supported or heading-supported
AND initial retrieval has not exhausted the latency budget
```

### 18.4 Targeted second pass

Search only missing facets:

```text
preterm infant feeding
preterm thermal care
preterm discharge criteria
```

Do not repeat all original searches.

### 18.5 Stop conditions

Stop retrieval when any is true:

- coverage at least 0.70;
- final chunk count reached;
- new unique candidates fewer than two;
- second pass completed;
- latency budget exceeded;
- context token budget reached.

## 19. Stage 11: Context Pack Building

### 19.1 Context pack structure

```json
{
  "resolved_query": "Explain care of preterm infants",
  "active_documents": [
    {
      "id": 22,
      "title": "Essential Newborn Care"
    }
  ],
  "coverage": {
    "score": 0.83,
    "covered_facets": [
      "thermal care",
      "feeding",
      "respiratory support",
      "infection prevention",
      "monitoring"
    ],
    "missing_facets": [
      "discharge"
    ]
  },
  "evidence": [
    {
      "citation_id": "D22-S42-C311",
      "document_title": "Essential Newborn Care",
      "section_title": "Care of the Preterm Infant",
      "page": 18,
      "slide": 18,
      "content": "..."
    }
  ]
}
```

### 19.2 Ordering

Order evidence logically:

1. definitions or overview;
2. major care domains;
3. procedures;
4. monitoring;
5. discharge or follow-up.

Do not order only by retrieval score.

### 19.3 Token budget

Preserve complete evidence where possible.

Suggested allocation:

```text
fast: 3,000 context tokens
standard: 5,000 context tokens
deep: 8,000 context tokens
```

### 19.4 Compression

Apply deterministic compression first:

- remove repeated headings;
- remove headers and footers;
- remove exact duplicates;
- trim unrelated sentences;
- preserve table headers;
- preserve citation identity.

Do not summarize evidence with the main LLM unless necessary.

## 20. Stage 12: Grounded Generation

### 20.1 System instruction

Use an instruction similar to:

```text
You are a document-grounded assistant.

Answer the user's question using only the supplied evidence and clearly identified conversation context.

Requirements:
1. Synthesize across relevant evidence instead of relying on the first passage.
2. For broad questions, organize the answer by the major facets represented in the evidence.
3. Cite each important factual claim using the provided citation IDs.
4. Never invent document content.
5. If a requested aspect is missing from the supplied evidence, state that it was not found in the selected documents.
6. Do not claim that the documents say more than they actually say.
7. Distinguish document evidence from general explanation when general explanation is permitted.
8. Prefer concise, complete answers.
```

### 20.2 Strict document-only mode

Support a configuration option:

```php
enum GroundingMode: string
{
    case StrictDocuments = 'strict_documents';
    case DocumentsPreferred = 'documents_preferred';
}
```

In strict mode:

- no unsupported external knowledge;
- state missing evidence explicitly.

### 20.3 Citation format

Every evidence chunk must have a stable citation identifier.

Example output:

```text
Thermal protection is emphasized as a core component of preterm care. [D22-S42-C311]
```

The UI may transform stable IDs into clickable document references.

## 21. Orchestrator

Create one orchestration service.

```php
final class DocumentRetrievalOrchestrator
{
    public function __construct(
        private ConversationContextResolver $contextResolver,
        private RetrievalProfileRouter $profileRouter,
        private QueryExpansionService $queryExpansion,
        private SectionRetriever $sectionRetriever,
        private HybridRetriever $hybridRetriever,
        private ReciprocalRankFusion $fusion,
        private StructuralExpander $structuralExpander,
        private CandidateDeduplicator $deduplicator,
        private CandidateReranker $reranker,
        private CoverageEvaluator $coverageEvaluator,
        private ContextPackBuilder $contextPackBuilder,
        private RetrievalTraceRecorder $traceRecorder,
    ) {}

    public function retrieve(RetrievalRequest $request): ContextPack
    {
        $timer = new StageTimer();

        $state = $this->loadConversationState($request->conversationId);

        $resolved = $timer->measure(
            'context_resolution',
            fn () => $this->contextResolver->resolve($request, $state)
        );

        $profile = $this->profileRouter->route($resolved);

        $sections = $timer->measure(
            'section_retrieval',
            fn () => $this->sectionRetriever->retrieve($resolved, $profile)
        );

        $queries = $timer->measure(
            'query_expansion',
            fn () => $this->queryExpansion->expand(
                $resolved,
                $sections,
                $profile
            )
        );

        $rankedLists = $timer->measure(
            'parallel_hybrid_retrieval',
            fn () => $this->hybridRetriever->retrieveAll(
                resolvedQuery: $resolved,
                expandedQueries: $queries,
                sectionCandidates: $sections,
                profile: $profile,
            )
        );

        $fused = $this->fusion->fuse($rankedLists);

        $expanded = $timer->measure(
            'structural_expansion',
            fn () => $this->structuralExpander->expand(
                $fused,
                $profile
            )
        );

        $deduplicated = $this->deduplicator->deduplicate($expanded);

        $reranked = $timer->measure(
            'reranking',
            fn () => $this->reranker->rerank(
                $resolved->resolved,
                $deduplicated,
                $profile->rerankLimit
            )
        );

        $expectedFacets = $this->deriveExpectedFacets(
            $resolved,
            $sections,
            $reranked
        );

        $coverage = $this->coverageEvaluator->evaluate(
            $expectedFacets,
            collect($reranked)
        );

        $secondPassUsed = false;

        if (
            $profile->allowSecondPass
            && !$coverage->sufficient
            && $this->withinLatencyBudget($timer)
        ) {
            $secondPassUsed = true;

            $additionalLists = $timer->measure(
                'second_pass',
                fn () => $this->hybridRetriever->retrieveMissingFacets(
                    resolvedQuery: $resolved,
                    missingFacets: $coverage->missingFacets,
                    sectionCandidates: $sections,
                    profile: $profile,
                )
            );

            $combined = $this->fusion->fuse([
                'initial' => $reranked,
                ...$additionalLists,
            ]);

            $expanded = $this->structuralExpander->expand(
                $combined,
                $profile
            );

            $deduplicated = $this->deduplicator->deduplicate($expanded);

            $reranked = $this->reranker->rerank(
                $resolved->resolved,
                $deduplicated,
                $profile->rerankLimit
            );

            $coverage = $this->coverageEvaluator->evaluate(
                $expectedFacets,
                collect($reranked)
            );
        }

        $contextPack = $this->contextPackBuilder->build(
            resolvedQuery: $resolved,
            candidates: $reranked,
            coverage: $coverage,
            profile: $profile,
        );

        $this->persistConversationState(
            request: $request,
            resolved: $resolved,
            contextPack: $contextPack,
        );

        $this->traceRecorder->record(
            request: $request,
            resolved: $resolved,
            profile: $profile,
            queries: $queries,
            sections: $sections,
            candidates: $reranked,
            coverage: $coverage,
            secondPassUsed: $secondPassUsed,
            timings: $timer->all(),
        );

        return $contextPack;
    }
}
```

## 22. Caching Strategy

### 22.1 Cache layers

Cache:

- query embeddings;
- section retrieval;
- query expansions;
- hybrid retrieval results;
- reranker output;
- document section summaries.

### 22.2 Keys

Include:

- normalized query;
- document IDs;
- document versions;
- retrieval profile;
- embedding model version;
- reranker version.

```php
$cacheKey = implode(':', [
    'rag',
    'retrieval',
    hash('xxh3', $normalizedQuery),
    hash('xxh3', implode(',', $documentIds)),
    hash('xxh3', implode(',', $documentVersions)),
    $profile->name,
    $embeddingModelVersion,
    $rerankerVersion,
]);
```

### 22.3 Suggested TTLs

```text
Query embeddings: 30 days
Query expansions: 7 days
Section retrieval: 24 hours
Chunk retrieval: 1-24 hours
Reranker output: 1-24 hours
Document summaries: until document version changes
```

### 22.4 Invalidation

Increment document version after reprocessing.

Do not rely only on TTL expiration.

## 23. Latency Budgets

Set explicit stage budgets.

Suggested server-side targets:

```text
Context resolution:      5-40 ms
Profile routing:         under 5 ms
Section retrieval:       20-100 ms
Query expansion:         0-150 ms
Parallel hybrid search:  40-250 ms
Fusion/deduplication:    5-40 ms
Structural expansion:    5-50 ms
Reranking:               50-300 ms
Coverage evaluation:     5-50 ms
Second pass:             0-250 ms
Prompt construction:     5-30 ms
```

Target retrieval latency:

```text
Fast path:      under 350 ms
Standard path:  under 700 ms
Deep path:      under 1,200 ms
```

These are engineering targets, not guarantees.

## 24. Failure Handling

### 24.1 Embedding service failure

Fallback to:

- keyword search;
- heading search;
- active section context;
- deterministic neighbor expansion.

### 24.2 Full-text search failure

Fallback to:

- vector search;
- section summary search;
- heading metadata.

### 24.3 Reranker timeout

Use RRF ranking plus diversity selection.

### 24.4 Query expansion failure

Use:

- original resolved query;
- domain dictionary variants;
- active section titles.

### 24.5 No results

Return a structured no-evidence response:

```json
{
  "status": "no_evidence",
  "message": "No relevant content was found in the selected documents.",
  "suggested_action": "Broaden document selection or refine the question."
}
```

Do not fabricate an answer.

### 24.6 Partial coverage

The generator should state which requested areas were not found.

## 25. Security and Access Control

Every retrieval query must enforce:

- user permissions;
- tenant boundaries;
- document visibility;
- role restrictions;
- soft-deletion status;
- data classification rules.

Never retrieve first and filter later.

Apply access constraints inside the database or retrieval engine query.

Example:

```sql
WHERE tenant_id = :tenant_id
  AND document_id = ANY(:authorized_document_ids)
```

Do not store sensitive raw prompts in logs unless policy permits it.

## 26. Observability

Track per request:

- original query;
- resolved query;
- retrieval profile;
- selected documents;
- expanded queries;
- selected sections;
- candidate counts;
- reranker latency;
- coverage score;
- second-pass use;
- context token count;
- time to first token;
- total response latency;
- citation count;
- user feedback.

Add dashboards for:

- p50, p95, and p99 retrieval latency;
- no-result rate;
- low-coverage rate;
- second-pass rate;
- title-slide-only failure rate;
- answer citation rate;
- cache hit rate;
- reranker failure rate.

## 27. Evaluation Dataset

Create a versioned test dataset.

Each test case should include:

```json
{
  "id": "preterm-care-001",
  "conversation_context": {
    "active_document_ids": [22],
    "active_topic": "preterm infant care"
  },
  "query": "Tell me more about care of preterms",
  "expected_profile": "deep",
  "expected_sections": [
    "Care of the Preterm Infant",
    "Feeding",
    "Thermal Care"
  ],
  "expected_facets": [
    "feeding",
    "thermal care",
    "respiratory support",
    "infection prevention"
  ],
  "forbidden_behavior": [
    "return only title slide",
    "answer without citations"
  ]
}
```

Include cases for:

- exact factual questions;
- broad explanations;
- follow-up questions;
- ambiguous pronouns;
- title slides;
- tables;
- cross-document questions;
- irrelevant documents;
- conflicting document versions;
- no-evidence questions;
- access-control boundaries.

## 28. Retrieval Quality Metrics

Measure:

### 28.1 Recall at K

Did the candidate set contain the required evidence?

### 28.2 Section recall

Did retrieval include the expected section?

### 28.3 Facet coverage

How many expected facets were represented?

### 28.4 Context precision

What portion of selected chunks was actually relevant?

### 28.5 Citation correctness

Does each citation support the claim?

### 28.6 Faithfulness

Does the answer contain unsupported claims?

### 28.7 Latency

Record:

- retrieval latency;
- generation latency;
- total latency.

## 29. Automated Tests

### 29.1 Unit tests

Test:

- query classification;
- context resolution;
- synonym expansion;
- RRF;
- structural expansion;
- deduplication;
- coverage scoring;
- cache keys;
- access-control filtering.

Example:

```php
it('routes broad care questions to deep retrieval', function () {
    $query = new ResolvedQuery(
        original: 'Tell me more about care of preterms',
        resolved: 'Tell me more about care of preterm infants',
        primaryTopic: 'preterm infant care',
        entities: [],
        documentIds: [22],
        preferredSectionIds: [],
        intent: 'broad_explanation',
        confidence: 0.95,
    );

    $profile = app(RetrievalProfileRouter::class)->route($query);

    expect($profile->name)->toBe('deep');
});
```

```php
it('expands title slides into following body chunks', function () {
    $title = DocumentChunk::factory()->create([
        'content_type' => 'title_slide',
        'chunk_order' => 10,
    ]);

    DocumentChunk::factory()->count(5)->sequence(
        fn ($sequence) => [
            'document_id' => $title->document_id,
            'chunk_order' => 11 + $sequence->index,
            'content_type' => 'slide_body',
        ]
    )->create();

    $expanded = app(StructuralExpander::class)->expand(
        candidates: [['chunk_id' => $title->id]],
        profile: RetrievalProfiles::deep(),
    );

    expect(collect($expanded)->pluck('chunk_order'))
        ->toContain(10, 11, 12, 13, 14, 15);
});
```

### 29.2 Integration tests

Test against a real parsed document and retrieval indexes.

### 29.3 Regression test

Create a mandatory regression test for the original failure:

```text
Query: Tell me more about care of preterms
Expected: multiple body chunks and multiple care facets
Failure: title slide is the only selected evidence
```

## 30. Rollout Plan

### Phase 1: Instrument current pipeline

Implement:

- retrieval traces;
- latency logging;
- result inspection;
- title-slide detection.

Do not change behavior yet.

### Phase 2: Structural metadata

Implement:

- section extraction;
- chunk order;
- content types;
- neighbor links;
- section summaries.

Reprocess documents.

### Phase 3: Hybrid retrieval

Implement:

- vector search;
- full-text search;
- heading search;
- RRF.

### Phase 4: Adaptive routing

Implement:

- fast, standard, and deep profiles;
- deterministic router;
- latency budgets.

### Phase 5: Structural expansion

Implement:

- title-slide expansion;
- section-heading expansion;
- neighbor retrieval;
- diversity selection.

### Phase 6: Coverage and second pass

Implement:

- expected facets;
- coverage evaluation;
- one targeted recovery pass.

### Phase 7: Reranking and caching

Add:

- lightweight reranker;
- caching;
- graceful degradation.

### Phase 8: Evaluation and tuning

Tune thresholds using the evaluation dataset.

## 31. Configuration

Create a configuration file.

```php
return [
    'profiles' => [
        'fast' => [
            'query_expansion_limit' => 1,
            'vector_limit' => 8,
            'keyword_limit' => 8,
            'section_limit' => 2,
            'rerank_limit' => 8,
            'final_chunk_limit' => 4,
            'neighbor_before' => 0,
            'neighbor_after' => 1,
            'allow_second_pass' => false,
            'max_context_tokens' => 3000,
            'latency_budget_ms' => 350,
        ],

        'standard' => [
            'query_expansion_limit' => 2,
            'vector_limit' => 12,
            'keyword_limit' => 12,
            'section_limit' => 3,
            'rerank_limit' => 14,
            'final_chunk_limit' => 7,
            'neighbor_before' => 1,
            'neighbor_after' => 2,
            'allow_second_pass' => false,
            'max_context_tokens' => 5000,
            'latency_budget_ms' => 700,
        ],

        'deep' => [
            'query_expansion_limit' => 4,
            'vector_limit' => 18,
            'keyword_limit' => 18,
            'section_limit' => 5,
            'rerank_limit' => 20,
            'final_chunk_limit' => 10,
            'neighbor_before' => 1,
            'neighbor_after' => 4,
            'allow_second_pass' => true,
            'max_context_tokens' => 8000,
            'latency_budget_ms' => 1200,
        ],
    ],

    'coverage' => [
        'minimum_score' => 0.70,
        'minimum_unique_sections' => 2,
    ],

    'deduplication' => [
        'near_duplicate_similarity' => 0.92,
    ],

    'fusion' => [
        'rrf_k' => 60,
    ],

    'timeouts_ms' => [
        'query_expansion' => 150,
        'vector_search' => 250,
        'keyword_search' => 250,
        'reranker' => 300,
    ],
];
```

## 32. Codex Implementation Instructions

Codex must follow these rules while modifying the codebase.

1. Inspect the existing architecture before creating new abstractions.
2. Reuse existing models, services, repositories, queues, and configuration conventions.
3. Do not create duplicate retrieval pipelines.
4. Make each retrieval stage replaceable through interfaces.
5. Keep orchestration separate from database queries.
6. Add migrations without destroying existing document data.
7. Make reprocessing idempotent.
8. Add feature flags for staged rollout.
9. Preserve the current API contract unless explicitly required.
10. Add tests for every retrieval stage.
11. Add structured logs and trace IDs.
12. Avoid unbounded loops.
13. Enforce authorization at retrieval time.
14. Fail gracefully when optional services are unavailable.
15. Document configuration values and environment variables.
16. Do not hardcode model names, vector dimensions, or latency thresholds.
17. Do not silently catch retrieval errors; log them with stage context.
18. Ensure all model responses used in retrieval are schema-validated.
19. Keep prompts versioned.
20. Add a regression test for title-slide-only retrieval.

## 33. Suggested Service Interfaces

```php
interface QueryExpansionService
{
    public function expand(
        ResolvedQuery $query,
        array $sectionCandidates,
        RetrievalProfile $profile
    ): array;
}
```

```php
interface SectionRetriever
{
    public function retrieve(
        ResolvedQuery $query,
        RetrievalProfile $profile
    ): array;
}
```

```php
interface HybridRetriever
{
    public function retrieveAll(
        ResolvedQuery $resolvedQuery,
        array $expandedQueries,
        array $sectionCandidates,
        RetrievalProfile $profile
    ): array;

    public function retrieveMissingFacets(
        ResolvedQuery $resolvedQuery,
        array $missingFacets,
        array $sectionCandidates,
        RetrievalProfile $profile
    ): array;
}
```

```php
interface CandidateReranker
{
    public function rerank(
        string $query,
        array $candidates,
        int $limit
    ): array;
}
```

```php
interface StructuralExpander
{
    public function expand(
        array $candidates,
        RetrievalProfile $profile
    ): array;
}
```

## 34. Feature Flags

Use feature flags:

```text
rag.multi_stage.enabled
rag.section_retrieval.enabled
rag.query_expansion.enabled
rag.structural_expansion.enabled
rag.coverage_check.enabled
rag.second_pass.enabled
rag.reranker.enabled
rag.trace_logging.enabled
```

This allows gradual activation and quick rollback.

## 35. Acceptance Criteria

The implementation is complete when:

1. Broad questions are routed to deep retrieval.
2. Narrow factual questions remain fast.
3. Follow-up questions use compact conversation state.
4. Search combines vector, keyword, heading, and section signals.
5. Retrieval searches are parallel where possible.
6. Title slides automatically expand into useful body content.
7. Deep retrieval includes diverse sections or facets.
8. A second pass runs only when coverage is insufficient.
9. Retrieval loops are bounded.
10. Answers contain stable citations.
11. Strict document mode does not invent unsupported information.
12. Access controls are enforced in retrieval queries.
13. Each stage records latency.
14. The original “care of preterms” failure has a passing regression test.
15. p95 retrieval latency stays within the agreed profile budgets.

## 36. Required Deliverables from Codex

Codex should produce:

1. database migrations;
2. updated document ingestion pipeline;
3. document and section models;
4. retrieval DTOs;
5. conversation context resolver;
6. retrieval profile router;
7. query expansion service;
8. section retriever;
9. hybrid retriever;
10. RRF fusion service;
11. structural expansion service;
12. deduplication and diversity service;
13. reranker adapter;
14. coverage evaluator;
15. context pack builder;
16. orchestrator;
17. trace recorder;
18. configuration;
19. feature flags;
20. unit, integration, and regression tests;
21. operational documentation;
22. a migration/reprocessing command for existing documents.

## 37. Final Operational Rule

The system should behave according to this principle:

```text
Retrieve cheaply and broadly enough to avoid missing the answer.
Use expensive precision only on a small candidate set.
Expand document structure deterministically.
Perform no more than one targeted recovery pass.
Generate only from evidence the user is authorized to access.
```
