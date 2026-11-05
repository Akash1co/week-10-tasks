import unittest
from pathlib import Path

from scripts.ats_scorer import extract_resume_text, validate_ats_result


def valid_result() -> dict[str, object]:
    return {
        "total_score": 82,
        "keywords": 80,
        "formatting": 90,
        "experience": 75,
        "skills": 84,
        "keyword_gaps": ["CI/CD"],
        "rewrite_suggestions": [
            {
                "section": "Experience",
                "current_issue": "Impact is not quantified.",
                "suggestion": "Add a truthful metric for the project outcome.",
            }
        ],
    }


class AtsScorerTests(unittest.TestCase):
    def test_valid_result_matches_frontend_contract(self) -> None:
        result = validate_ats_result(valid_result())

        self.assertEqual(result["total_score"], 82)
        self.assertEqual(result["keyword_gaps"], ["CI/CD"])
        self.assertEqual(result["rewrite_suggestions"][0]["section"], "Experience")

    def test_rejects_scores_outside_range_and_boolean_scores(self) -> None:
        for score in (-1, 101, True):
            with self.subTest(score=score):
                result = valid_result()
                result["skills"] = score
                with self.assertRaises(ValueError):
                    validate_ats_result(result)

    def test_rejects_malformed_suggestions(self) -> None:
        result = valid_result()
        result["rewrite_suggestions"] = [{"section": "Experience"}]

        with self.assertRaises(ValueError):
            validate_ats_result(result)

    def test_rejects_unsupported_resume_format_before_loading_docling(self) -> None:
        with self.assertRaisesRegex(ValueError, "PDF or DOCX"):
            extract_resume_text(Path("resume.txt"))


if __name__ == "__main__":
    unittest.main()
