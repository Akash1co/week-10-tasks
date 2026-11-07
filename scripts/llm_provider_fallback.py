"""Small OpenAI-compatible client with Groq-to-OpenRouter failover."""

import json
import os
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen


class ProviderError(RuntimeError):
    """Base error for a provider response that could not be used."""


class ProviderUnavailable(ProviderError):
    """Raised for provider timeouts, transport errors, and server failures."""


class ProviderRejected(ProviderError):
    """Raised for non-retryable provider errors such as invalid credentials."""


def _post_chat_completion(
    provider: str,
    api_key: str,
    model: str,
    messages: list[dict[str, str]],
    timeout: float = 25.0,
) -> str:
    base_url = (
        "https://api.groq.com/openai/v1"
        if provider == "groq"
        else "https://openrouter.ai/api/v1"
    )
    request = Request(
        f"{base_url}/chat/completions",
        data=json.dumps(
            {"model": model, "temperature": 0.2, "messages": messages}
        ).encode("utf-8"),
        headers={
            "Authorization": f"Bearer {api_key}",
            "Content-Type": "application/json",
            "HTTP-Referer": "https://localhost",
            "X-Title": "CIA Career Intelligence",
        },
        method="POST",
    )
    try:
        with urlopen(request, timeout=timeout) as response:
            result = json.loads(response.read().decode("utf-8"))
    except HTTPError as error:
        if error.code >= 500:
            raise ProviderUnavailable(f"{provider} returned HTTP {error.code}.") from error
        raise ProviderRejected(f"{provider} rejected the request with HTTP {error.code}.") from error
    except (TimeoutError, URLError) as error:
        raise ProviderUnavailable(f"{provider} request failed: {error}.") from error
    except (UnicodeDecodeError, json.JSONDecodeError) as error:
        raise ProviderRejected(f"{provider} returned invalid JSON.") from error

    try:
        content = result["choices"][0]["message"]["content"]
    except (KeyError, IndexError, TypeError) as error:
        raise ProviderRejected(f"{provider} returned an invalid response shape.") from error
    if not isinstance(content, str) or not content.strip():
        raise ProviderRejected(f"{provider} returned an empty response.")
    return content


def complete_with_fallback(
    messages: list[dict[str, str]],
    *,
    groq_api_key: str | None = None,
    openrouter_api_key: str | None = None,
) -> str:
    groq_key = (groq_api_key if groq_api_key is not None else os.getenv("GROQ_API_KEY", "")).strip()
    openrouter_key = (
        openrouter_api_key
        if openrouter_api_key is not None
        else os.getenv("OPENROUTER_API_KEY", "")
    ).strip()
    if not groq_key and not openrouter_key:
        raise ProviderUnavailable("Neither Groq nor OpenRouter is configured.")

    if groq_key:
        try:
            return _post_chat_completion(
                "groq",
                groq_key,
                os.getenv("GROQ_MODEL", "llama-3.3-70b-versatile"),
                messages,
            )
        except ProviderUnavailable:
            if not openrouter_key:
                raise

    return _post_chat_completion(
        "openrouter",
        openrouter_key,
        os.getenv("OPENROUTER_MODEL", "openai/gpt-4o-mini"),
        messages,
    )
