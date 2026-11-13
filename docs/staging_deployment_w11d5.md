# Project 4 — CIA Career Intelligence (`cynaris-portal`)
## Week 11 Staging Release Review

**Release state: NOT APPROVED — staging deployment was not performed.**

This review describes the implementation visible in the Week 11 Day 5
workspace and the deployment procedure for a complete application checkout.
It does not certify the requested seven endpoints: the current checkout lacks
the Laravel runtime entry point and dependency manifest, the requested paths
do not match the defined routes, and not all requested modules are exposed as
routes.

## 1. Release overview

- **Project:** Project 4 — CIA Career Intelligence (`cynaris-portal`)
- **Release:** Week 11 staging candidate
- **Branch:** `feat/cia-career-w11d5-akasha`
- **Disposition:** Blocked pending restoration of a deployable Laravel
  application, route/telemetry completion, and authenticated smoke tests.
- **Changes in this release review:** smoke-test evidence and deployment
  runbook. No application code was changed because the missing application
  runtime prevents verifying any route or persistence behavior end to end.

## 2. Module implementation and architecture

The controller-backed flow, where present, is Laravel HTTP validation and
authentication → controller → Eloquent/MySQL reads and/or a Python subprocess
→ optional ChromaDB or external LLM provider → JSON response. This is not a
verified end-to-end staging architecture; database, external-service,
configuration, and HTTP behavior remain untested in this checkout.

| Module | Current implementation | Data flow and edge cases | Release status |
| --- | --- | --- | --- |
| 1. Career dashboard | Authenticated `GET /cia/career/dashboard` closure returns a readiness message only. | No metrics query, dashboard schema, or telemetry is implemented on this route. | Placeholder; not a dashboard |
| 2. Skill gap analysis | `POST /candidate/cia/career/skill-gap` invokes `CiaController::skillGap`. | Reads completed lessons and quiz scores through Eloquent; derives benchmark/radar data; calls the Python LLM failover helper when configured; uses local benchmark output if neither provider key is configured. Validation returns JSON `422`; process/provider failures can return `502`/`504`. Eloquent `QueryException` is not explicitly handled. | Implemented in source; HTTP/database/provider behavior not run |
| 3. AI career recommendations | `POST /candidate/cia/career/recommendations` invokes `careerRecommendations`. | Validates role, skills, skill gaps, and optional profile; starts `scripts/career_recommendations.py` (CrewAI); validates a three-path JSON result; records a search log. Start, timeout, nonzero exit, invalid JSON, and invalid result-shape cases have controlled responses. | Implemented in source; dependency/LLM/database behavior not run |
| 4. Personalized learning path | `POST /candidate/cia/career/learning-path` invokes `learningPath`. | Validates request bounds; starts `scripts/langgraph_learning_path.py`; the graph builds and validates an eight-week, seven-day-per-week schedule, with deterministic per-node fallback when its Groq call or structured response fails; records a search log. Subprocess and output-shape errors are mapped to controlled responses. | Implemented in source; Python/LLM/database behavior not run |
| 5. Semantic vector search | `POST /candidate/cia/career/vector-search` invokes `vectorSearch`. | Validates one through 50 non-empty skills; starts `scripts/vector_search_roles.py`; queries ChromaDB and expects exactly five matches from a collection seeded with exactly 20 role profiles; records the result. Missing/invalid collection and subprocess failures are not live-tested. | Implemented in source; ChromaDB health/index not verified |
| 6. Dual-provider LLM failover | `scripts/llm_provider_fallback.py` calls Groq and attempts OpenRouter after provider failure. Skill-gap analysis invokes it. | Provider exceptions are converted to controlled JSON by the helper. There is no dedicated `/cia/failover-test` route. Live credentials and real provider requests were not used. | Internal service only; no standalone endpoint |
| 7. Telemetry and search history | Controller has a `searchHistory` method; some operations write `cia_search_logs`. | The method filters by authenticated candidate and optional role, newest first, limit 50. It is not registered in `routes/web.php`. No `/cia/telemetry` route or configured `CIA_CAREER_TELEMETRY` logging channel was found. Persistence and data-retention/privacy settings are unverified. | Incomplete; no reachable history/telemetry endpoint |

### Error-handling and data review

- `ValidationException` is converted to a structured JSON `422`.
- Python process startup, timeout, nonzero exit, invalid JSON, and output-shape
  failures have specific controller handling. Timeouts currently map to `504`,
  while unavailable services generally map to `502`.
- There are no explicit `QueryException` handlers around controller database
  reads or writes. Global handling cannot be inspected because the Laravel
  application configuration is missing.
- No route-level throttling or explicit `429` handling was found.
- Search-log persistence includes request/result payloads and a candidate
  identifier. Confirm field minimization, access control, and retention
  requirements before production use.
- Laravel/provider logs, production debug settings, and API error serialization
  could not be inspected or verified here.

## 3. Endpoint directory

All routes below are declared with `auth` middleware, either directly or via
the `cia/career` route group. Methods and paths are from `routes/web.php`;
they are not confirmed by `route:list` because the Laravel runtime is absent.

| Implemented route | Method | Request / validation | Response contract / state |
| --- | --- | --- | --- |
| `/cia/career/dashboard` | GET | No body | `200` readiness placeholder: `{"message":"Career intelligence dashboard endpoint is ready."}`; does not return the requested dashboard metrics |
| `/candidate/cia/career/skill-gap` | POST | Required `target_role`: string, max 120 | `200`: `score`, `gaps`, `priority_learning`, `radar_data`; validation `422`; process/provider failures `502`/`504` |
| `/candidate/cia/career/recommendations` | POST | Required `target_role` string max 120; optional `skills` and `skill_gaps` arrays max 50 with string elements max 120; optional `profile` array | `200`: `log_id`, exactly three `career_paths`; validation `422`; process/invalid output failures `502`/`504` |
| `/candidate/cia/career/learning-path` | POST | Required nonblank `target_role` string max 120; optional `skills`/`skill_gaps` arrays max 50 with nonblank string elements max 120; optional `experience_level` string max 80; optional `weekly_hours` integer 1–40; optional `profile` array max 50 | `200`: `log_id`, summary fields, 8-week `schedule`, Mermaid chart, and `validation`; validation `422`; process/invalid output failures `502`/`504` |
| `/candidate/cia/career/vector-search` | POST | Required `skills` array with 1–50 nonblank strings max 120 | `200`: `log_id` and five `matches`; validation `422`; process/invalid output failures `502`/`504` |
| `/cia/career/profiles/{candidate_id}` | GET | Required path identifier | Placeholder message and identifier; not a persisted profile response |
| `/cia/career/profiles` | POST | No controller validation is defined in this route closure | Placeholder `201` message; no profile is persisted |
| `/cia/career/profiles/{candidate_id}/skills` | PUT | No controller validation is defined in this route closure | Placeholder message and identifier; no skills are persisted |
| `/cia/career/recommendations/{candidate_id}` | GET | Required path identifier | Placeholder response with an empty `recommendations` array |
| `/cia/career/analytics` | GET | No body | Placeholder readiness message; no analytics schema/data |

### Requested routes not registered

The requested `/cia/dashboard`, `/cia/skill-gap`, `/cia/recommendations`,
`/cia/learning-path`, `/cia/vector-search`, `/cia/failover-test`,
`/cia/telemetry`, and `/cia/search-history` paths do not match the controller
route paths above. There is no dedicated failover-test route, and the
`searchHistory` method is not bound to a route. Align the public API contract
and route registrations before publishing endpoint URLs.

## 4. Verification evidence and deployment gate

Verified in the available workspace:

- PHP 8.2.12 is available.
- PHP syntax lint passed for `app/Http/Controllers/CiaController.php` and
  `routes/web.php`.
- A `.venv\Scripts\python.exe` file and `storage\chroma_db` directory exist;
  neither the interpreter dependencies nor ChromaDB service was exercised.

Not verified:

- Laravel version, Composer autoloading, Laravel optimization, or route cache.
- Database connectivity, migrations, authenticated HTTP responses, response
  latency, LLM provider behavior, vector-search results, or telemetry writes.
- `storage/logs/laravel.log` and `storage/logs/cia_monitoring.log`; both are
  absent from this workspace.
- Seven endpoint smoke tests and staging deployment.

This checkout has no root `artisan` or `composer.json`. `php artisan` checks
therefore stop with “Could not open input file: artisan”; `composer
dump-autoload` stops because Composer cannot find `composer.json`. Do not
interpret these as successful optimization or clean-log checks.

## 5. Staging deployment runbook

Run only after the complete Laravel application is available and the release
blockers above have been resolved. Use the actual deployment host, environment
configuration, and approved release branch; do not run migrations against
production as part of this staging procedure.

### Preconditions

1. Confirm a deployable checkout includes `artisan`, `composer.json`,
   `composer.lock`, application configuration, and the expected CIA routes.
2. Provision the supported PHP extensions, Python environment and project
   dependencies, MySQL schema, persistent ChromaDB directory, queue/cache
   services if used, and writable Laravel storage/cache directories.
3. Configure secrets through the staging secret store (not Git), including
   database credentials and only the provider keys needed for the test.
4. Back up staging data and confirm the current release identifier and
   rollback procedure. Review each migration for backward compatibility.

### Deploy

```powershell
git fetch origin
git pull --ff-only origin feat/cia-career-w11d5-akasha
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan route:list --path=cia
```

If staging must run with a pre-built release artifact rather than branch pull,
deploy that immutable artifact instead. Configure `.env` and persistent
storage outside the source tree according to the host's deployment policy.
Seed/validate the ChromaDB role collection through the approved deployment
procedure before enabling vector search.

### Smoke and acceptance checks

1. Authenticate with a staging-only test candidate; do not use real candidate
   data.
2. Verify registered route methods/paths and authentication behavior.
3. Submit valid and invalid payloads for each implemented route and check
   JSON schema, expected success and validation/error status, candidate-scoped
   persistence, and absence of stack traces or secrets.
4. Exercise primary-provider success, primary failure with secondary-provider
   success, dual-provider failure, Python timeout/start failure, missing
   ChromaDB data, and database failure with controlled test doubles.
5. Confirm monitoring events, log redaction/retention, response latencies, and
   clean application logs before approving the release.

Local demo URL, after booting a complete Laravel application:

```text
http://127.0.0.1:8000/cia/career/dashboard
```

The requested `http://127.0.0.1:8000/cia/dashboard` is not registered by the
current routes. Staging demo URLs must be taken from the deployed environment;
none is configured in this workspace.

### Rollback

1. Stop or disable traffic to the new release and redeploy the last known-good
   immutable application artifact.
2. Restore the pre-deployment database backup only if a migration/data change
   cannot safely coexist with the old application and the approved recovery
   plan permits that restore. A database restore can discard writes made
   since backup; obtain the data owner's approval first.
3. If a reviewed migration is explicitly reversible and rollback is approved,
   execute its documented rollback command for that migration only. Do not
   blindly roll back all migrations.
4. Re-run health checks and authenticated smoke tests against the previous
   release; retain deployment and rollback logs for the incident record.

## 6. Release checklist

- [ ] Restore complete Laravel application and dependency manifests.
- [ ] Register and reconcile requested endpoint paths/API documentation.
- [ ] Implement and test search-history and telemetry route/channel.
- [ ] Decide and test explicit database exception and rate-limit behavior.
- [ ] Run Composer autoload generation and Laravel optimization successfully.
- [ ] Run all seven authenticated HTTP smoke tests and verify telemetry.
- [ ] Review logs for sensitive-data exposure and verify staging rollback.
- [ ] Obtain release approval before staging promotion.
