# CIA Career Intelligence — Logging, Monitoring & Alerting Operations Manual (Week 11, Day 4)

## 1. Executive Summary

- **Scope:** Production-oriented telemetry, structured JSON logging, and automated failure alerts for CIA Career Intelligence endpoints.
- **Objective:** Provide visibility into LLM provider selection, fallback occurrences, request latency, and error frequencies.
- **Endpoints:** Skill-gap analysis, career recommendations, learning-path generation, vector search, and search history.
- **Log destination:** `storage/logs/cia_monitoring.log` for JSON telemetry and `CRITICAL_ALERT` records.

The controller emits telemetry when each endpoint returns a response. Provider-specific skill-gap execution telemetry supplements the endpoint event with provider timings, model, token usage, and per-provider HTTP statuses. Alert records are written to the same file at `critical` severity; no external paging integration is configured by this change.

## 2. Structured JSON Log Schema

- **Event Identifier:** `CIA_CAREER_TELEMETRY`
- **Payload Specifications:** Fields are stored in the `context` property of Monolog's JSON log envelope.
  - `timestamp`: String (ISO 8601 UTC)
  - `candidate_id_hash`: String (SHA-256 hash; raw candidate identifiers are not written to telemetry)
  - `execution_latency_ms`: Integer (milliseconds)
  - `llm_provider`: String (`groq`, `openrouter`, or `not_applicable` when no provider is configured/used)
  - `fallback_triggered`: Boolean
  - `status_code`: Integer (HTTP response status)
  - `action`: Endpoint action name

The provider-specific skill-gap event also records provider/model, provider latency, token usage when supplied, primary and fallback statuses, and provider failure details. Fallback events are logged at `WARNING`; request failures use error-level telemetry. Prompts, provider responses, API keys, and raw candidate identifiers must not be added to monitoring context.

## 3. Alerting Rules & Thresholds

| Condition | Severity / Marker | Response |
| --- | --- | --- |
| A provider fallback is engaged (`Groq → OpenRouter`) | `WARNING` | Inspect primary-provider health and fallback frequency |
| HTTP 429 on a monitored endpoint or provider | `CRITICAL_ALERT` | Check quotas, rate limits, and backoff behavior |
| HTTP 502/504 gateway or provider failure | `CRITICAL_ALERT` | Check provider health, subprocess output, and network dependencies |
| Both configured LLM providers fail | `CRITICAL_ALERT` | Check both provider statuses, credentials, DNS/egress, and provider availability |
| Database query exception in monitored persistence/history operations | `CRITICAL_ALERT` | Check database availability, query/schema health, and application logs |

Critical markers are emitted to `storage/logs/cia_monitoring.log` with critical severity. The application records each qualifying event; aggregation windows and notification delivery must be configured by the deployment's log/alert platform.

## 4. Operations and Retention

1. Ensure the PHP worker can write to `storage/logs` and configure a stable, strong Laravel `APP_KEY`.
2. Tail `cia_monitoring.log` with `Get-Content storage/logs/cia_monitoring.log -Wait` on Windows PowerShell or `tail -F` on Unix-like systems.
3. Ship the file through the approved JSON log collector. Parse Monolog's envelope and expand `context`; index `timestamp`, `event`, `action`, `level_name`, `llm_provider`, `status_code`, and `fallback_triggered`.
4. Dashboard request volume, error ratio, p50/p95 latency, provider fallback count, and 429/502/504 counts. For skill-gap provider events, also monitor provider latency and available token totals.
5. Configure alert aggregation and immediate notification for `CRITICAL_ALERT`, repeated 429s, elevated 502/504 rates, and sustained latency regressions.
6. Configure host-level rotation, retention, and access controls. The single-file logger preserves the requested filename but does not rotate it. Keep logs outside public web roots.

## 5. Verification Checklist

- Exercise each career endpoint with successful and invalid requests; verify telemetry includes timestamp, candidate hash, action, latency, provider tag, fallback flag, and response status.
- Simulate Groq failure followed by OpenRouter success; verify a warning fallback record and provider-specific status/timing details.
- Simulate dual-provider failure, HTTP 429, and HTTP 502/504; verify critical markers are written to `cia_monitoring.log`.
- Exercise a database query exception in a non-production environment; verify the critical alert contains diagnostic codes but no SQL parameters or raw candidate ID.
- Confirm production log rotation, collector parsing, retention, and alert delivery separately; this code writes local alert records and does not itself page an on-call rotation.
