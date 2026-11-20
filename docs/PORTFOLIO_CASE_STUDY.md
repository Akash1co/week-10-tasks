# CIA Career Intelligence — Portfolio Case Study

## Problem

Early-career engineers need more than a static list of courses: they need to
understand how current skills compare with a target role, receive useful
practice guidance, and see progress in a form they can discuss with a mentor
or interviewer. A capstone also needs clear evidence of how AI suggestions
are generated, what happens when providers fail, and which product goals are
implemented versus still planned.

## What this repository implements

The CIA code in this checkout is a partial vertical slice across a Laravel
integration, Python services, ChromaDB, and a Blade interface. It should not
be presented as a completed implementation of all seven planned modules.

1. **Developer workspace and curriculum:** `career-tab.blade.php` contains
   forms for skill-gap analysis, career recommendations, an eight-week
   learning path, and role matching. A curriculum progress tracker is not
   present in the inspected view or routes.
2. **LangGraph orchestration:** `langgraph_learning_path.py` runs five
   sequential graph nodes for assessment, gap identification, schedule
   building, validation, and summary. Pydantic schemas constrain the result;
   deterministic per-node output is available when the Groq call fails or is
   not configured.
3. **Semantic search and RAG:** `role_profiles.py`,
   `seed_chromadb_roles.py`, and `vector_search_roles.py` maintain 20 role
   profiles and rank five matches by cosine similarity, including missing
   skills. This is vector-based role matching, not a demonstrated
   retrieval-augmented answer-generation pipeline.
4. **Milestone and rubric evaluation:** not implemented as a rubric engine.
   The skill-gap endpoint reads completed lessons and quiz scores as input
   signals; it does not score milestones or code against a rubric.
5. **Git and PR verification simulator:** not present in this checkout.
   There is no simulator that validates branches, commits, or pull requests.
6. **Student/admin analytics:** the authenticated dashboard and analytics
   routes return readiness messages rather than populated analytics. The
   skill-gap response does provide a candidate readiness score and radar data.
7. **Portfolio and capstone export:** not present as an export pipeline or
   endpoint.

## Engineering challenges and decisions

The main integration boundary is Laravel invoking Python through bounded
Symfony processes. The controller validates request fields, supplies
structured JSON, applies process timeouts, checks exit status, decodes and
shape-checks output, and maps service failures to non-success HTTP responses.
Provider errors are logged while API responses use generic messages. The
learning-path graph validates structured model output and falls back to a
deterministic plan; career recommendations and vector search do not have the
same offline-generation guarantee. ChromaDB persists role vectors and
exposes an explicit seed step. Authenticated web routes use the application
session boundary, with candidate IDs for the analysis flows derived from the
authenticated user.

The repository also illustrates why branch and test hygiene matter: the
checked-out workspace had pre-existing pending changes, including the tagged
LLM script, so those edits must be reviewed and isolated before a release
commit. The present `test_llm_fallback.py` expects a different CLI and
`requests`-based implementation than the current JSON-stdin script; it needs
alignment before it can serve as regression evidence.

## Outcomes and next steps

The implemented slice demonstrates schema-constrained generation, bounded
cross-language calls, explicit error responses, persistent semantic role
matching, and useful candidate-facing visualizations. No production
availability, latency, or learning-outcome metrics are claimed: those require
repeatable deployment tests and instrumented user data. For capstone
readiness, first establish a clean release branch and repeatable test setup,
then complete the rubric, Git verification, real dashboards, and export
modules. Treat provider-backed recommendations and first-time embedding
downloads as external dependencies, not guaranteed offline features.
