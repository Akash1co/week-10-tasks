# CIA Career Intelligence — Production CI & Smoke Verification (Week 12, Day 1)

## Verification status

- Repository: standalone `week-10-tasks` CIA module checkout for `cynaris-portal`.
- Branch checked before work: `feat/cia-career-w12d1-akasha`.
- Local PHP syntax: **PASS** — `CiaController.php` and `routes/web.php` both report
  `No syntax errors detected`.
- Parent Laravel CI and production/staging HTTP smoke tests: **NOT RUN**. This
  checkout does not contain the parent portal's `artisan`, `composer.json`, or
  `composer.lock`, nor its application environment, database, or credentials.
  No runtime or live endpoint result is implied by the local syntax checks.
- Route-cache / `php artisan optimize` behavior: **NOT VERIFIED**. `routes/web.php`
  contains closure-based readiness routes; verify whether the parent's Laravel
  route cache can serialize them before treating optimize as a release pass.

## Production CI / deployment verification checklist

Run this exact sequence from the deployed `cynaris-portal` release directory
after selecting the approved production branch:

1. `git pull --ff-only origin <approved-release-branch>`
2. `composer dump-autoload`
3. `php artisan optimize`
4. Verify application logs for the CIA telemetry marker and deployment errors
   (for example, search the active `storage/logs/laravel*.log` files for
   `CIA_CAREER_TELEMETRY`, `QueryException`, and `ERROR`).

Before sign-off in the parent portal, also execute its configured CI suite,
confirm required Python packages and provider/vector-store configuration, and
run the authenticated smoke requests in the matrix below against staging and
then production. Capture actual HTTP status, response shape, release revision,
and log verification in the deployment record. Do not treat this checklist as
evidence that those production commands or requests ran.

Do not mark `php artisan optimize` complete if route caching fails on the
closure-based readiness routes. Resolve route caching against the parent
Laravel version before switching production traffic.

### Local verification evidence

| Check | Result |
| --- | --- |
| `php -l app/Http/Controllers/CiaController.php` | PASS — no syntax errors detected |
| `php -l routes/web.php` | PASS — no syntax errors detected |
| Laravel route-list / controller tests | Not runnable here — parent `artisan` and Composer manifest are absent |
| Production/staging HTTP requests | Not run — no deployed base URL or credentials supplied |

## CIA endpoint smoke matrix

All listed routes are protected by the `auth` middleware. For each request,
send `Accept: application/json` and use the application's configured session or
authorization scheme. The JSON shapes below describe the current checked-in
implementation; for placeholder routes the response is explicitly a readiness
message, not implemented candidate/profile/analytics data.

| Module | Registered route(s) and method | Request validation | Expected success response | Error coverage |
| --- | --- | --- | --- | --- |
| Skill-gap analysis | `POST /candidate/cia/career/skill-gap` (`cia.career.skill_gap`) | `target_role`: required string, max 120 | `200`: `{score, gaps, priority_learning, radar_data}` | `422` for invalid input; `500` for lesson/quiz database errors or input preparation; `502` for process/provider failures; `504` for process/provider timeout. DB details are not returned. |
| Career recommendations | `POST /candidate/cia/career/recommendations` (`cia.career.recommendations.generate`); `GET /cia/career/recommendations/{candidate_id}` (`cia.career.recommendations`) | POST: `target_role` required string max 120; optional `skills` and `skill_gaps` arrays (max 50, each string max 120); optional `profile` array. GET has no body validation. | POST `200`: `{log_id, career_paths}` with exactly 3 paths. GET placeholder `200`: `{message, candidate_id, recommendations: []}`. | POST: `422` validation; `500` for result-log persistence/input errors; `502` for process, invalid output, or provider failure; `504` for timeout. GET placeholder has no explicit validation, DB, or Python-service error path. |
| Learning paths | `POST /candidate/cia/career/learning-path` (`cia.career.learning_path`) | `target_role`: required non-whitespace string max 120; optional `skills`/`skill_gaps` arrays max 50 with non-whitespace strings max 120; optional `experience_level` string max 80; optional `weekly_hours` integer 1–40; optional `profile` array max 50 | `200`: `{log_id, title, summary, outcomes, schedule, mermaid, validation, fallback_nodes}`; schedule contains 8 weeks with 7 days each. | `422` validation; `500` for input preparation or result-log persistence; `502` for process/invalid output; `504` timeout. |
| Vector role search | `POST /candidate/cia/career/vector-search` (`cia.career.vector_search`) | `skills`: required array, 1–50 entries; each required non-whitespace string max 120 | `200`: `{log_id, matches}` with 5 ranked role matches | `422` validation; `500` for input preparation or result-log persistence; `502` for process/invalid output; `504` timeout. |
| Career dashboard | `GET /cia/career/dashboard` (`cia.career.dashboard`) | No request body or query validation | Placeholder `200`: `{message: "Career intelligence dashboard endpoint is ready."}` | No endpoint-specific `422`, `500`, or `502` handler is implemented. |
| Candidate profiles | `GET /cia/career/profiles/{candidate_id}` (`cia.career.profiles.show`); `POST /cia/career/profiles` (`cia.career.profiles.store`); `PUT /cia/career/profiles/{candidate_id}/skills` (`cia.career.profiles.update_skills`) | No request-body validation is implemented in these placeholder handlers. | GET/PUT placeholder `200`: `{message, candidate_id}`. POST placeholder `201 Created`: `{message}` (not `200`). | No endpoint-specific `422`, `500`, or `502` handler is implemented. |
| Career analytics | `GET /cia/career/analytics` (`cia.career.analytics`) | No request body or query validation | Placeholder `200`: `{message: "Career analytics endpoint is ready."}` | No endpoint-specific `422`, `500`, or `502` handler is implemented. |

The controller's unregistered `searchHistory` method is not in this smoke matrix:
no route for it is declared in `routes/web.php`.

### Smoke request examples

Replace `<BASE_URL>` with the authorized staging or production base URL. Keep
authentication in the configured client/session; do not put credentials in
this report.

```sh
curl -sS -X POST "<BASE_URL>/candidate/cia/career/vector-search" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  --data '{"skills":["Python","SQL","Data Visualization"]}'

curl -sS -X POST "<BASE_URL>/candidate/cia/career/learning-path" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  --data '{"target_role":"Full Stack Developer","skills":["PHP","Laravel"],"weekly_hours":7}'

curl -sS -X POST "<BASE_URL>/candidate/cia/career/skill-gap" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  --data '{"target_role":"Full Stack Developer"}'
```

Confirm a successful `CIA_CAREER_TELEMETRY` structured log record for each
controller-backed request. The route-level readiness handlers emit the same
marker. Do not log request bodies, authorization headers, SQL text, or provider
secrets as part of smoke verification.
