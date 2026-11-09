"""Performance and provider failover checks for CIA Career Intelligence."""

import io
import json
import sys
import time
import tracemalloc
import unittest
from contextlib import redirect_stdout
from pathlib import Path
from unittest.mock import patch

import chromadb


SCRIPT_DIRECTORY = Path(__file__).resolve().parents[1]
PROJECT_ROOT = SCRIPT_DIRECTORY.parent
CHROMA_DIRECTORY = PROJECT_ROOT / "storage" / "chroma_db"
sys.path.insert(0, str(SCRIPT_DIRECTORY))

from llm_provider_fallback import ProviderTimeout, main  # noqa: E402


class TestCiaCareerPerformance(unittest.TestCase):
    @classmethod
    def setUpClass(cls) -> None:
        cls.chroma_path = CHROMA_DIRECTORY
        cls.script_directory = SCRIPT_DIRECTORY

    def test_01_chromadb_memory_profile_1000_docs(self) -> None:
        client = chromadb.PersistentClient(path=str(self.chroma_path))
        collection = client.get_or_create_collection(name="internship_roles")
        existing_count = collection.count()

        if existing_count < 1_000:
            existing_ids = set(collection.get(include=[])["ids"])
            next_index = 0
            remaining = 1_000 - existing_count
            while remaining:
                ids: list[str] = []
                documents: list[str] = []
                embeddings: list[list[float]] = []
                metadatas: list[dict[str, int]] = []
                batch_size = min(100, remaining)

                while len(ids) < batch_size:
                    document_id = f"w11d1-benchmark-{next_index}"
                    next_index += 1
                    if document_id in existing_ids:
                        continue

                    existing_ids.add(document_id)
                    ids.append(document_id)
                    documents.append(
                        "Python Laravel Full Stack Developer benchmark role "
                        f"profile {next_index}"
                    )
                    embeddings.append([
                        ((next_index * 17 + dimension * 13) % 101) / 100
                        for dimension in range(384)
                    ])
                    metadatas.append({"benchmark_document": next_index})

                collection.upsert(
                    ids=ids,
                    documents=documents,
                    embeddings=embeddings,
                    metadatas=metadatas,
                )
                remaining -= len(ids)

        self.assertGreaterEqual(collection.count(), 1_000)
        query_embedding = [
            ((index * 19 + 7) % 101) / 100 for index in range(384)
        ]

        tracemalloc.start()
        try:
            baseline, _ = tracemalloc.get_traced_memory()
            started_at = time.perf_counter()
            results = collection.query(
                query_embeddings=[query_embedding],
                n_results=5,
                include=["distances"],
            )
            duration = time.perf_counter() - started_at
            current, peak = tracemalloc.get_traced_memory()

            self.assertEqual(len(results["ids"][0]), 5)
            self.assertLess(duration, 2.0)
            self.assertGreaterEqual(peak, baseline)
            print(
                "[ChromaDB 1,000 Doc Profile] "
                f"Query Time: {duration:.4f}s | "
                f"Peak Memory: {peak / 1024 / 1024:.2f} MB"
            )
        finally:
            tracemalloc.stop()

    def test_02_groq_timeout_fallback_simulation(self) -> None:
        stdout = io.StringIO()
        with (
            patch("sys.stdin", io.StringIO(json.dumps({
                "model": "test-model",
                "messages": [{"role": "user", "content": "test prompt"}],
            }))),
            patch(
                "llm_provider_fallback.call_groq",
                side_effect=ProviderTimeout("simulated 504 timeout"),
            ) as groq_call,
            patch(
                "llm_provider_fallback.call_openrouter",
                return_value="fallback response",
            ) as openrouter_call,
            redirect_stdout(stdout),
        ):
            exit_code = main()

        response = json.loads(stdout.getvalue())
        self.assertEqual(exit_code, 0)
        self.assertEqual(response["status"], "ok")
        self.assertEqual(response["provider"], "openrouter")
        self.assertEqual(response["content"], "fallback response")
        groq_call.assert_called_once()
        openrouter_call.assert_called_once()
        self.assertNotIn("Traceback", stdout.getvalue())

        timeout_stdout = io.StringIO()
        with (
            patch("sys.stdin", io.StringIO(json.dumps({
                "messages": [{"role": "user", "content": "test prompt"}],
            }))),
            patch(
                "llm_provider_fallback.call_groq",
                side_effect=ProviderTimeout("simulated 504 timeout"),
            ),
            patch(
                "llm_provider_fallback.call_openrouter",
                side_effect=ProviderTimeout("simulated fallback timeout"),
            ),
            redirect_stdout(timeout_stdout),
        ):
            timeout_exit_code = main()

        timeout_response = json.loads(timeout_stdout.getvalue())
        self.assertEqual(timeout_exit_code, 1)
        self.assertEqual(timeout_response["status"], "error")
        self.assertEqual(timeout_response["http_status"], 504)
        self.assertNotIn("Traceback", timeout_stdout.getvalue())


if __name__ == "__main__":
    unittest.main()
