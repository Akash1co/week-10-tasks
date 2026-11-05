"""Rank internship roles against a candidate's skill profile using ChromaDB."""

import json
import math
import sys
from pathlib import Path
from typing import Any

import chromadb


PROJECT_ROOT = Path(__file__).resolve().parents[1]
DATASET_PATH = PROJECT_ROOT / "scripts" / "data" / "roles_dataset.json"
COLLECTION_NAME = "internship_roles"
PERSIST_DIRECTORY = Path("./storage/chroma_db")


def _normalized_skill(skill: str) -> str:
    return " ".join(skill.casefold().split())


def _load_roles() -> list[dict[str, Any]]:
    with DATASET_PATH.open(encoding="utf-8") as dataset_file:
        roles = json.load(dataset_file)

    if not isinstance(roles, list) or len(roles) != 20:
        raise ValueError("The internship role dataset must contain exactly 20 profiles.")

    required_fields = {"id", "role", "required_skills", "match_criteria"}
    for role in roles:
        if (
            not isinstance(role, dict)
            or not required_fields.issubset(role)
            or not isinstance(role["id"], str)
            or not isinstance(role["role"], str)
            or not isinstance(role["required_skills"], list)
            or not role["required_skills"]
            or any(not isinstance(skill, str) or not skill.strip() for skill in role["required_skills"])
            or not isinstance(role["match_criteria"], str)
            or not role["match_criteria"].strip()
        ):
            raise ValueError("The internship role dataset contains an invalid profile.")

    if len({role["id"] for role in roles}) != 20:
        raise ValueError("The internship role dataset must contain 20 unique IDs.")

    return roles


def _get_roles_collection() -> Any:
    client = chromadb.PersistentClient(path=str(PERSIST_DIRECTORY))
    collection = client.get_or_create_collection(
        name=COLLECTION_NAME,
        metadata={"description": "CIA internship role profiles", "hnsw:space": "cosine"},
    )

    if collection.count() < 20:
        roles = _load_roles()
        collection.upsert(
            ids=[role["id"] for role in roles],
            documents=[
                (
                    f"{role['role']}. Required skills: "
                    f"{', '.join(role['required_skills'])}. {role['match_criteria']}"
                )
                for role in roles
            ],
            metadatas=[
                {
                    "role": role["role"],
                    "required_skills": json.dumps(role["required_skills"], ensure_ascii=False),
                    "match_criteria": role["match_criteria"],
                }
                for role in roles
            ],
        )

    if collection.count() < 20:
        raise RuntimeError("The internship role collection could not be seeded completely.")

    return collection


def match_internships(skills: list[str]) -> dict[str, Any]:
    """Return five ranked roles, normalized scores, and skill gaps."""
    candidate_skills = [skill.strip() for skill in skills if skill.strip()]
    if not candidate_skills:
        raise ValueError("skills must contain at least one non-empty string.")

    candidate_skill_keys = {_normalized_skill(skill) for skill in candidate_skills}
    collection = _get_roles_collection()
    results = collection.query(
        query_texts=["Candidate skills: " + ", ".join(candidate_skills)],
        n_results=5,
        include=["metadatas", "distances"],
    )

    ids = results["ids"][0]
    metadatas = results["metadatas"][0]
    distances = results["distances"][0]
    matches: list[dict[str, Any]] = []

    for role_id, metadata, distance in zip(ids, metadatas, distances, strict=True):
        if metadata is None or distance is None:
            raise RuntimeError("ChromaDB returned incomplete role match data.")

        required_skills = json.loads(metadata["required_skills"])
        distance_value = float(distance)
        if not math.isfinite(distance_value):
            raise RuntimeError("ChromaDB returned a non-finite distance.")

        missing_skills = [
            skill
            for skill in required_skills
            if _normalized_skill(skill) not in candidate_skill_keys
        ]
        match_percentage = max(0, min(100, round((1 - distance_value) * 100, 2)))
        matches.append(
            {
                "role_id": role_id,
                "role": metadata["role"],
                "match_percentage": match_percentage,
                "required_skills": required_skills,
                "missing_skills": missing_skills,
                "match_criteria": metadata["match_criteria"],
            }
        )

    if len(matches) != 5:
        raise RuntimeError(f"Expected five ranked role results, received {len(matches)}.")

    return {
        "status": "success",
        "total_matched": len(matches),
        "candidate_skills": candidate_skills,
        "matches": matches,
    }


def main() -> int:
    if len(sys.argv) != 2:
        raise ValueError("Pass the candidate skill profile as a JSON argument.")

    candidate_profile = json.loads(sys.argv[1])
    if not isinstance(candidate_profile, dict):
        raise ValueError("Candidate skill profile must be a JSON object.")

    skills = candidate_profile.get("skills")
    if (
        not isinstance(skills, list)
        or not skills
        or any(not isinstance(skill, str) or not skill.strip() for skill in skills)
    ):
        raise ValueError("skills must be a non-empty array of non-empty strings.")

    result = match_internships(skills)
    sys.stdout.write(json.dumps(result, ensure_ascii=False) + "\n")
    return 0


if __name__ == "__main__":
    try:
        raise SystemExit(main())
    except (json.JSONDecodeError, OSError, RuntimeError, ValueError) as error:
        print(json.dumps({"status": "error", "message": str(error)}), file=sys.stderr)
        raise SystemExit(1) from error
