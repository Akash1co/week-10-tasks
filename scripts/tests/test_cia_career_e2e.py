"""Integration checks for CIA Career Intelligence modules and provider failover."""

import json
import base64
import os
import re
import shutil
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path
from unittest import mock
from typing import Any

import chromadb


class TestCiaCareerE2E(unittest.TestCase):
    def setUp(self) -> None:
        self.root_dir = Path(__file__).resolve().parents[2]
        self.chroma_dir = self.root_dir / "storage" / "chroma_db"
        self.dataset_path = self.root_dir / "scripts" / "data" / "roles_dataset.json"
        self.assertTrue(self.dataset_path.is_file(), f"Missing role dataset: {self.dataset_path}")
        self.chroma_dir.mkdir(parents=True, exist_ok=True)
        self.assertTrue(self.chroma_dir.is_dir(), f"Missing persistent store: {self.chroma_dir}")

    def _dataset_roles(self) -> list[dict[str, Any]]:
        roles = json.loads(self.dataset_path.read_text(encoding="utf-8"))
        required_fields = {"id", "role", "required_skills", "match_criteria"}
        self.assertIsInstance(roles, list)
        self.assertEqual(len(roles), 20)
        for role in roles:
            self.assertIsInstance(role, dict)
            self.assertTrue(required_fields.issubset(role))
            self.assertTrue(role["id"])
            self.assertTrue(role["role"])
            self.assertIsInstance(role["required_skills"], list)
            self.assertTrue(role["required_skills"])
            self.assertTrue(role["match_criteria"])
        self.assertEqual(len({role["id"] for role in roles}), 20)
        return roles

    def test_01_chromadb_persistence_and_seeding(self) -> None:
        roles = self._dataset_roles()
        client = chromadb.PersistentClient(path=str(self.chroma_dir))
        collection = client.get_or_create_collection(
            name="internship_roles",
            metadata={"description": "CIA internship role profiles", "hnsw:space": "cosine"},
        )

        if collection.count() < 20:
            existing_ids = set(collection.get()["ids"])
            missing_roles = [role for role in roles if role["id"] not in existing_ids]
            if missing_roles:
                collection.upsert(
                    ids=[role["id"] for role in missing_roles],
                    documents=[
                        f"{role['role']}. Required skills: "
                        f"{', '.join(role['required_skills'])}. {role['match_criteria']}"
                        for role in missing_roles
                    ],
                    metadatas=[
                        {
                            "role": role["role"],
                            "required_skills": json.dumps(role["required_skills"], ensure_ascii=False),
                            "match_criteria": role["match_criteria"],
                        }
                        for role in missing_roles
                    ],
                )

        self.assertGreaterEqual(collection.count(), 20)
        reopened = chromadb.PersistentClient(path=str(self.chroma_dir)).get_collection(
            "internship_roles"
        )
        self.assertGreaterEqual(reopened.count(), 20)

    def test_02_vector_internship_matcher_script(self) -> None:
        self._dataset_roles()
        skills = ["PHP", "Laravel", "JavaScript", "MySQL", "Git"]
        process = subprocess.run(
            [
                sys.executable,
                str(self.root_dir / "scripts" / "internship_matcher.py"),
                json.dumps(skills),
            ],
            capture_output=True,
            check=False,
            cwd=self.root_dir,
            env={
                **os.environ,
                "CIA_INTERNSHIP_CHROMA_DIRECTORY": str(self.chroma_dir),
            },
            text=True,
            timeout=120,
        )

        self.assertEqual(process.returncode, 0, process.stderr)
        result = json.loads(process.stdout)
        self.assertEqual(result["status"], "success")
        self.assertEqual(result["total_matched"], 5)
        self.assertEqual(len(result["matches"]), 5)
        for match in result["matches"]:
            self.assertIsInstance(match["match_percentage"], float)
            self.assertGreaterEqual(match["match_percentage"], 0.0)
            self.assertLessEqual(match["match_percentage"], 100.0)
            self.assertIsInstance(match["skill_gaps"], list)

    def test_03_groq_and_openrouter_fallback_resilience(self) -> None:
        scripts_dir = self.root_dir / "scripts"
        sys.path.insert(0, str(scripts_dir))
        try:
            from llm_provider_fallback import (
                ProviderRejected,
                ProviderUnavailable,
                complete_with_fallback,
            )
        finally:
            sys.path.remove(str(scripts_dir))

        messages = [{"role": "user", "content": "return JSON"}]
        for primary_failure in (
            ProviderUnavailable("request timed out"),
            ProviderUnavailable("Groq returned HTTP 503"),
        ):
            with self.subTest(primary_failure=primary_failure), mock.patch(
                "llm_provider_fallback._post_chat_completion",
                side_effect=[primary_failure, '{"summary":"secondary response"}'],
            ) as request:
                response = complete_with_fallback(
                    messages,
                    groq_api_key="test-groq-key",
                    openrouter_api_key="test-openrouter-key",
                )

            self.assertEqual(response, '{"summary":"secondary response"}')
            self.assertEqual(
                [call.args[0] for call in request.call_args_list],
                ["groq", "openrouter"],
            )

        with mock.patch(
            "llm_provider_fallback._post_chat_completion",
            side_effect=ProviderRejected("invalid Groq credentials"),
        ) as request:
            with self.assertRaises(ProviderRejected):
                complete_with_fallback(
                    messages,
                    groq_api_key="invalid-key",
                    openrouter_api_key="test-openrouter-key",
                )
        request.assert_called_once()

        controller = (self.root_dir / "app" / "Http" / "Controllers" / "CiaController.php").read_text(
            encoding="utf-8"
        )
        fallback_source = (scripts_dir / "llm_provider_fallback.py").read_text(encoding="utf-8")
        self.assertIn("OpenRouter", controller)
        self.assertIn("serverError()", controller)
        self.assertIn("catch (ConnectionException", controller)
        self.assertIn("GROQ_API_KEY", fallback_source)
        self.assertIn("OPENROUTER_API_KEY", fallback_source)

    def test_04_all_seven_modules_endpoint_coverage(self) -> None:
        routes_path = self.root_dir / "routes" / "web.php"
        controller_path = self.root_dir / "app" / "Http" / "Controllers" / "CiaController.php"
        routes = routes_path.read_text(encoding="utf-8")
        controller = controller_path.read_text(encoding="utf-8")
        endpoints = [
            ("post", "/candidate/cia/career/ats-score", "atsScore"),
            ("post", "/candidate/cia/career/readiness-scores", "readinessScores"),
            ("post", "/candidate/cia/career/generate-report", "generateReport"),
            ("post", "/candidate/cia/career/match-internships", "matchInternships"),
            ("post", "/candidate/cia/career/skill-gap", "skillGap"),
            ("post", "/candidate/cia/career/learning-path", "learningPath"),
            ("get", "/candidate/cia/career/reports", "reports"),
            ("get", "/candidate/cia/career/reports/{id}/download", "downloadReport"),
        ]

        for method, path, action in endpoints:
            with self.subTest(path=path):
                route_pattern = (
                    rf"Route::{method}\(\s*'{re.escape(path)}'\s*,\s*"
                    rf"\[CiaController::class,\s*'{action}'\]\s*\)"
                    rf"(?:(?!Route::).)*?->middleware\(\['auth'\]\)"
                )
                self.assertRegex(routes, re.compile(route_pattern, re.DOTALL))
                self.assertRegex(controller, rf"function\s+{action}\s*\(")

        self.assertIn("->whereNumber('id')", routes)
        self.assertIn("->where('candidate_id', $candidateId)", controller)
        self.assertIn("->where('id', $id)", controller)
        self.assertIn("if ($candidateScores === [] || $targetScores === [])", controller)
        self.assertRegex(controller, r"\$targetScore > 0\s*\?")

    def test_05_resume_text_extraction_rejects_empty_buffers(self) -> None:
        scripts_dir = self.root_dir / "scripts"
        sys.path.insert(0, str(scripts_dir))
        try:
            import resume_ats_scorer
        finally:
            sys.path.remove(str(scripts_dir))

        class EmptyTextPage:
            def get_text_range(self) -> str:
                return ""

            def close(self) -> None:
                return None

        class EmptyPdfPage:
            def get_textpage(self) -> EmptyTextPage:
                return EmptyTextPage()

            def close(self) -> None:
                return None

        class EmptyPdfDocument:
            def __iter__(self) -> Any:
                return iter([EmptyPdfPage()])

            def close(self) -> None:
                return None

        with mock.patch.object(
            resume_ats_scorer.pdfium, "PdfDocument", return_value=EmptyPdfDocument()
        ):
            with self.assertRaisesRegex(ValueError, "does not contain extractable text"):
                resume_ats_scorer.extract_pdf_text("unreadable.pdf")

    def test_06_career_report_has_six_archetypes_and_valid_pdf(self) -> None:
        scripts_dir = self.root_dir / "scripts"
        sys.path.insert(0, str(scripts_dir))
        try:
            import career_report_generator
            import resume_ats_scorer
        finally:
            sys.path.remove(str(scripts_dir))

        with mock.patch.dict(os.environ, {"GROQ_API_KEY": "", "OPENROUTER_API_KEY": ""}):
            report = career_report_generator.generate_report(
                {
                    "target_role": "Full Stack Developer",
                    "skills": ["JavaScript", "React", "Node.js"],
                }
            )
        self.assertEqual(len(report["archetypes"]), 6)
        self.assertTrue(all(0 <= item["score"] <= 100 for item in report["archetypes"]))

        with tempfile.TemporaryDirectory() as directory:
            pdf_path = Path(directory) / "career-report.pdf"
            pdf_path.write_bytes(base64.b64decode(report["pdf_base64"]))
            extracted_text = resume_ats_scorer.extract_pdf_text(str(pdf_path))
        self.assertIn(report["title"], extracted_text)
        self.assertIn("Archetype scores:", extracted_text)

    def test_07_skill_gap_comparison_normalizes_case_and_whitespace(self) -> None:
        php = shutil.which("php")
        if php is None:
            self.skipTest("PHP CLI is not installed.")
        php_code = r"""
function base_path($path = '') {
    return getcwd() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
}
require getcwd() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . 'CiaServiceException.php';
require getcwd() . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Services' . DIRECTORY_SEPARATOR . 'CiaCareerService.php';
$result = (new \App\Services\CiaCareerService())->compareSkills(
    ['javascript', ' React ', 'Node.js', 'html/css', 'REST   APIs'],
    ' full stack developer '
);
echo json_encode($result, JSON_THROW_ON_ERROR);
"""
        process = subprocess.run(
            [php, "-r", php_code],
            capture_output=True,
            check=False,
            cwd=self.root_dir,
            text=True,
            timeout=30,
        )
        self.assertEqual(process.returncode, 0, process.stderr)
        result = json.loads(process.stdout)
        self.assertEqual(
            result["matched_skills"],
            ["JavaScript", "React", "Node.js", "HTML/CSS", "REST APIs"],
        )
        self.assertEqual(result["skill_gaps"], [])


if __name__ == "__main__":
    unittest.main()
