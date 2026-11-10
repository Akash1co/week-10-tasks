"""Performance and provider failover checks for CIA Career Intelligence."""

import os
import sys
import time
import tracemalloc
import unittest
from pathlib import Path
from unittest.mock import patch

import chromadb


SCRIPT_DIRECTORY = Path(__file__).resolve().parents[1]
PROJECT_ROOT = SCRIPT_DIRECTORY.parent
CHROMA_DIRECTORY = PROJECT_ROOT / "storage" / "chroma_db"
sys.path.insert(0, str(SCRIPT_DIRECTORY))

from llm_provider_fallback import ProviderFailure, complete_with_fallback  # noqa: E402


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
        with (
            patch.dict(os.environ, {
                "GROQ_API_KEY": "test-groq-key",
                "OPENROUTER_API_KEY": "test-openrouter-key",
            }),
            patch(
                "llm_provider_fallback.call_groq",
                side_effect=ProviderFailure("simulated 504 timeout", 504),
            ) as groq_call,
            patch(
                "llm_provider_fallback.call_openrouter",
                return_value="fallback response",
            ) as openrouter_call,
        ):
            response = complete_with_fallback("test prompt")

        self.assertEqual(response["status"], "success")
        self.assertEqual(response["provider"], "openrouter")
        self.assertEqual(response["data"]["response"], "fallback response")
        groq_call.assert_called_once()
        openrouter_call.assert_called_once()

        with (
            patch.dict(os.environ, {
                "GROQ_API_KEY": "test-groq-key",
                "OPENROUTER_API_KEY": "test-openrouter-key",
            }),
            patch(
                "llm_provider_fallback.call_groq",
                side_effect=ProviderFailure("simulated 504 timeout", 504),
            ),
            patch(
                "llm_provider_fallback.call_openrouter",
                side_effect=ProviderFailure("simulated fallback timeout", 504),
            ),
        ):
            timeout_response = complete_with_fallback("test prompt")

        self.assertEqual(timeout_response["status"], "error")
        self.assertEqual(timeout_response["code"], 504)


if __name__ == "__main__":
    unittest.main()
