# CIA Career Intelligence — Five-Minute Demo Script

## Before the demo

This checkout contains CIA feature files but no Laravel `artisan`,
`composer.json`, or `.env.example`; the authenticated pages therefore require
the host Laravel application to be installed and to mount the Blade view.
Use the Python commands below from the repository root with its configured
environment. Never display provider keys. Seed ChromaDB before the demo; its
embedding model may need to download on first use, so complete that step
ahead of time and have the expected output saved as a local screenshot.

For browser API calls, set `$base`, `$session`, and `$csrf` to values from the
already-authenticated host application:

```powershell
$headers = @{
    Accept = 'application/json'
    'Content-Type' = 'application/json'
    'X-CSRF-TOKEN' = $csrf
}
```

## 0:00–1:00 — Workspace and skill-gap analysis (Module 1)

**Show:** the CIA career tab: skill-gap, recommendations, learning-path, and
role-matching forms. Enter `Full Stack Developer` and submit skill-gap
analysis.

**Endpoint:** `POST /candidate/cia/career/skill-gap`

```powershell
Invoke-RestMethod "$base/candidate/cia/career/skill-gap" -Method Post `
  -WebSession $session -Headers $headers `
  -Body (@{ target_role = 'Full Stack Developer' } | ConvertTo-Json)
```

**Expected:** JSON with `score` (0–100), `gaps`, `priority_learning`, and
`radar_data`. The actual score depends on the signed-in candidate's completed
lessons and quiz attempts. The view renders a radar chart when Chart.js loads.
Explain that curriculum tracking itself is not implemented in this slice.

## 1:00–2:00 — Multi-agent learning path (Module 2)

**Show:** an eight-week plan, including its validation result and Mermaid
timeline. To demonstrate the deterministic provider-independent path:

```powershell
$env:GROQ_API_KEY = ''
'{"target_role":"Full Stack Developer","skills":["PHP"],"skill_gaps":["Laravel"],"weekly_hours":7}' |
  .\.venv\Scripts\python.exe .\scripts\langgraph_learning_path.py
```

**Expected:** a JSON summary, eight weeks with seven days each, and a
`fallback_nodes` list. When the configured provider is available, the same
five graph stages use structured model output; malformed or failed node
responses use the deterministic fallback.

## 2:00–2:40 — ChromaDB role matching (Module 3)

**Prepare beforehand:** set `CIA_CHROMA_PERSIST_DIRECTORY` to the same
directory for both commands, then run:

```powershell
$env:CIA_CHROMA_PERSIST_DIRECTORY = "$PWD\storage\app\chromadb"
.\.venv\Scripts\python.exe .\scripts\seed_chromadb_roles.py
'{"skills":["Python","SQL","Data Visualization"]}' |
  .\.venv\Scripts\python.exe .\scripts\vector_search_roles.py
```

**Expected:** five ranked matches with similarity, required skills, and
missing skills. First-time embedding-model downloads can be slow; show a
previously captured result if setup or the live query stalls. Describe this
as semantic role matching, not as a full RAG answer system.

## 2:40–3:10 — Milestone and rubric evaluation (Module 4)

**Show:** the skill-gap inputs and the `QuizAttempt`/completed-lesson reads
used to derive candidate signals. State clearly that rubric-based code
grading and milestone evaluation are not implemented. Do not present quiz
averages as automated rubric scores.

## 3:10–3:40 — Git workflow and PR verification (Module 5)

**Show:** the repository's branch/commit workflow in a prepared slide or
repository history. There is no in-app Git/PR simulator in this checkout, so
do not imply that a submitted PR is automatically verified by CIA.

## 3:40–4:20 — Student/admin analytics (Module 6)

**Endpoints:** authenticated `GET /cia/career/dashboard` and
`GET /cia/career/analytics`.

**Expected in this checkout:** placeholder JSON messages, not populated
metrics. Contrast those stubs with the candidate-specific readiness and radar
response from the first minute. If the audience expects a live dashboard,
use a clearly labelled design mock rather than fabricated data.

## 4:20–5:00 — Portfolio export and close (Module 7)

**Show:** the case study and this architecture guide. There is no portfolio
export endpoint or generated capstone bundle in the inspected code. Close by
summarizing the implemented vertical slice, its explicit failure boundaries,
and the next engineering work: rubric evaluation, PR verification, real
analytics, and a portfolio export pipeline.

## Slow-service fallback

If a model call is slow or unavailable, use the learning-path deterministic
mode above and explain that CrewAI recommendations do not currently have an
equivalent offline fallback. If ChromaDB is unavailable, show the saved
five-result capture and explain the required seed/persistent-store setup.
Never describe a cached screenshot as a live response.
