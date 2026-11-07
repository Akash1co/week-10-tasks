"""Extract PDF resume text safely and compare it with a role's required skills."""

import json
import sys
from pathlib import Path
from typing import Any

import pypdfium2 as pdfium


def extract_pdf_text(path: str) -> str:
    try:
        document = pdfium.PdfDocument(path)
        text_parts = []
        try:
            for page in document:
                try:
                    text_page = page.get_textpage()
                    try:
                        text_parts.append(text_page.get_text_range())
                    finally:
                        text_page.close()
                finally:
                    page.close()
        finally:
            document.close()
    except pdfium.PdfiumError as error:
        raise ValueError("The uploaded PDF could not be read.") from error
    text = "\n".join(text_parts).strip()
    if not text:
        raise ValueError("The uploaded PDF does not contain extractable text.")
    return text


def score_resume(payload: dict[str, Any]) -> dict[str, Any]:
    pdf_path = payload.get("pdf_path")
    if not isinstance(pdf_path, str) or not Path(pdf_path).is_file():
        raise ValueError("A readable PDF path is required.")
    resume_text = extract_pdf_text(pdf_path)
    target_role = payload.get("target_role")
    required_skills = payload.get("required_skills")
    if not isinstance(target_role, str) or not target_role.strip():
        raise ValueError("target_role is required.")
    if (
        not isinstance(required_skills, list)
        or not required_skills
        or any(not isinstance(skill, str) or not skill.strip() for skill in required_skills)
    ):
        raise ValueError("required_skills must be a non-empty array of strings.")

    normalized_text = " ".join(resume_text.casefold().split())
    matched = [skill for skill in required_skills if skill.casefold() in normalized_text]
    gaps = [skill for skill in required_skills if skill not in matched]
    return {
        "status": "success",
        "target_role": target_role.strip(),
        "score": round(len(matched) / len(required_skills) * 100, 1),
        "matched_skills": matched,
        "skill_gaps": gaps,
        "extracted_characters": len(resume_text),
    }


def main() -> int:
    payload = json.load(sys.stdin)
    if not isinstance(payload, dict):
        raise ValueError("Input must be a JSON object.")
    sys.stdout.write(json.dumps(score_resume(payload), ensure_ascii=False) + "\n")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (json.JSONDecodeError, OSError, RuntimeError, ValueError) as error:
        print(json.dumps({"status": "error", "message": str(error)}), file=sys.stderr)
        raise SystemExit(1) from error
