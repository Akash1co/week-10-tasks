"""Run repository-local CIA capstone integrity and offline behavior checks."""

from __future__ import annotations

import json
import logging
import os
import shutil
import subprocess
import sys
import tempfile
import time
from pathlib import Path
from unittest.mock import patch


ROOT = Path(__file__).resolve().parents[1]
SCRIPTS = ROOT / "scripts"
REQUIRED_FILES = [
    "routes/web.php",
    "app/Http/Controllers/CiaController.php",
    "database/migrations/2026_10_28_000000_create_cia_search_logs_table.php",
    "database/migrations/2026_11_09_000000_add_indexes_to_cia_search_logs_table.php",
    "scripts/vector_search_roles.py",
    "scripts/career_recommendations.py",
    "scripts/langgraph_learning_path.py",
    "scripts/llm_provider_fallback.py",
    "scripts/seed_chromadb_roles.py",
    "scripts/role_profiles.py",
    "docs/api-contracts-cia-career.md",
    "docs/architecture_cia_career_w12d2.md",
    "docs/CHANGELOG_CIA_CAREER.md",
    "docs/PORTFOLIO_CASE_STUDY_CIA_CAREER.md",
    "docs/DEMO_SCRIPT_CIA_CAREER.md",
]
ROUTE_ACTIONS = {
    "skill-gap": ("skillGap", "scripts/llm_provider_fallback.py"),
    "recommendations": ("careerRecommendations", "scripts/career_recommendations.py"),
    "learning-path": ("learningPath", "scripts/langgraph_learning_path.py"),
    "vector-search": ("vectorSearch", "scripts/vector_search_roles.py"),
}
CHECKS: list[tuple[str, str, float]] = []


def record(label: str, status: str, duration: float, detail: str = "") -> None:
    CHECKS.append((label, status, duration))
    suffix = f" - {detail}" if detail else ""
    print(f"[{status}] {label} ({duration:.2f}s){suffix}")


def run_command(
    command: list[str],
    *,
    input_text: str | None = None,
    env: dict[str, str] | None = None,
    timeout: int = 90,
) -> subprocess.CompletedProcess[str]:
    return subprocess.run(
        command,
        cwd=ROOT,
        input=input_text,
        capture_output=True,
        check=False,
        env=env,
        text=True,
        timeout=timeout,
    )


def check_files() -> bool:
    missing = []
    empty = []
    for relative_path in REQUIRED_FILES:
        path = ROOT / relative_path
        if not path.is_file():
            missing.append(relative_path)
        elif path.stat().st_size == 0:
            empty.append(relative_path)
    if missing or empty:
        problems = []
        if missing:
            problems.append("missing: " + ", ".join(missing))
        if empty:
            problems.append("empty: " + ", ".join(empty))
        record("Core files and documentation", "FAIL", 0.0, "; ".join(problems))
        return False
    record("Core files and documentation", "PASS", 0.0, f"{len(REQUIRED_FILES)} non-empty files")
    return True


def check_route_wiring() -> bool:
    started = time.perf_counter()
    routes = (ROOT / "routes/web.php").read_text(encoding="utf-8")
    controller = (ROOT / "app/Http/Controllers/CiaController.php").read_text(encoding="utf-8")
    missing = []
    for route_name, (method, script) in ROUTE_ACTIONS.items():
        if route_name not in routes or f"'{method}'" not in routes:
            missing.append(f"route {route_name}")
        if f"function {method}(" not in controller:
            missing.append(f"controller action {method}")
        if f"scripts/{Path(script).name}" not in controller:
            missing.append(f"process target {script}")
    duration = time.perf_counter() - started
    if missing:
        record("Controller-backed route wiring", "FAIL", duration, ", ".join(missing))
        return False
    record("Controller-backed route wiring", "PASS", duration, "4 AI POST actions mapped")
    return True


def check_php_syntax() -> bool | None:
    php = shutil.which("php")
    if php is None:
        record("PHP route/controller syntax", "WARN", 0.0, "php executable not found")
        return None

    started = time.perf_counter()
    targets = [ROOT / "routes/web.php", ROOT / "app/Http/Controllers/CiaController.php"]
    for target in targets:
        result = run_command([php, "-l", str(target)], timeout=30)
        if result.returncode != 0:
            record(
                "PHP route/controller syntax",
                "FAIL",
                time.perf_counter() - started,
                result.stderr.strip() or result.stdout.strip(),
            )
            return False
    record(
        "PHP route/controller syntax",
        "PASS",
        time.perf_counter() - started,
        "2 files passed php -l",
    )
    return True


def check_python_syntax() -> bool:
    started = time.perf_counter()
    modules = sorted(SCRIPTS.glob("*.py"))
    if not modules:
        record("Python script syntax", "FAIL", 0.0, "no scripts/*.py files found")
        return False

    with tempfile.TemporaryDirectory(prefix="cia-capstone-pycache-") as cache_dir:
        env = os.environ.copy()
        env["PYTHONPYCACHEPREFIX"] = cache_dir
        result = run_command(
            [sys.executable, "-m", "py_compile", *map(str, modules)],
            env=env,
            timeout=60,
        )
    if result.returncode != 0:
        record(
            "Python script syntax",
            "FAIL",
            time.perf_counter() - started,
            result.stderr.strip() or result.stdout.strip(),
        )
        return False
    record(
        "Python script syntax",
        "PASS",
        time.perf_counter() - started,
        f"{len(modules)} scripts compiled",
    )
    return True


def check_fallback_smoke() -> bool:
    started = time.perf_counter()
    sys.path.insert(0, str(SCRIPTS))
    logging.disable(logging.CRITICAL)
    try:
        import llm_provider_fallback

        with (
            patch(
                "llm_provider_fallback.call_groq",
                side_effect=llm_provider_fallback.ProviderTimeout("simulated primary timeout"),
            ),
            patch(
                "llm_provider_fallback.call_openrouter",
                return_value="offline mocked fallback",
            ),
        ):
            result = llm_provider_fallback.complete_with_fallback(
                {"messages": [{"role": "user", "content": "smoke check"}]}
            )
    except Exception as exception:
        record("Mocked Groq-to-OpenRouter fallback", "FAIL", time.perf_counter() - started, str(exception))
        return False
    finally:
        logging.disable(logging.NOTSET)
        sys.path.remove(str(SCRIPTS))

    valid = (
        result.get("status") == "ok"
        and result.get("provider") == "openrouter"
        and result.get("telemetry", {}).get("fallback_triggered") is True
        and result.get("content") == "offline mocked fallback"
    )
    invalid_request = run_command(
        [sys.executable, str(SCRIPTS / "llm_provider_fallback.py")],
        input_text='{"messages":[]}',
        timeout=30,
    )
    try:
        invalid_result = json.loads(invalid_request.stdout)
    except json.JSONDecodeError:
        invalid_result = {}
    valid = (
        valid
        and invalid_request.returncode == 1
        and invalid_result.get("status") == "error"
        and invalid_result.get("http_status") == 422
    )
    record(
        "Mocked Groq-to-OpenRouter fallback",
        "PASS" if valid else "FAIL",
        time.perf_counter() - started,
        "mocked provider failover and 422 guard; no external request",
    )
    return valid


def check_vector_search_smoke() -> bool:
    started = time.perf_counter()
    env = os.environ.copy()
    env["HF_HUB_OFFLINE"] = "1"
    env["TRANSFORMERS_OFFLINE"] = "1"
    try:
        result = run_command(
            [sys.executable, str(SCRIPTS / "tests" / "test_vector_search_roles.py")],
            env=env,
            timeout=180,
        )
    except (OSError, subprocess.TimeoutExpired) as exception:
        record("Offline ChromaDB seed/search smoke", "WARN", time.perf_counter() - started, str(exception))
        return False

    success = result.returncode == 0
    output = result.stdout.strip().splitlines()
    errors = result.stderr.strip().splitlines()
    detail = (
        output[-1]
        if success and output
        else (errors[-1] if errors else "ChromaDB role-search test failed")
    )
    record(
        "Offline ChromaDB seed/search smoke",
        "PASS" if success else "WARN",
        time.perf_counter() - started,
        detail,
    )
    return success


def check_learning_path_smoke() -> bool:
    started = time.perf_counter()
    env = os.environ.copy()
    env["GROQ_API_KEY"] = ""
    payload = json.dumps({
        "target_role": "Full Stack Developer",
        "skills": ["PHP", "HTML"],
        "skill_gaps": ["Laravel"],
        "weekly_hours": 7,
    })
    try:
        result = run_command(
            [sys.executable, str(SCRIPTS / "langgraph_learning_path.py")],
            input_text=payload,
            env=env,
            timeout=90,
        )
        output = json.loads(result.stdout) if result.returncode == 0 else {}
    except (OSError, subprocess.TimeoutExpired, json.JSONDecodeError) as exception:
        record("Offline LangGraph learning-path smoke", "WARN", time.perf_counter() - started, str(exception))
        return False

    schedule = output.get("schedule")
    valid = (
        isinstance(schedule, list)
        and len(schedule) == 8
        and all(isinstance(week, dict) and len(week.get("days", [])) == 7 for week in schedule)
    )
    stderr_lines = result.stderr.strip().splitlines()
    detail = (
        "8 weeks / 56 days"
        if valid
        else (stderr_lines[-1] if stderr_lines else "unexpected output shape")
    )
    record(
        "Offline LangGraph learning-path smoke",
        "PASS" if valid else "WARN",
        time.perf_counter() - started,
        detail,
    )
    return valid


def main() -> int:
    print("CIA CAREER INTELLIGENCE — CAPSTONE READINESS")
    print("=" * 54)

    check_files()
    check_route_wiring()
    check_php_syntax()
    check_python_syntax()
    check_vector_search_smoke()
    check_fallback_smoke()
    check_learning_path_smoke()

    passed = sum(status == "PASS" for _, status, _ in CHECKS)
    warned = sum(status == "WARN" for _, status, _ in CHECKS)
    failed = sum(status == "FAIL" for _, status, _ in CHECKS)
    total_time = sum(duration for _, _, duration in CHECKS)
    print("-" * 54)
    print(f"Score: {passed} PASS | {warned} WARN | {failed} FAIL")
    print(f"Check time: {total_time:.2f}s")
    print("Scope: repository integrity, syntax, route wiring, and offline Python smoke checks.")
    print("Not checked: authenticated HTTP, MySQL integration/EXPLAIN, or live LLM providers.")
    return 1 if failed else 0


if __name__ == "__main__":
    raise SystemExit(main())
