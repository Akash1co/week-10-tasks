"""Call Groq with an OpenRouter fallback and emit a controlled JSON response."""

import argparse
import json
import math
import os
import sys
import time
from datetime import datetime, timezone
from typing import Any

import requests


GROQ_MODEL = "llama-3.3-70b-versatile"
OPENROUTER_MODEL = "meta-llama/llama-3.3-70b-instruct"
GROQ_TIMEOUT_SECONDS = 5.0
OPENROUTER_TIMEOUT_SECONDS = 7.0
MAX_TOKENS_LIMIT = 8192


class ArgumentValidationError(ValueError):
    """A CLI argument is missing or malformed."""


class ArgumentParser(argparse.ArgumentParser):
    def error(self, message: str) -> None:
        raise ArgumentValidationError(message)


class ProviderFailure(Exception):
    """A provider request failed with a controlled status code."""

    def __init__(self, message: str, code: int = 502) -> None:
        super().__init__(message)
        self.code = code


def _error_payload(message: str, code: int) -> dict[str, Any]:
    return {
        "status": "error",
        "provider": "failed_all",
        "code": code,
        "message": message,
        "timestamp": datetime.now(timezone.utc).isoformat(),
    }


def _post_completion(
    *,
    endpoint: str,
    api_key: str,
    model: str,
    prompt: str,
    temperature: float,
    max_tokens: int,
    timeout: float,
) -> str:
    if not api_key.strip():
        raise ProviderFailure("Provider API key is missing or blank.")

    try:
        response = requests.post(
            endpoint,
            headers={
                "Authorization": f"Bearer {api_key}",
                "Content-Type": "application/json",
                "Accept": "application/json",
            },
            json={
                "model": model,
                "messages": [{"role": "user", "content": prompt}],
                "temperature": temperature,
                "max_tokens": max_tokens,
            },
            timeout=timeout,
        )
        response.raise_for_status()
    except requests.Timeout as exception:
        raise ProviderFailure(
            f"{type(exception).__name__} after {timeout:.1f} seconds.",
            504,
        ) from exception
    except requests.HTTPError as exception:
        status = exception.response.status_code if exception.response is not None else 0
        if status == 429:
            raise ProviderFailure("HTTP 429 rate limit exceeded.", 502) from exception
        if status in {500, 502, 503, 504}:
            raise ProviderFailure(
                f"HTTP {status} provider server error.",
                504 if status == 504 else 502,
            ) from exception
        raise ProviderFailure(
            f"HTTP {status} provider request failed.",
            502,
        ) from exception
    except requests.RequestException as exception:
        raise ProviderFailure(
            f"{type(exception).__name__}: provider network request failed.",
            502,
        ) from exception

    try:
        result = response.json()
        content = result["choices"][0]["message"]["content"]
    except (ValueError, KeyError, IndexError, TypeError) as exception:
        raise ProviderFailure("Provider returned malformed JSON data.", 502) from exception
    if not isinstance(content, str):
        raise ProviderFailure("Provider returned a non-string response.", 502)
    return content


def call_groq(prompt: str, temperature: float, max_tokens: int) -> str:
    return _post_completion(
        endpoint="https://api.groq.com/openai/v1/chat/completions",
        api_key=os.getenv("GROQ_API_KEY", ""),
        model=GROQ_MODEL,
        prompt=prompt,
        temperature=temperature,
        max_tokens=max_tokens,
        timeout=GROQ_TIMEOUT_SECONDS,
    )


def call_openrouter(prompt: str, temperature: float, max_tokens: int) -> str:
    return _post_completion(
        endpoint="https://openrouter.ai/api/v1/chat/completions",
        api_key=os.getenv("OPENROUTER_API_KEY", ""),
        model=OPENROUTER_MODEL,
        prompt=prompt,
        temperature=temperature,
        max_tokens=max_tokens,
        timeout=OPENROUTER_TIMEOUT_SECONDS,
    )


def complete_with_fallback(
    prompt: str,
    temperature: float = 0.7,
    max_tokens: int = 1024,
) -> dict[str, Any]:
    if not prompt.strip():
        return _error_payload("Prompt must not be empty or whitespace-only.", 422)
    if not math.isfinite(temperature) or not 0 <= temperature <= 2:
        return _error_payload("Temperature must be a finite number between 0 and 2.", 422)
    if not 1 <= max_tokens <= MAX_TOKENS_LIMIT:
        return _error_payload(f"max_tokens must be between 1 and {MAX_TOKENS_LIMIT}.", 422)

    primary_error: ProviderFailure
    try:
        started = time.perf_counter()
        content = call_groq(prompt, temperature, max_tokens)
        ended = time.perf_counter()
        return {
            "status": "success",
            "provider": "groq",
            "model": GROQ_MODEL,
            "data": {"response": content},
            "latency_ms": round((ended - started) * 1000, 2),
        }
    except ProviderFailure as exception:
        primary_error = exception
        timestamp = datetime.now(timezone.utc).isoformat()
        print(
            f"[Groq Failover Alert]: {timestamp} {primary_error}",
            file=sys.stderr,
        )

    try:
        started = time.perf_counter()
        content = call_openrouter(prompt, temperature, max_tokens)
        ended = time.perf_counter()
        return {
            "status": "success",
            "provider": "openrouter",
            "model": OPENROUTER_MODEL,
            "data": {"response": content},
            "latency_ms": round((ended - started) * 1000, 2),
        }
    except ProviderFailure as fallback_error:
        return _error_payload(
            "Both LLM providers failed. "
            f"Groq: {primary_error}; OpenRouter: {fallback_error}",
            fallback_error.code,
        )


def _build_parser() -> ArgumentParser:
    parser = ArgumentParser(description=__doc__)
    parser.add_argument("--prompt", required=True, help="User prompt payload.")
    parser.add_argument("--temperature", type=float, default=0.7)
    parser.add_argument("--max-tokens", type=int, default=1024)
    return parser


def main(argv: list[str] | None = None) -> int:
    try:
        arguments = _build_parser().parse_args(argv)
        result = complete_with_fallback(
            arguments.prompt,
            arguments.temperature,
            arguments.max_tokens,
        )
    except ArgumentValidationError as exception:
        result = _error_payload(str(exception), 422)

    sys.stdout.write(json.dumps(result, ensure_ascii=False) + "\n")
    return 0 if result["status"] == "success" else 1


if __name__ == "__main__":
    raise SystemExit(main())
