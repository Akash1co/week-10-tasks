# CIA Career Intelligence — Five-Minute Demo Script

> **Demo video recording metadata**
>
> - Video title: CIA Career Intelligence — Project 4 Capstone
> - Visibility: Unlisted
> - YouTube URL: `[PASTE_UNLISTED_YOUTUBE_DEMO_LINK_HERE]`
> - Repository branch: `feat/cia-career-w12d3-akasha`
> - Recording note: The checkout has no root `artisan` or `composer.json`.
>   Route calls below require the complete Cynaris Laravel app, a running
>   database, and a valid authenticated session. Do not present placeholder
>   routes as implemented features.

## Before recording

Run commands from the repository root in PowerShell. The local virtual
environment is `.venv\Scripts\python.exe`. For HTTP examples, set a base URL
and an authenticated session cookie from your own local app; never record or
commit real credentials.

```powershell
$Python = ".venv\Scripts\python.exe"
$BaseUrl = "http://127.0.0.1:8000"
$SessionCookie = "<YOUR_LOCAL_AUTH_SESSION_COOKIE>"
$Headers = @{ Accept = "application/json"; "Content-Type" = "application/json" }
```

Routes marked **placeholder** currently return fixed JSON and do not read
candidate data. The live commands against those routes demonstrate that
boundary, not a completed profile or analytics workflow.

## Timestamped walkthrough

### 0:00–0:40 — Architecture and route map

**Say:** “The integration point is Laravel. Four POST routes delegate to
`CiaController`; the dashboard, profile, and analytics routes are placeholders.
Python work runs out of process, and successful or failed operations can be
audited in `cia_search_logs`.”

```powershell
Get-Content routes\web.php
Select-String -Path app\Http\Controllers\CiaController.php -Pattern "function (skillGap|careerRecommendations|learningPath|vectorSearch)|setTimeout|json_decode"
Get-Content docs\architecture_cia_career_w12d2.md
```

### 0:40–1:30 — Module 1 hub (placeholder) and Module 2 semantic role search

**Say:** “The dashboard route is currently a placeholder. The implemented
search path uses ChromaDB cosine retrieval over 20 role profiles and returns
five matches.”

```powershell
# Module 1: placeholder dashboard; requires the authenticated host app.
Invoke-RestMethod -Uri "$BaseUrl/cia/career/dashboard" -Headers @{ Cookie = $SessionCookie; Accept = "application/json" }

# Module 2: seed only the default project ChromaDB directory, then search.
& $Python scripts\seed_chromadb_roles.py
@'
{"skills":["Python","SQL","Data Visualization"]}
'@ | & $Python scripts\vector_search_roles.py
```

If the ChromaDB embedding model is not cached, seeding may need model access
the first time. Do not substitute the historical 1,000-vector benchmark for
this role-matching run.

### 1:30–2:20 — Module 3 recommendations and Module 4 skill-gap scoring

**Say:** “These authenticated controller actions validate a target role and
profile fields before invoking Python or provider logic. The request examples
are payload shapes; the calls require an authenticated local application.”

```powershell
$RecommendationBody = '{"target_role":"Full Stack Developer","skills":["PHP","Laravel","SQL"],"skill_gaps":["API testing"],"profile":{"experience_level":"intern"}}'
curl.exe --fail-with-body -X POST "$BaseUrl/candidate/cia/career/recommendations" -H "Cookie: $SessionCookie" -H "Accept: application/json" -H "Content-Type: application/json" --data $RecommendationBody

$SkillGapBody = '{"target_role":"Full Stack Developer"}'
curl.exe --fail-with-body -X POST "$BaseUrl/candidate/cia/career/skill-gap" -H "Cookie: $SessionCookie" -H "Accept: application/json" -H "Content-Type: application/json" --data $SkillGapBody
```

To show the validation guard in a host app, omit `target_role`; Laravel should
return `422`. The live request may call Groq/OpenRouter if configured. Do not
record API keys or claim model output is deterministic.

### 2:20–3:15 — Module 5 LangGraph learning path

**Say:** “The graph assesses, identifies gaps, builds and validates a plan,
then summarizes it. With the Groq key removed, its deterministic node fallbacks
still produce eight weeks of seven daily topics.”

```powershell
$env:GROQ_API_KEY = ""
@'
{"target_role":"Full Stack Developer","skills":["PHP","HTML"],"skill_gaps":["Laravel","API testing"],"weekly_hours":7}
'@ | & $Python scripts\langgraph_learning_path.py | ConvertFrom-Json | ForEach-Object {
    "Weeks: $($_.schedule.Count); daily topics: $(($_.schedule | ForEach-Object { $_.days.Count } | Measure-Object -Sum).Sum)"
    "Fallback stages: $($_.fallback_nodes -join ', ')"
}
```

### 3:15–4:10 — Module 6 profile/analytics placeholders and Module 7 provider fallback

**Say:** “Profile and analytics endpoints are placeholders. The provider
fallback is an internal skill-gap component, not a separate public route:
Groq is primary, OpenRouter secondary, and both failing returns an error.
There is no exponential-backoff or provider-independent success claim.”

```powershell
# Module 6: these authenticated endpoints currently return placeholder JSON.
Invoke-RestMethod -Uri "$BaseUrl/cia/career/profiles/demo-candidate" -Headers @{ Cookie = $SessionCookie; Accept = "application/json" }
Invoke-RestMethod -Uri "$BaseUrl/cia/career/analytics" -Headers @{ Cookie = $SessionCookie; Accept = "application/json" }

# Module 7: repeatable, offline mocked primary-failure demonstration.
& $Python scripts\verify_capstone_demo.py
```

The verifier exercises the fallback in-process with mocked provider functions;
it makes no external LLM request. It also runs the ChromaDB seed/search test
against temporary storage with model downloads disabled. Its output
distinguishes offline checks from PHP checks that require a PHP executable.

### 4:10–5:00 — Search audit, scorecard, and wrap-up

**Say:** “Successful role search writes a candidate-scoped record. Indexes
exist for candidate/time and candidate/role lookup, but the committed audit
does not contain a live MySQL `EXPLAIN` plan. This scorecard reports what was
actually checked.”

```powershell
# Run from the complete Laravel application checkout with database configured.
php artisan tinker --execute="dump(DB::table('cia_search_logs')->latest('id')->limit(5)->get(['id','candidate_id','target_role','created_at']));"

# From this repository root:
& $Python scripts\verify_capstone_demo.py
git diff --check
```

**Close:** “The implemented slice demonstrates retrieval, structured career
guidance, a validated learning plan, and bounded provider handling. The
remaining placeholder routes and host-app/database checks are called out
explicitly in the case study and architecture notes.”

## Live route and payload cheat-sheet

All routes below are declared in `routes/web.php` and use `auth`. `Cookie`
examples are illustrative; use the local app's configured authentication and
CSRF mechanism.

| Demo module | Route / status | Example payload |
| --- | --- | --- |
| 1. Career hub | `GET /cia/career/dashboard` — placeholder | None |
| 2. Semantic role search | `POST /candidate/cia/career/vector-search` — controller-backed | `{"skills":["Python","SQL"]}` |
| 3. Career recommendations | `POST /candidate/cia/career/recommendations` — controller-backed | `{"target_role":"Full Stack Developer","skills":["PHP"]}` |
| 4. Skill-gap analysis | `POST /candidate/cia/career/skill-gap` — controller-backed | `{"target_role":"Full Stack Developer"}` |
| 5. Learning path | `POST /candidate/cia/career/learning-path` — controller-backed | `{"target_role":"Full Stack Developer","skills":["PHP"],"skill_gaps":["Laravel"],"weekly_hours":7}` |
| 6. Profile / analytics | `GET /cia/career/profiles/{candidate_id}`, `POST /cia/career/profiles`, `PUT /cia/career/profiles/{candidate_id}/skills`, `GET /cia/career/recommendations/{candidate_id}`, `GET /cia/career/analytics` — placeholders | Profile create example: `{"name":"Demo Candidate","email":"demo@example.test"}` |
| 7. Provider fallback | Internal to skill-gap action; no public route | `{"messages":[{"role":"user","content":"..."}]}` |

## Recording boundaries

- Do not claim all seven modules are implemented end to end; module 1 and
  module 6 routes are placeholders, and module 7 is an internal provider
  boundary rather than a route.
- The Python verifier does not start Laravel or connect to MySQL. Authenticated
  HTTP calls and database inspection require the complete application runtime.
- The fallback demonstration mocks both provider functions and sends no
  network request. No real API key is needed for it.
- The route contract examples are not all implemented response examples;
  consult `api-contracts-cia-career.md` and the controller before recording.
