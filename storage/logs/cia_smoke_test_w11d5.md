# CIA Career Intelligence — Week 11 Day 5 Smoke Test Report

## Result

**Release gate: BLOCKED.** The seven requested HTTP smoke tests could not be
executed. This checkout contains Laravel route/controller source files but no
`artisan` entry point or `composer.json`, so there is no bootable application
or HTTP kernel in this workspace. The requested `/cia/...` URLs also do not
match the registered routes, and two requested capabilities have no route.
No endpoint is reported as passing.

## Environment

| Item | Observed |
| --- | --- |
| Test timestamp | 2026-11-13T21:58:31.401+05:30 (workspace-provided; no HTTP request was made) |
| PHP CLI | 8.2.12 |
| Laravel version | Not determinable: no `composer.json`, installed dependency manifest, or `artisan` |
| Python environment | `.venv\Scripts\python.exe` exists; interpreter version/dependencies were not queried |
| ChromaDB | `storage\chroma_db` exists; no ChromaDB health/query check was run |
| Application boot | Unavailable: `artisan` is absent |
| Staging target | Not configured or deployed by this run |

## Endpoint results

Latency is not measured because no HTTP request could be dispatched. “Expected
200” is the requested valid-payload success condition, not an observed result.

| Module | Requested endpoint | Method | Expected | Actual | Average latency | Telemetry verification |
| --- | --- | --- | --- | --- | --- | --- |
| 1 — Career dashboard | `/cia/dashboard` | GET | 200 OK | Not dispatched; route is instead `GET /cia/career/dashboard` | N/A | Not verified |
| 2 — Skill gap | `/cia/skill-gap` | POST | 200 OK | Not dispatched; route is instead `POST /candidate/cia/career/skill-gap` | N/A | Not verified |
| 3 — Recommendations | `/cia/recommendations` | POST | 200 OK | Not dispatched; route is instead `POST /candidate/cia/career/recommendations` | N/A | Not verified |
| 4 — Learning path | `/cia/learning-path` | POST | 200 OK | Not dispatched; route is instead `POST /candidate/cia/career/learning-path` | N/A | Not verified |
| 5 — Vector search | `/cia/vector-search` | POST | 200 OK | Not dispatched; route is instead `POST /candidate/cia/career/vector-search` | N/A | Not verified |
| 6 — Provider failover | `/cia/failover-test` or service execution | POST / internal | 200 OK | No failover-test route; helper script is called from skill-gap analysis only | N/A | Not verified |
| 7 — Telemetry / search history | `/cia/telemetry` or `/cia/search-history` | GET | 200 OK | Neither route is registered; `searchHistory` has no route binding | N/A | Not verified |

Every listed route is protected by `auth`. The dashboard route currently
returns a readiness placeholder rather than dashboard metrics. All statuses
above are deliberately “not dispatched,” not inferred 404s or successful
responses.

## Verification commands and outcomes

| Check | Outcome |
| --- | --- |
| `php -l app\Http\Controllers\CiaController.php` | Passed — no syntax errors |
| `php -l routes\web.php` | Passed — no syntax errors |
| `php artisan config:clear`, `cache:clear`, `route:clear`, `view:clear` | Not runnable — `Could not open input file: artisan` |
| `composer dump-autoload` | Not runnable — Composer reports no `composer.json` |
| `php artisan optimize` | Not runnable — `artisan` is absent |
| `php artisan route:list --path=cia` | Not runnable — `artisan` is absent |
| `php artisan test` | Not runnable — `artisan` is absent |
| `storage\logs\laravel.log` | Missing |
| `storage\logs\cia_monitoring.log` | Missing |
| `storage\logs\CIA_CAREER_TELEMETRY` channel | Not found in the inspected controller/routes; no log configuration is available in this checkout |

Consequently, autoloader generation, framework optimization, route boot,
application log cleanliness, and endpoint response/latency/telemetry checks
are **not verified**. No “zero log errors” claim is made.

## Code and error-handling audit

- Both requested PHP files pass syntax lint.
- `validateRequest` converts `ValidationException` to a structured JSON `422`.
- The controller catches process-start, process-timeout, and general process
  failures for its Python subprocess operations, returning controlled JSON
  errors (including `502` and `504`). The provider helper attempts Groq and then
  OpenRouter; this integration was not exercised against live providers.
- There are no explicit `QueryException` handlers around the Eloquent reads,
  search-history query, or search-log inserts. Database errors therefore
  depend on the absent Laravel application's global exception configuration.
- No `throttle` middleware or `429` mapping is registered in `routes/web.php`.
- `CIA_CAREER_TELEMETRY` is not configured in the inspected source. Some
  operations insert request/result payloads into `cia_search_logs`, while the
  provider helper emits telemetry that the skill-gap controller does not
  persist as a monitoring event. Telemetry completeness and privacy controls
  are not verified.
- The standalone failover smoke endpoint and search-history/telemetry route
  are absent; several requested URLs differ from the actual route paths.

## Release decision

Do not promote this checkout to staging or describe the seven endpoints as
passing. Restore a complete Laravel application and its dependency manifest,
register/align the endpoint routes, implement the missing monitoring and
search-history bindings, and exercise the endpoints with an authenticated
test identity and controlled dependencies before approving deployment.
