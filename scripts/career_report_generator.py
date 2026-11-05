import json
import os
import sys
from typing import Any

from langchain_core.messages import HumanMessage, SystemMessage
from langchain_groq import ChatGroq
from pydantic import BaseModel, ConfigDict, Field


class StrictModel(BaseModel):
    model_config = ConfigDict(extra="forbid")


class ArchetypeScores(StrictModel):
    Builder: float = Field(ge=0, le=100)
    Analyst: float = Field(ge=0, le=100)
    Leader: float = Field(ge=0, le=100)
    Innovator: float = Field(ge=0, le=100)
    Specialist: float = Field(ge=0, le=100)
    Generalist: float = Field(ge=0, le=100)


class CareerReport(StrictModel):
    archetype_scores: ArchetypeScores
    overall_employability_score: float = Field(ge=0, le=100)
    narrative_summary: str = Field(min_length=30, max_length=4000)
    recommendations: list[str] = Field(min_length=3, max_length=10)


def build_report(candidate_data: dict[str, Any]) -> CareerReport:
    api_key = os.getenv("GROQ_API_KEY", "").strip()
    if not api_key:
        raise ValueError("GROQ_API_KEY is not configured.")

    model = os.getenv("GROQ_MODEL", "llama-3.3-70b-versatile").strip()
    structured_model = ChatGroq(
        model=model,
        api_key=api_key,
        temperature=0.2,
    ).with_structured_output(CareerReport)
    result = structured_model.invoke(
        [
            SystemMessage(
                content=(
                    "You are a fair, evidence-based early-career coach. Analyze only "
                    "the supplied candidate profile; do not infer credentials or "
                    "experience that are not present. Rate each career archetype from "
                    "0 to 100: Builder (implementation and delivery), Analyst "
                    "(reasoning and data), Leader (communication and ownership), "
                    "Innovator (experimentation and creativity), Specialist (depth "
                    "in a focused domain), and Generalist (breadth and adaptability). "
                    "Estimate overall employability from the available evidence, "
                    "explain strengths and growth areas in a balanced narrative, and "
                    "give at least three concrete, prioritized actions. Scores are "
                    "indicative coaching guidance, not hiring decisions."
                )
            ),
            HumanMessage(
                content=json.dumps(candidate_data, ensure_ascii=False, allow_nan=False)
            ),
        ]
    )

    if isinstance(result, CareerReport):
        return result
    if isinstance(result, dict):
        return CareerReport.model_validate(result)
    raise ValueError("Groq returned an unsupported structured response.")


def main() -> int:
    candidate_data = json.load(sys.stdin)
    if not isinstance(candidate_data, dict):
        raise ValueError("Input must be a JSON object.")

    report = build_report(candidate_data)
    sys.stdout.write(
        json.dumps(report.model_dump(), ensure_ascii=False, allow_nan=False) + "\n"
    )
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except Exception as error:
        print(f"Career report generation failed: {error}", file=sys.stderr)
        raise SystemExit(1) from error
