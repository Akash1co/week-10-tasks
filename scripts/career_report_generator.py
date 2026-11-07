"""Generate a deterministic six-archetype career report and a compact PDF."""

import base64
import json
import sys
from typing import Any

from llm_provider_fallback import ProviderUnavailable, complete_with_fallback


ARCHETYPES: dict[str, list[str]] = {
    "Backend Developer": ["Python", "Django", "REST APIs", "PostgreSQL", "Git"],
    "Frontend Developer": ["JavaScript", "React", "TypeScript", "HTML/CSS", "Accessibility"],
    "Full Stack Developer": ["JavaScript", "React", "Node.js", "HTML/CSS", "REST APIs"],
    "Data Specialist": ["Python", "SQL", "Statistics", "Pandas", "Data Visualization"],
    "DevOps Engineer": ["Linux", "Docker", "Kubernetes", "CI/CD", "AWS"],
    "AI/ML Engineer": ["Python", "Machine Learning", "TensorFlow", "APIs", "Vector Databases"],
}


def _pdf_escape(value: str) -> str:
    return value.encode("ascii", "replace").decode("ascii").replace("\\", "\\\\").replace("(", "\\(").replace(")", "\\)")


def render_pdf(lines: list[str]) -> bytes:
    """Render a one-page, dependency-free PDF; long text is wrapped before drawing."""
    wrapped: list[str] = []
    for line in lines:
        wrapped.extend(
            line[index:index + 88]
            for index in range(0, max(1, len(line)), 88)
        )
    text_commands = ["BT", "/F1 10 Tf", "50 750 Td", "14 TL"]
    for line in wrapped[:48]:
        text_commands.extend([f"({_pdf_escape(line)}) Tj", "T*"])
    text_commands.append("ET")
    stream = "\n".join(text_commands).encode("ascii")
    objects = [
        b"<< /Type /Catalog /Pages 2 0 R >>",
        b"<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
        b"<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>",
        b"<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
        b"<< /Length " + str(len(stream)).encode("ascii") + b" >>\nstream\n" + stream + b"\nendstream",
    ]
    document = bytearray(b"%PDF-1.4\n")
    offsets = [0]
    for index, content in enumerate(objects, start=1):
        offsets.append(len(document))
        document.extend(f"{index} 0 obj\n".encode("ascii") + content + b"\nendobj\n")
    xref_offset = len(document)
    document.extend(f"xref\n0 {len(objects) + 1}\n".encode("ascii"))
    document.extend(b"0000000000 65535 f \n")
    for offset in offsets[1:]:
        document.extend(f"{offset:010d} 00000 n \n".encode("ascii"))
    document.extend(
        f"trailer\n<< /Size {len(objects) + 1} /Root 1 0 R >>\n"
        f"startxref\n{xref_offset}\n%%EOF\n".encode("ascii")
    )
    return bytes(document)


def generate_report(profile: dict[str, Any]) -> dict[str, Any]:
    raw_skills = profile.get("skills", [])
    if not isinstance(raw_skills, list) or any(not isinstance(skill, str) for skill in raw_skills):
        raise ValueError("skills must be an array of strings.")
    candidate_skills = {" ".join(skill.casefold().split()) for skill in raw_skills if skill.strip()}

    scores = []
    for archetype, required_skills in ARCHETYPES.items():
        matched = [
            skill for skill in required_skills
            if " ".join(skill.casefold().split()) in candidate_skills
        ]
        scores.append({
            "archetype": archetype,
            "score": round(len(matched) / len(required_skills) * 100, 1),
            "matched_skills": matched,
            "skill_gaps": [skill for skill in required_skills if skill not in matched],
        })
    scores.sort(key=lambda item: (-item["score"], item["archetype"]))

    target_role = profile.get("target_role", "your target role")
    if not isinstance(target_role, str) or not target_role.strip():
        raise ValueError("target_role must be a non-empty string.")
    report: dict[str, Any] = {
        "title": f"Career Intelligence Report: {target_role.strip()}",
        "summary": (
            f"Compared the supplied skills against six career archetypes. "
            f"The closest match is {scores[0]['archetype']} at {scores[0]['score']}%."
        ),
        "archetypes": scores,
    }
    try:
        content = complete_with_fallback([
            {
                "role": "system",
                "content": "Return one JSON object with a concise string field named summary. "
                "Use only the supplied archetype scores; do not invent qualifications.",
            },
            {"role": "user", "content": json.dumps(report, ensure_ascii=False)},
        ])
    except ProviderUnavailable:
        content = ""
    if content:
        try:
            summary = json.loads(content).get("summary")
        except (json.JSONDecodeError, AttributeError):
            summary = None
        if isinstance(summary, str) and summary.strip():
            report["summary"] = summary.strip()

    pdf_lines = [report["title"], "", report["summary"], "", "Archetype scores:"]
    pdf_lines.extend(
        f"{item['archetype']}: {item['score']}% - gaps: {', '.join(item['skill_gaps']) or 'none'}"
        for item in scores
    )
    report["pdf_base64"] = base64.b64encode(render_pdf(pdf_lines)).decode("ascii")
    return report


def main() -> int:
    profile = json.load(sys.stdin)
    if not isinstance(profile, dict):
        raise ValueError("Input must be a JSON object.")
    sys.stdout.write(json.dumps(generate_report(profile), ensure_ascii=False) + "\n")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (json.JSONDecodeError, OSError, RuntimeError, ValueError) as error:
        print(json.dumps({"status": "error", "message": str(error)}), file=sys.stderr)
        raise SystemExit(1) from error
