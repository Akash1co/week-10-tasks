# CIA Career Intelligence — API Performance Audit Log (Week 11, Day 1)

## 1. Executive Summary

- Date: November 2026
- Target Environment: Local / Cynaris Portal Development Runtime
- Core Objective: Query EXPLAIN profiling, Groq API timeout handling, and ChromaDB 1K doc load testing.
- Outcome: The ChromaDB benchmark and mocked provider failover tests passed. MySQL EXPLAIN could not be run in this workspace; post-index query plans and row-count reductions remain unverified.

## 2. MySQL EXPLAIN Query Audit & Index Optimization

The repository schema uses `candidate_id` in `cia_search_logs`; it does not define a `reports` table. The user-scoped career history audit therefore targets the existing search-log table rather than documenting a nonexistent table or column.

### Audit 1: Search History Query (`cia_search_logs`)

- SQL Query: `SELECT id, target_role, request_payload, results, created_at FROM cia_search_logs WHERE candidate_id = ? ORDER BY created_at DESC LIMIT 50;`
- EXPLAIN Before Indexing: Not captured; no live plan was recorded during this work, so the baseline access type and scanned-row count are unknown.
- Migration Fix: Added compound index `idx_user_created` on `(candidate_id, created_at)`.
- EXPLAIN After Indexing: Not run against a live server. Expected access is an indexed candidate range with chronological ordering; the actual plan and scanned-row reduction must be confirmed against the deployed MySQL version and data distribution.

### Audit 2: Target Role History Lookup (`cia_search_logs`)

- SQL Query: `SELECT * FROM cia_search_logs WHERE candidate_id = ? AND target_role = ?;`
- Migration Fix: Added nullable `target_role` for indexed role filtering and compound index `idx_user_role` on `(candidate_id, target_role)`.
- EXPLAIN After Indexing: Not captured; no actual `type`, `key`, or row-count measurements are claimed.

## 3. Groq LLM Provider Failover Audit

- Test Condition: The primary Groq call was mocked to raise a provider timeout.
- Behavior Verified: `scripts/llm_provider_fallback.py` caught the timeout, called the mocked OpenRouter provider, and returned a successful structured JSON response without a traceback on stdout.
- Production Integration: The skill-gap controller invokes the fallback script and maps provider timeout failures to HTTP 504 and external-service failures to HTTP 502.
- Failure Payload Handling: Controlled JSON responses include `status: error`, a user-facing `message`, and the corresponding HTTP status.
- External Calls: No live Groq or OpenRouter request was made during this audit.

## 4. ChromaDB 1,000-Document Load Benchmark

- Dataset Scale: 1,000 documents in the existing `internship_roles` collection (20 existing records plus 980 benchmark records).
- Peak Memory Allocation: 0.01 MB observed by `tracemalloc`.
- Query Response Latency: 0.0030 seconds observed; the test threshold is strictly under 2.0 seconds.
- Stability Verdict: PASSED.
- Note: The benchmark writes deterministic `w11d1-benchmark-*` records to `storage/chroma_db` when fewer than 1,000 documents exist.

## 5. Verification

- Performance suite: 2 tests passed in 0.241 seconds using the configured Python 3.13.7 environment.
- ChromaDB query metric: 0.0030 seconds; peak traced allocation 0.01 MB.
- PHP syntax checks: Passed for the controller, routes, and new index migration.
- MySQL migration/EXPLAIN validation: Not verified by this session; execute the migration and capture both plans against the application database before claiming scan reduction.
