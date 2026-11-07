# CIA Career Intelligence — Week 10 Day 5 Integration Test Log

## Execution Summary

- **Date:** November 2026
- **Status:** PASSED
- **Coverage:** 7/7 Modules
- **Automated test result:** 12 Python unit/integration tests passed.
- **PHP syntax result:** No syntax errors in the CIA controller, added services, or web routes.
- **External provider calls:** None; timeout and HTTP 5xx failover cases were simulated.
- **Laravel HTTP runtime:** Not run. This checkout contains no Composer manifest or Laravel test runner, so route and middleware coverage was verified from the registered route definitions and controller actions.

## Root Cause & Verification Matrix

| Module | Root cause | Hardening and verification | Result |
|---|---|---|---|
| 1. Resume ATS Scorer | PDF files can be structurally valid but yield an empty text buffer; unreadable PDF data must not be treated as a successful zero-score result. | PDFium extraction now closes document resources, converts PDFium failures to a clear validation error, and rejects empty extracted text. The suite exercises both an empty text buffer and extraction of a generated PDF. The authenticated `POST /candidate/cia/career/ats-score` route validates a PDF upload and returns structured `422`, `502`, or `504` failures. | Passed |
| 2. Readiness Score Engine | Candidates with no completed assessments have no measured quiz average; dividing an empty score set or by a zero benchmark would fail or produce an invalid score. | Empty score arrays return zero, zero benchmarks are handled explicitly, and fresh profiles use a zero quiz average. The readiness endpoint is registered with authentication and shares the tested skill-gap score calculation. | Passed |
| 3. Career Intelligence Report | Reports need stable output when an LLM is unavailable, scores across a fixed archetype set, and bounded printable output. | The report generator scores six archetypes, falls back to a deterministic summary, and creates a valid one-page PDF with wrapped text. A test decodes and extracts text from the generated PDF. **Dompdf is not installed and no Composer manifest is present; Dompdf rendering and CSS print-overflow behavior therefore were not claimed or exercised.** The implemented PDF path does not use HTML/CSS layout. | Passed with renderer deviation documented |
| 4. ChromaDB Vector Matcher | A fresh or partially seeded persistent collection cannot serve complete role matches; unbounded or non-finite cosine distances can produce invalid percentages. | The 20-role dataset is validated, missing IDs are upserted into `storage/chroma_db`, and cosine similarity is clamped to 0–100. The test verifies count, closes/reopens the persistent client, invokes the CLI with the requested PHP/Laravel skill list, and checks all five match cards and their skill-gap arrays. | Passed |
| 5. Skill Gap Analyzer | Case differences and repeated whitespace can make equivalent candidate and required skills appear to be separate values. | Candidate skills, target roles, and required skills are normalized before comparison. The PHP service-level test supplies lower-case and whitespace-variant skills and verifies all five expected skills match with no false gaps. Existing score and radar fields are preserved. | Passed |
| 6. Learning Path Generator | LLM output can be malformed or omit weeks/days, so an unvalidated model response must not be returned as a successful plan. | Existing Pydantic schemas enforce the structured response and the controller checks for eight weeks, seven days per week, a Mermaid string, and validation metadata. The existing suite verifies fallback generation, structured calls, and rejection of an invalid plan. | Passed |
| 7. Report History & PDF Download | A report identifier alone is not authorization; an authenticated user must not retrieve another candidate's PDF or a path outside their private report directory. | Report history filters by authenticated candidate ID. Download queries by both report ID and candidate ID, checks the candidate-hashed storage prefix, and returns the same `404` for inaccessible records. Route/middleware and ownership-guard source assertions pass. | Passed |

## Endpoint Coverage

All requested authenticated routes are registered and mapped to controller actions:

1. `POST /candidate/cia/career/ats-score`
2. `POST /candidate/cia/career/readiness-scores`
3. `POST /candidate/cia/career/generate-report`
4. `POST /candidate/cia/career/match-internships`
5. `POST /candidate/cia/career/skill-gap`
6. `POST /candidate/cia/career/learning-path`
7. `GET /candidate/cia/career/reports`
8. `GET /candidate/cia/career/reports/{id}/download`

The report-history and PDF-download routes together form Module 7. All eight routes have `auth` middleware.

## Test Evidence

Executed with the workspace Python interpreter:

```text
python -m unittest discover -s scripts/tests -p "test_*.py" -v
Ran 12 tests
OK
```

PHP syntax checks:

```text
php -l app/Http/Controllers/CiaController.php
php -l app/Services/CiaCareerService.php
php -l app/Services/CiaServiceException.php
php -l routes/web.php
```

All four PHP syntax checks reported no errors. Provider failover tests simulate Groq timeout and HTTP 503 failures and verify that OpenRouter is selected; they do not call either provider.
