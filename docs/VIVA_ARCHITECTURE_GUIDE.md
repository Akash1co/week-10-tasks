# CIA Career Intelligence — Viva Architecture Guide

## System flow

1. A signed-in candidate uses the Blade career view. Its forms send JSON to
   authenticated `POST /candidate/cia/career/*` routes and include the session
   CSRF token.
2. `CiaController` validates request shapes and limits, derives the candidate
   ID from the authenticated user, and either queries lesson/quiz records or
   invokes a Python service using Symfony Process.
3. PHP passes bounded JSON input, enforces per-service timeouts, checks exit
   status, decodes JSON with exceptions, validates essential response
   structure, logs search/generation results, and returns JSON.
4. Python services implement role recommendation (CrewAI), the
   five-node learning-path graph (LangGraph), LLM provider failover, and
   ChromaDB role search. The Blade view uses DOM text nodes for user-facing
   strings and Chart.js/Mermaid for charts.

This is the data flow of the CIA slice present in this repository. It is not
a complete deployment: the profile, dashboard, and analytics handlers in
`routes/web.php` are placeholders, and several requested capstone modules
have no implementation here.

## Responsibilities and current scope

| Area | Responsibility | Current boundary |
| --- | --- | --- |
| Blade career tab | Candidate forms and visual output | Four analysis forms; no curriculum tracker |
| Laravel controller | Authenticated input, DB reads, process boundary, response/logging | Skill-gap, recommendations, learning path, and vector search |
| LangGraph service | Skill assessment through validated 8-week plan | Five sequential nodes; deterministic per-node fallback |
| CrewAI service | Three structured career paths | Provider-backed; no offline fallback |
| ChromaDB scripts | Seed 20 roles and return five cosine-ranked matches | Role matching only; not a full RAG generation path |
| Remaining modules | Rubrics, Git simulator, analytics, portfolio export | Missing or placeholder-only as noted in the case study |

`docs/chromadb_schema.py` defines a separate candidate-profile collection
(`cynaris_intern_profiles`); the active role search uses
`cynaris_career_roles`. Do not assume those stores are interchangeable or
that candidate profiles are currently retrieved by role matching.

## Error handling and resilience

- Laravel returns `422` for request validation errors, `502` for subprocess,
  provider, malformed JSON, or invalid-shape failures, and `504` for process
  timeouts. Responses use controlled messages; details are logged server-side.
- Python subprocess output is treated as untrusted: callers check status,
  decode JSON strictly, and validate essential response shape before use.
- The Groq/OpenRouter adapter sets a 25-second provider timeout and reports a
  generic provider-unavailable message if both fail. Learning-path nodes
  report a fallback-node list and use deterministic values when model output
  cannot be used.
- ChromaDB storage, model downloads, database connections, and search-log
  writes remain dependencies. Vector search and CrewAI do not have the same
  deterministic fallback guarantee as LangGraph. A database/logging failure
  may still fail the request and needs operational monitoring.
- Operational logs may include exception details or subprocess stderr. Keep
  log access restricted; never place API keys or raw credentials in requests,
  responses, or logs.

## Security and authentication

The CIA web routes use Laravel `auth`; state-changing form calls use the
session CSRF token. Analysis candidate IDs come from the authenticated user,
not the request body. Provider keys are passed to child processes through
environment variables rather than command-line arguments. Keep keys in
deployment secret configuration. The profile/demo placeholder routes do not
yet implement profile ownership checks or admin authorization policies; do
not treat authentication alone as complete authorization for those future
features. No `.env.example` is present in this checkout.

## Testing and release caveats

Python `unittest` suites are present for LangGraph, vector search, fallback,
and performance. The current working-copy `test_llm_fallback.py` content
expects a `requests.post` CLI and `main(arguments)` signature, while the
current `llm_provider_fallback.py` uses `urllib`, reads JSON from stdin, and
defines `main()` without arguments; that test must be reconciled before
claiming fallback regression coverage. The performance test writes 1,000
documents into `storage/chroma_db`, so it should not be run against valuable
local data. This checkout also lacks `artisan`, `composer.json`, and
PHPUnit/vendor files, so the Laravel test/runtime path cannot be verified
from this directory alone.

## Likely viva questions

**“Walk me through your architecture.”**  
The browser sends authenticated, CSRF-protected requests to Laravel. Laravel
validates inputs, uses candidate-owned learning records where needed, and
calls isolated Python services through bounded subprocesses. Python owns
structured agent orchestration and vector role ranking; PHP validates the
result again before returning it to the UI.

**“How are errors handled across layers?”**  
Validate at the HTTP and Python schema boundaries, bound provider and process
waits, check subprocess exit status and JSON shape, return controlled HTTP
errors, and log diagnostic context server-side. LangGraph can fall back
deterministically; other integrations currently fail explicitly instead.

**“How do agents, retrieval, and persistence interact?”**  
LangGraph's nodes pass typed state and validate the generated plan. Separately,
ChromaDB stores role documents and ranks candidate skill queries. Laravel
persists request/result audit records in `cia_search_logs`. The current code
does not connect retrieved role passages to agent generation as a complete
RAG chain.

**“What would you improve next?”**  
First reconcile the fallback tests and establish a reproducible Laravel test
environment. Then implement rubric/milestone evaluation, a safe Git/PR
verification workflow, real role-scoped analytics, and portfolio export.
After that, add integration tests for provider timeouts, ChromaDB failures,
database logging failures, and per-candidate authorization.
