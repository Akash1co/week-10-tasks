"""Tests for the persistent ChromaDB internship matching script."""

import json
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

SCRIPT_DIRECTORY = Path(__file__).resolve().parents[1]
PROJECT_ROOT = SCRIPT_DIRECTORY.parent
MATCHER_SCRIPT = SCRIPT_DIRECTORY / "internship_matcher.py"
DATASET_PATH = SCRIPT_DIRECTORY / "data" / "roles_dataset.json"


class InternshipMatcherTests(unittest.TestCase):
    def test_dataset_has_twenty_unique_complete_role_profiles(self) -> None:
        roles = json.loads(DATASET_PATH.read_text(encoding="utf-8"))
        expected_roles = [
            "Full Stack Developer",
            "Frontend Engineer",
            "Backend Developer",
            "AI/ML Integration Engineer",
            "DevOps Engineer",
            "QA Automation Engineer",
            "Data Engineer",
            "Mobile App Developer",
            "Cloud Solutions Architect",
            "Security Analyst",
            "UI/UX Developer",
            "Database Administrator",
            "API Engineer",
            "System Administrator",
            "Moodle Developer",
            "Python Developer",
            "PHP/Laravel Developer",
            "React Developer",
            "Technical Support Engineer",
            "Software Engineering Intern",
        ]

        self.assertEqual(len(roles), 20)
        self.assertEqual(len({role["id"] for role in roles}), 20)
        self.assertEqual([role["role"] for role in roles], expected_roles)
        self.assertTrue(all(role["role"] for role in roles))
        self.assertTrue(all(role["required_skills"] for role in roles))
        self.assertTrue(all(role["match_criteria"] for role in roles))

    def test_cli_seeds_collection_and_returns_five_ranked_matches(self) -> None:
        skills = ["PHP", "Laravel", "SQL", "Git"]

        with tempfile.TemporaryDirectory() as working_directory:
            process = subprocess.run(
                [sys.executable, str(MATCHER_SCRIPT), json.dumps({"skills": skills})],
                capture_output=True,
                check=True,
                cwd=working_directory,
                text=True,
            )
            result = json.loads(process.stdout)
            persist_directory = str(Path(working_directory) / "storage" / "chroma_db")
            count_process = subprocess.run(
                [
                    sys.executable,
                    "-c",
                    "import chromadb, sys; "
                    "print(chromadb.PersistentClient(path=sys.argv[1])"
                    ".get_collection('internship_roles').count())",
                    persist_directory,
                ],
                capture_output=True,
                check=True,
                text=True,
            )
            collection_count = int(count_process.stdout.strip())

        matches = result["matches"]
        self.assertEqual(collection_count, 20)
        self.assertEqual(result["status"], "success")
        self.assertEqual(result["total_matched"], 5)
        self.assertEqual(result["candidate_skills"], skills)
        self.assertEqual(len(matches), 5)
        self.assertEqual(len({match["role_id"] for match in matches}), 5)
        self.assertTrue(all(0 <= match["match_percentage"] <= 100 for match in matches))
        self.assertTrue(all(match["required_skills"] for match in matches))
        self.assertTrue(all(isinstance(match["missing_skills"], list) for match in matches))
        self.assertTrue(all(match["match_criteria"] for match in matches))

    def test_cli_rejects_invalid_candidate_profiles(self) -> None:
        process = subprocess.run(
            [sys.executable, str(MATCHER_SCRIPT), json.dumps({"skills": []})],
            capture_output=True,
            cwd=PROJECT_ROOT,
            text=True,
        )

        self.assertNotEqual(process.returncode, 0)
        self.assertEqual(json.loads(process.stderr)["status"], "error")


if __name__ == "__main__":
    unittest.main()
