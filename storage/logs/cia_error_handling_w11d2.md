# CIA Career Intelligence — Error Handling & Failover Audit (Week 11, Day 2)

## 1. Overview

- Scope: Groq to OpenRouter dual-provider resilience architecture.
- Target Repository: `week-10-tasks`
- Primary Objective: Catch provider timeouts, rate limits, and server errors without uncaught application exceptions. This failover improves resilience but does not independently guarantee 99.9% service uptime.

## 2. Failover Strategy & Architecture

- **Primary Provider**: Groq API (`llama-3.3-70b-versatile`) — latency optimized, 5-second request timeout.
- **Secondary Provider**: OpenRouter API (`meta-llama/llama-3.3-70b-instruct`) — resilience backup, 7-second request timeout.
- **Input Validation**: Prompt must contain non-whitespace text; temperature must be finite and between 0 and 2; `max_tokens` must be between 1 and 8,192.
- **Exception Mapping**:
  * HTTP 422: Invalid prompt or request parameters.
  * HTTP 502: Provider/network failure or malformed provider response.
  * HTTP 504: Provider timeout or secondary gateway timeout.
- **Output Contract**: The Python process writes one JSON result to stdout. Groq failover diagnostics are written to stderr. Laravel validates the prompt, limits access to authenticated requests, and applies request throttling.

## 3. Verification Test Results

- `test_primary_groq_success`: PASSED
- `test_groq_timeout_triggers_openrouter_fallback`: PASSED
- `test_dual_provider_failure_returns_json_error`: PASSED
- `test_empty_prompt_validation_failure`: PASSED
- Parameter bounds and missing-key behavior are also covered by unit tests.
