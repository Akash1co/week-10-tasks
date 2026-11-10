"""Unit tests for the Groq-to-OpenRouter fallback command."""

import io
import json
import os
import sys
import unittest
from contextlib import redirect_stderr, redirect_stdout
from pathlib import Path
from unittest.mock import Mock, patch

import requests


SCRIPT_DIRECTORY = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(SCRIPT_DIRECTORY))

import llm_provider_fallback  # noqa: E402


class LlmFallbackTests(unittest.TestCase):
    def setUp(self) -> None:
        self.environment = patch.dict(os.environ, {
            "GROQ_API_KEY": "test-groq-key",
            "OPENROUTER_API_KEY": "test-openrouter-key",
        })
        self.environment.start()
        self.addCleanup(self.environment.stop)
        self.process_handles: list[object] = []

    @staticmethod
    def successful_response(content: str) -> Mock:
        response = Mock()
        response.raise_for_status.return_value = None
        response.json.return_value = {
            "choices": [{"message": {"content": content}}],
        }
        return response

    @staticmethod
    def failed_response(status_code: int) -> Mock:
        response = Mock()
        response.status_code = status_code
        response.raise_for_status.side_effect = requests.HTTPError(
            f"HTTP {status_code}",
            response=response,
        )
        return response

    def invoke_cli(self, *arguments: str) -> tuple[int, dict[str, object], str]:
        stdout = io.StringIO()
        stderr = io.StringIO()
        with redirect_stdout(stdout), redirect_stderr(stderr):
            exit_code = llm_provider_fallback.main(list(arguments))
        return exit_code, json.loads(stdout.getvalue()), stderr.getvalue()

    @patch("llm_provider_fallback.requests.post")
    def test_primary_groq_success(self, post: Mock) -> None:
        post.return_value = self.successful_response("Groq answer")

        exit_code, result, stderr = self.invoke_cli(
            "--prompt",
            "Build a career plan",
        )

        self.assertEqual(exit_code, 0)
        self.assertEqual(result["status"], "success")
        self.assertEqual(result["provider"], "groq")
        self.assertEqual(result["model"], "llama-3.3-70b-versatile")
        self.assertEqual(result["data"], {"response": "Groq answer"})
        self.assertIsInstance(result["latency_ms"], float)
        self.assertEqual(stderr, "")
        post.assert_called_once()
        self.assertEqual(post.call_args.kwargs["timeout"], 5.0)
        self.assertEqual(
            post.call_args.kwargs["headers"]["Authorization"],
            "Bearer test-groq-key",
        )

    @patch("llm_provider_fallback.requests.post")
    def test_groq_timeout_triggers_openrouter_fallback(self, post: Mock) -> None:
        post.side_effect = [
            requests.Timeout("Groq timed out"),
            self.successful_response("OpenRouter answer"),
        ]

        exit_code, result, stderr = self.invoke_cli(
            "--prompt",
            "Build a career plan",
        )

        self.assertEqual(exit_code, 0)
        self.assertEqual(result["status"], "success")
        self.assertEqual(result["provider"], "openrouter")
        self.assertEqual(
            result["model"],
            "meta-llama/llama-3.3-70b-instruct",
        )
        self.assertEqual(result["data"], {"response": "OpenRouter answer"})
        self.assertIn("[Groq Failover Alert]:", stderr)
        self.assertIn("Timeout", stderr)
        self.assertEqual(post.call_count, 2)
        self.assertEqual(post.call_args_list[1].kwargs["timeout"], 7.0)

    @patch("llm_provider_fallback.requests.post")
    def test_dual_provider_failure_returns_json_error(self, post: Mock) -> None:
        post.side_effect = [
            self.failed_response(500),
            self.failed_response(504),
        ]

        exit_code, result, stderr = self.invoke_cli("--prompt", "Give advice")

        self.assertEqual(exit_code, 1)
        self.assertEqual(result["status"], "error")
        self.assertEqual(result["provider"], "failed_all")
        self.assertEqual(result["code"], 504)
        self.assertIn("Groq:", result["message"])
        self.assertIn("OpenRouter:", result["message"])
        self.assertIn("timestamp", result)
        self.assertIn("[Groq Failover Alert]:", stderr)

    @patch("llm_provider_fallback.requests.post")
    def test_empty_prompt_validation_failure(self, post: Mock) -> None:
        exit_code, result, stderr = self.invoke_cli("--prompt", "   ")

        self.assertEqual(exit_code, 1)
        self.assertEqual(result["status"], "error")
        self.assertEqual(result["code"], 422)
        self.assertIn("Prompt", result["message"])
        self.assertEqual(stderr, "")
        post.assert_not_called()

    @patch("llm_provider_fallback.requests.post")
    def test_missing_prompt_argument_returns_json_validation_error(self, post: Mock) -> None:
        exit_code, result, stderr = self.invoke_cli()

        self.assertEqual(exit_code, 1)
        self.assertEqual(result["status"], "error")
        self.assertEqual(result["code"], 422)
        self.assertIn("--prompt", result["message"])
        self.assertEqual(stderr, "")
        post.assert_not_called()

    @patch("llm_provider_fallback.requests.post")
    def test_invalid_sampling_parameters_return_422(self, post: Mock) -> None:
        for arguments in (
            ("--prompt", "valid", "--temperature", "2.1"),
            ("--prompt", "valid", "--temperature", "nan"),
            ("--prompt", "valid", "--max-tokens", "0"),
            ("--prompt", "valid", "--max-tokens", "8193"),
        ):
            with self.subTest(arguments=arguments):
                exit_code, result, _ = self.invoke_cli(*arguments)
                self.assertEqual(exit_code, 1)
                self.assertEqual(result["code"], 422)

        post.assert_not_called()

    @patch("llm_provider_fallback.requests.post")
    def test_missing_keys_fail_over_without_uncaught_exception(self, post: Mock) -> None:
        with patch.dict(os.environ, {
            "GROQ_API_KEY": " ",
            "OPENROUTER_API_KEY": "",
        }):
            exit_code, result, stderr = self.invoke_cli(
                "--prompt",
                "A valid prompt",
            )

        self.assertEqual(exit_code, 1)
        self.assertEqual(result["status"], "error")
        self.assertEqual(result["code"], 502)
        self.assertIn("API key is missing or blank", result["message"])
        self.assertIn("[Groq Failover Alert]:", stderr)
        post.assert_not_called()


if __name__ == "__main__":
    unittest.main()
