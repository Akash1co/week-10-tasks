# CIA Career Intelligence — Implementation Changelog

This is a repository-state changelog, not a claim about deployment history.
Entries describe capabilities observable in the current source snapshot.

## Current snapshot

- Added four authenticated controller routes for skill-gap analysis, career
  recommendations, learning-path generation, and semantic role search.
- Added Symfony Process boundaries with configured timeouts, JSON parsing and
  response-shape validation, and controlled error responses.
- Added `cia_search_logs` storage plus candidate/time and candidate/role
  indexes. The included audit notes that live MySQL `EXPLAIN` was not run.
- Added a ChromaDB collection helper and 20 role profiles; vector search
  returns the five highest-ranked matches and missing required skills.
- Added structured CrewAI career recommendations and an eight-week,
  56-day LangGraph learning plan with deterministic node-level fallback.
- Added a Groq-primary, OpenRouter-secondary script for skill-gap LLM
  analysis. If both provider calls fail, it returns an error; it does not
  synthesize a successful provider response.
- Retained authenticated dashboard, profile, profile-recommendation, and
  analytics endpoints as placeholder closures. These are not persisted
  profile or analytics implementations.
- Added API contract documentation and Python tests for vector search,
  learning-path validation, and performance/failover scenarios.

## Verification caveats

The source snapshot does not include the Laravel `artisan` entry point or
Composer manifest. The documented routes therefore cannot be booted as an
HTTP application from this directory alone. Performance figures in
`storage/logs/cia_performance_audit_w11d1.md` are historical local test
observations; the audit explicitly says MySQL query-plan measurements were
not captured. They are not production or end-to-end latency claims.
