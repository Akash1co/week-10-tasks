# CIA Career Intelligence W12D4 Code Review

## Review checklist and audit summary

- **Routes, authentication, and CSRF:** The CIA service routes in
  `routes/web.php` explicitly require `auth`. They remain in Laravel's web
  routing boundary; this review added no CSRF exemption or middleware change.
- **Validation and `422` responses:** `CiaController::validateRequest` converts
  Laravel validation failures into JSON `422` responses. Skill, gap, array,
  and schedule bounds are enforced on the relevant endpoints. The skill-gap
  and career-recommendation routes previously accepted whitespace-only role
  names, then passed an empty value downstream. Both now require a
  non-whitespace character.
- **Subprocess timeouts:** Controller subprocess limits are bounded at 55
  seconds for skill-gap provider failover, 90 seconds for career
  recommendations and vector search, and 240 seconds for learning paths.
  Learning-path requests additionally constrain weekly hours to 1-40.
- **JSON and response-shape handling:** PHP serializes subprocess input with
  `JSON_THROW_ON_ERROR`, checks process status, decodes JSON with exceptions,
  and validates the expected response shapes before returning them. The
  nested LLM skill-gap content is decoded and checked for string-list fields.
- **RAG / ChromaDB:** Vector search requires the seeded 20-profile collection,
  requests five ranked results, rejects non-finite distances, and returns
  required/missing-skill data. ChromaDB availability and malformed collection
  metadata do not have a deterministic offline fallback; failures propagate
  to the controller's logged `502` path.
- **LangGraph and offline behavior:** Learning-path generation has a
  deterministic per-node fallback when the Groq key is absent or a model call
  or structured response fails. Its fallback schedule is structurally checked
  and constrained by the submitted weekly-hours budget.
- **Career recommendations and provider fallback:** The CrewAI recommender
  validates a structured three-path response but has no offline generation
  fallback; process errors are surfaced by the PHP controller. At review
  start, `scripts/llm_provider_fallback.py` already had unstaged local edits.
  That file and its related pre-existing test edits were not changed or staged.

## Findings addressed and remaining observations

### Addressed

- Rejected whitespace-only `target_role` values on both the skill-gap and
  career-recommendation endpoints. The shared validation helper returns the
  existing JSON `422` response for these requests.
- Updated the API contract to document the non-blank role requirement and
  `422` result.

### Observations not changed in this pass

- Vector-search and CrewAI CLI failures can produce a non-zero process exit
  with diagnostic output on stderr rather than a normalized JSON error on
  stdout. The PHP callers check the exit status and return logged service
  errors, so this was not changed as part of the role-input fix.
- Vector-search availability and CrewAI provider outages do not have the same
  offline fallback guarantee as LangGraph. Their failures remain explicit;
  deterministic fallback behavior would be a separate product decision.
- `scripts/verify_capstone_demo.py`, `artisan`, and `vendor/bin/phpunit` are not
  present in this checkout. The requested capstone smoke script and PHPUnit
  suite therefore could not be executed.

## Commit hygiene and squash strategy

- The feature branch was `feat/cia-career-w12d4-akasha`, initially at
  `e7bfaf28` (the same base commit as local `main`). There were no existing
  WIP commits on this branch to squash.
- Pre-existing worktree changes in `scripts/llm_provider_fallback.py` and
  `scripts/tests/test_cia_career_performance.py`, along with pre-existing
  untracked files and artifacts, were left unstaged.
- The defensive controller change was isolated in
  `366f598f` (`fix(cia-career): reject blank target roles in AI endpoints`).
  The API contract update and this report are kept in a separate documentation
  commit.
- No commit on `main` was amended or rewritten, and no force push was used.

## Post-review verification

| Check | Result |
| --- | --- |
| `php -l routes/web.php` | Passed: no syntax errors detected |
| `php -l app/Http/Controllers/CiaController.php` | Passed: no syntax errors detected |
| `py_compile` for the four present requested Python scripts | Passed |
| `python scripts/verify_capstone_demo.py` | Not run: file is absent (`No such file or directory`) |
| Offline LangGraph tests (`python -m unittest -v scripts.tests.test_langgraph_learning_path`) | Passed: 3 tests |
| Pytest | Unavailable: `.venv` reports `No module named pytest` |
| PHPUnit / `php artisan test --filter=Cia` | Unavailable: neither `artisan` nor `vendor/bin/phpunit` exists |
| ChromaDB vector tests | Not run: test seeding uses the embedding default and could access the network; the existing untracked persistent ChromaDB directory was not touched |
| `git diff --check` | Passed: no whitespace errors |

The Python compilation check covered `vector_search_roles.py`,
`career_recommendations.py`, `langgraph_learning_path.py`, and the existing
working copy of `llm_provider_fallback.py`. The missing capstone verifier was
not included.
