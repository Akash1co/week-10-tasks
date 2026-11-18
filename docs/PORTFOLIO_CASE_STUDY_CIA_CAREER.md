# CIA Career Intelligence — Portfolio Case Study

## The Problem

Engineering interns often search for roles through keyword boards that reward
matching vocabulary rather than transferable skills. Generic career portals
make the next problem worse: a list of job titles does not explain which
competencies are missing, what to learn next, or how to organize that work
around limited study time. Inside the Cynaris learning portal, the opportunity
was to connect candidate progress to career guidance without presenting an
unbounded model call as a dependable product feature. I treated this as a
systems problem: useful recommendations need structured inputs, bounded
service boundaries, and an honest fallback when infrastructure or providers
are unavailable. The project scope in this repository is deliberately visible:
some dashboard and profile routes are still placeholders, so the case study
focuses on the implemented AI feature slice rather than implying every
candidate workflow is complete.

## What I Built

I implemented four authenticated Laravel controller actions: skill-gap
analysis, career recommendations, personalized learning-path generation, and
semantic role search. Laravel validates payloads, passes JSON to Python
through Symfony Process, checks return codes and response shapes, and records
candidate-scoped request and result data in `cia_search_logs`. The ChromaDB
pipeline stores 20 role profiles and returns the five closest cosine-ranked
matches with missing skills. A CrewAI script produces three structured career
paths. A five-stage LangGraph builds an eight-week plan containing 56 daily
entries, with schema checks and deterministic per-stage fallback. Skill-gap
LLM analysis tries Groq before OpenRouter. Separate dashboard, profile, and
analytics routes exist but remain JSON placeholders; they are not described
here as fully implemented modules.

## Technical Challenges

The PHP-to-Python boundary had to fail predictably. I used argument-list
process invocation, bounded timeouts, explicit JSON encoding and decoding,
status checks, response-shape validation, and logged failures mapped to
controlled HTTP errors. Validation failures return `422`, while process
startup, timeout, provider, and malformed-output failures do not masquerade
as successful recommendations.

Provider resilience required precise limits. The skill-gap script attempts
Groq, then OpenRouter, and reports a controlled error if both fail; it does
not claim exponential backoff or successful offline synthesis. Separately,
the learning-path graph falls back deterministically at individual stages.
This distinction keeps the fallback story testable rather than overstated.

For persistence, the migration adds compound indexes for candidate history
and role-filtered history. The supplied audit records that live MySQL
`EXPLAIN` measurements were not captured, so query-plan or production latency
improvements remain unverified. ChromaDB uses cosine distance and checks
collection size and result count before returning matches.

## Outcome

The delivered slice has explicit, inspectable limits: 20 seeded role
profiles, five ranked search results, three structured recommendations, and
an eight-week schedule with 56 daily topics. The repository includes API
contracts, implementation notes, Python tests, and a verification runner
that distinguishes syntax and offline checks from checks requiring a full
Laravel host, database, or live credentials. Historical local performance
observations are labeled as such rather than presented as production
benchmarks. The result is a reproducible engineering portfolio artifact:
reviewers can trace requests from authenticated routes through validation,
isolated Python processing, structured output checks, and audit persistence,
while also seeing exactly which adjacent modules still need implementation.
