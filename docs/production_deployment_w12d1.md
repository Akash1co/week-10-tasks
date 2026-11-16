# Project 4 — CIA Career Intelligence Production Release Sign-off

## Release summary

- **Project:** Project 4 — CIA Career Intelligence (`cynaris-portal`)
- **Work item:** Week 12, Day 1 — production deployment and CI verification
- **Feature branch:** `feat/cia-career-w12d1-akasha`
- **Release posture:** **Conditionally ready for parent-portal CI/staging
  validation. Production sign-off is pending the parent portal pipeline,
  authenticated staging smoke tests, deployment review, and production
  verification.**
- **Code review outcome:** Controller database failures now receive clean JSON
  HTTP 500 responses; request validation and controller responses emit
  `CIA_CAREER_TELEMETRY` with route name, HTTP method, and status. Query
  exceptions are recorded by exception class only; database/SQL detail is not
  placed in the client response.
- **Local verification:** PHP syntax checks pass for the controller and routes.
  This standalone checkout lacks the parent portal's `artisan` and Composer
  manifests, so Laravel bootstrap, application tests, and live HTTP checks have
  not been run here. See
  [the production CI and smoke verification log](../storage/logs/cia_production_ci_w12d1.md).

The dashboard, profile, and GET recommendation route declarations currently
return placeholder readiness JSON. Their registration is not evidence that
data-backed dashboard/profile/analytics features are implemented. The parent
portal owner must confirm intended release scope before production exposure.

## Architecture and data flow

1. Authenticated requests enter Laravel through the CIA routes in
   `routes/web.php`. The four integration endpoints dispatch to
   `CiaController`; the dashboard, profile, analytics, and GET recommendations
   routes currently use readiness-message closures.
2. `CiaController` validates request fields and derives candidate identity from
   the authenticated user. Skill-gap analysis reads completed lessons through
   `LessonUser`/`lessons` and completed quiz data through `QuizAttempt`.
3. For skill-gap LLM analysis, Laravel sends structured JSON to
   `scripts/llm_provider_fallback.py` through Symfony Process. The helper
   attempts Groq and then OpenRouter and returns a controlled JSON result;
   the controller validates its output before responding. If no provider keys
   are configured, skill-gap analysis uses its local benchmark-derived
   analysis.
4. For recommendations, Laravel invokes
   `scripts/career_recommendations.py`, which uses CrewAI and a structured
   Pydantic response to produce exactly three career paths.
5. For vector search, Laravel invokes `scripts/vector_search_roles.py`. That
   script queries the ChromaDB `internship_roles` collection, ranks roles by
   cosine similarity, and returns five matches plus missing skills. The
   collection is provisioned by `scripts/seed_chromadb_roles.py`.
6. For learning paths, Laravel invokes `scripts/langgraph_learning_path.py`.
   The LangGraph pipeline validates an eight-week / 56-day schedule and can
   use deterministic node fallbacks when Groq is unavailable or returns
   unusable output.
7. Vector search, generated recommendations, and learning-path outcomes are
   recorded in `cia_search_logs`. The table and its indexes are defined by the
   existing CIA migrations. `CIA_CAREER_TELEMETRY` records the route, method,
   and HTTP status for controller responses and readiness-route responses.

Python subprocess timeouts, Python executable/package availability, ChromaDB
data/permissions, provider connectivity, credentials, database migrations, and
log delivery must be validated in the parent portal's staging and production
environments. No live provider or database verification is claimed by this
release report.

**Optimize/route-cache gate:** `routes/web.php` contains closure-based
readiness routes. Laravel route caching commonly cannot serialize closure
actions. Because `php artisan optimize` was not executable in this checkout,
verify it against the parent's exact Laravel version before release. If route
caching fails, replace those placeholders with controller actions (or apply
the portal's approved route-cache strategy) before sign-off; do not skip or
claim the optimize step passed.

## Production and staging demo endpoints

Set the environment-specific base URL in the deployment system; hostnames are
intentionally not invented here:

- Staging: `${CIA_STAGING_BASE_URL}`
- Production: `${CIA_PRODUCTION_BASE_URL}`

The registered paths are the same in both environments:

- `POST /candidate/cia/career/skill-gap`
- `POST /candidate/cia/career/recommendations`
- `POST /candidate/cia/career/vector-search`
- `POST /candidate/cia/career/learning-path`
- `GET /cia/career/dashboard`
- `GET /cia/career/profiles/{candidate_id}`
- `POST /cia/career/profiles`
- `PUT /cia/career/profiles/{candidate_id}/skills`
- `GET /cia/career/recommendations/{candidate_id}`
- `GET /cia/career/analytics`

All routes require the configured authenticated session/guard. The dashboard,
profiles, analytics, and GET recommendations currently return readiness
placeholders; do not use them as a functional data-backed demo. See the smoke
matrix for request rules, response shapes, and current error coverage.

## Zero-downtime production deployment

Use the parent portal's established release-directory and shared-storage
layout. Do not update the live release in place.

1. Confirm the approved commit/branch, clean CI result, maintenance/rollback
   owner, database backup policy, and required environment variables. Verify
   the deployed Python runtime, script dependencies, ChromaDB collection, and
   writable log/storage paths.
2. Prepare a new release directory from the approved revision. Preserve the
   current release and shared `.env`, uploads, logs, and persistent vector
   data. Apply database changes using the established forward-compatible
   migration process; this work item does not authorize destructive schema
   changes.
3. In the new release directory, run the production sequence in order:

   ```sh
   git pull --ff-only origin <approved-release-branch>
   composer dump-autoload
   php artisan optimize
   ```

4. Confirm `php artisan optimize` completed, including route-cache creation,
   with the parent's actual Laravel version. Stop deployment if closure routes
   prevent caching; resolve that issue before switching production traffic.
5. Verify the optimized release is healthy and can serve the smoke requests
   before directing production traffic. Check both JSON contracts and the
   `CIA_CAREER_TELEMETRY` record in the active application log. Validate
   authenticated responses; an unauthenticated `401` is not a successful
   application smoke test.
6. Atomically switch the web-server/current-release symlink to the new release
   (or use the portal's equivalent atomic traffic switch). Keep the previous
   release available. Confirm readiness and representative endpoint responses
   after the switch, then complete the log verification step.
7. Monitor error rate, `QueryException` events, process timeout/failure
   responses, database writes, and provider/vector-service availability for
   the agreed observation window. Record the deployed revision and actual
   smoke-test status in the release log.

The command sequence above is the production runbook to execute in
`cynaris-portal`; it was not run from this standalone module checkout.

## Immediate rollback protocol

1. If health checks, response contracts, telemetry, database behavior, or
   error rates regress, stop further traffic changes and notify the release
   owner.
2. Atomically point the production symlink/traffic target back to the
   immediately previous known-good release. Do not delete the failed release
   or shared logs.
3. Re-run the authenticated health and representative smoke requests against
   the restored release; verify `CIA_CAREER_TELEMETRY` and application logs.
4. Keep database changes backward compatible with both releases. Do not
   reverse a migration or remove data as an emergency code rollback; use the
   database owner's approved recovery procedure if a schema issue is involved.
5. Preserve failed-release logs and revision details, document the rollback
   time and observed symptoms, and resume deployment only after a reviewed fix
   passes parent CI and staging verification.

## Sign-off gates

- [ ] Parent `cynaris-portal` CI and configured test suite pass.
- [ ] Required database migrations and rollback compatibility are confirmed.
- [ ] Authenticated staging smoke matrix passes for implemented endpoints.
- [ ] Placeholder endpoints are accepted as placeholders or excluded from
      release/demo scope.
- [ ] Production environment configuration, Python dependencies, ChromaDB,
      provider keys, and log permissions are verified.
- [ ] Production traffic switch and rollback owner are confirmed.
- [ ] Production smoke results, telemetry, and deployed revision are recorded.
