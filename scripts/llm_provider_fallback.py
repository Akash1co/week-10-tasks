"""Call Groq with an OpenRouter fallback and emit a controlled JSON response."""

import json
import logging
import os
import socket
import sys
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen


LOGGER = logging.getLogger(__name__)
TIMEOUT_SECONDS = 25


class ProviderError(Exception):
    """A provider request failed without a timeout."""


class ProviderTimeout(ProviderError):
    """A provider request timed out or returned a timeout status."""


def _request_completion(
    *,
    endpoint: str,
    api_key: str,
    model: str,
    payload: dict[str, Any],
) -> str:
    if not api_key:
        raise ProviderError("Provider API key is not configured.")

    body = {**payload, "model": model}
    request = Request(
        endpoint,
        data=json.dumps(body).encode("utf-8"),
        headers={
            "Authorization": f"Bearer {api_key}",
            "Content-Type": "application/json",
            "Accept": "application/json",
        },
        method="POST",
    )
    try:
        with urlopen(request, timeout=TIMEOUT_SECONDS) as response:
            result = json.loads(response.read().decode("utf-8"))
    except HTTPError as exception:
        if exception.code in {408, 504}:
            raise ProviderTimeout from exception
        raise ProviderError from exception
    except (TimeoutError, socket.timeout) as exception:
        raise ProviderTimeout from exception
    except URLError as exception:
        if isinstance(exception.reason, (TimeoutError, socket.timeout)):
            raise ProviderTimeout from exception
        raise ProviderError from exception
    except OSError as exception:
        raise ProviderError from exception
    except (UnicodeDecodeError, json.JSONDecodeError) as exception:
        raise ProviderError from exception

    try:
        content = result["choices"][0]["message"]["content"]
    except (KeyError, IndexError, TypeError) as exception:
        raise ProviderError from exception
    if not isinstance(content, str):
        raise ProviderError("Provider returned an invalid response.")
    return content


def call_groq(payload: dict[str, Any]) -> str:
    return _request_completion(
        endpoint="https://api.groq.com/openai/v1/chat/completions",
        api_key=os.getenv("GROQ_API_KEY", "").strip(),
        model=os.getenv("GROQ_MODEL", "llama-3.3-70b-versatile"),
        payload=payload,
    )


def call_openrouter(payload: dict[str, Any]) -> str:
    return _request_completion(
        endpoint="https://openrouter.ai/api/v1/chat/completions",
        api_key=os.getenv("OPENROUTER_API_KEY", "").strip(),
        model=os.getenv("OPENROUTER_MODEL", "openai/gpt-4o-mini"),
        payload=payload,
    )


def complete_with_fallback(payload: dict[str, Any]) -> dict[str, Any]:
    primary_timed_out = False
    try:
        return {
            "status": "ok",
            "provider": "groq",
            "content": call_groq(payload),
        }
    except ProviderError as primary_error:
        primary_timed_out = isinstance(primary_error, ProviderTimeout)
        LOGGER.warning(
            "Groq request failed; attempting OpenRouter fallback (%s).",
            "timeout" if primary_timed_out else "provider_error",
        )

    try:
        return {
            "status": "ok",
            "provider": "openrouter",
            "content": call_openrouter(payload),
        }
    except ProviderError as fallback_error:
        timed_out = primary_timed_out or isinstance(fallback_error, ProviderTimeout)
        LOGGER.error("OpenRouter fallback failed (%s).", "timeout" if timed_out else "provider_error")
        return {
            "status": "error",
            "message": "LLM providers are currently unavailable.",
            "http_status": 504 if timed_out else 502,
        }


def main() -> int:
    try:
        payload = json.load(sys.stdin)
    except (json.JSONDecodeError, UnicodeDecodeError):
        sys.stdout.write(json.dumps({
            "status": "error",
            "message": "Request body must be valid JSON.",
            "http_status": 422,
        }) + "\n")
        return 1

    if (
        not isinstance(payload, dict)
        or not isinstance(payload.get("messages"), list)
        or not payload["messages"]
    ):
        sys.stdout.write(json.dumps({
            "status": "error",
            "message": "A non-empty messages array is required.",
            "http_status": 422,
        }) + "\n")
        return 1

    result = complete_with_fallback(payload)
    sys.stdout.write(json.dumps(result, ensure_ascii=False) + "\n")
    return 0 if result["status"] == "ok" else 1


if __name__ == "__main__":
    logging.basicConfig(level=logging.WARNING, stream=sys.stderr)
    raise SystemExit(main())
