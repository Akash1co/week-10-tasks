# CIA Career Intelligence Changelog

This changelog summarizes the Project 4 (`cynaris-portal`) implementation
milestones recorded in the repository history for Weeks 10-12. It is
project-scoped rather than a release/version changelog.

## Week 10 - Career intelligence foundations

### Day 1 - Resume intelligence

- Added the resume-intelligence pipeline with Docling PDF extraction and a
  Groq-based ATS scoring workflow.

### Day 2 - Placement and interview readiness

- Added the placement/interview readiness score engine and candidate-facing
  scorecard UI.

### Day 3 - Career intelligence reports

- Added the career-intelligence archetype UI, report generation, PDF export
  through Dompdf, and report download history.

### Day 4 - Internship matching

- Added the ChromaDB internship-role dataset and vector-search script.
- Added the matching API endpoint and ranked dashboard UI.

### Day 5 - Integration verification

- Added end-to-end integration checks for the career-intelligence modules.
- Fixed integration-test issues and hardened service failover behavior.

## Week 11 - AI workflows, reliability, and operations

### Day 1 - Search performance audit

- Added a ChromaDB benchmark over a 1,000-document dataset and recorded
  performance-audit results.
- Added candidate/time and candidate/role indexes for CIA search-log queries.

### Day 2 - LLM provider resilience

- Added a Groq-primary, OpenRouter-fallback completion service with tests.
- Standardized provider failure output and HTTP-status mapping for the
  fallback flow.

### Day 3 - Endpoint security hardening

- Hardened CIA endpoints against injection and access-control risks.
- Added a security audit/remediation record to the project history.

### Day 4 - Logging and telemetry

- Added structured logging, telemetry tracking, and alert-hook work for CIA
  workflows.
- The current checked-in controller audit does not expose a
  `CIA_CAREER_TELEMETRY` identifier or controller-level event contract; see
  the implementation caveat in the [architecture document](./architecture_cia_career_w12d2.md#5-reliability-security-and-error-handling).

### Day 5 - Staging verification and deployment preparation

- Recorded a staging deployment runbook/module-architecture deliverable and
  smoke verification for the seven-endpoint milestone.

## Week 12 - Production hardening and technical documentation

### Day 1 - Production readiness

- Recorded production deployment/CI smoke-verification work.
- Added a commit-level hardening milestone for database `QueryException`
  handling and error responses. The current audited `CiaController.php` has
  no explicit `QueryException` catch; controller/database failures rely on
  Laravel's global exception handling. The architecture and API reference
  document that checked-in behavior rather than assuming a controller-level
  implementation.

### Day 2 - Architecture, API contract, and changelog

- Added the end-to-end architecture and the separate ChromaDB candidate
  profile and live role-search collection specifications.
- Consolidated the API reference around the actual route registrations,
  middleware, validators, response shapes, placeholder handlers, and
  controller-backed AI workflows.
- Added this chronological project changelog and documented the observed
  implementation boundaries for telemetry and database error handling.
