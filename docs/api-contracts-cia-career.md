# CIA Career Intelligence API Contract

This document describes the authenticated career-intelligence endpoints. All
paths are relative to the application origin. The original career endpoints
remain placeholders; the skill-gap endpoint uses illustrative database
assumptions documented in its section below.

## Conventions

- Base path: `/cia/career`
- Content type: `application/json`
- Protected routes require an authenticated application session.
- Send `Accept: application/json` on all requests. Send
  `Content-Type: application/json` when a request has a JSON body.
- IDs are opaque strings. The examples use UUID-like values.
- Error responses have the common shape shown under [Errors](#errors).

### Common headers

| Header | Required | Description |
| --- | --- | --- |
| `Accept` | Yes | `application/json` |
| `Content-Type` | For JSON bodies | `application/json` |
| `Authorization` | Depends on configured auth guard | Send credentials using the application's configured authorization scheme |
| Session cookie and CSRF token | Depends on configured auth guard | Required when using cookie-based authentication for state-changing requests |

## 1. Career dashboard

`GET /cia/career/dashboard`  
Route name: `cia.career.dashboard`

Returns overview metrics for the authenticated user's career-intelligence
workspace.

### Request

No request body or query parameters.

### `200 OK`

```json
{
  "candidate_count": 128,
  "profile_completion_rate": 0.84,
  "average_readiness_score": 76.5,
  "recent_activity": [
    {
      "type": "profile_updated",
      "candidate_id": "cand-1042",
      "occurred_at": "2026-10-26T12:00:00Z"
    }
  ]
}
```

Response schema:

```json
{
  "candidate_count": "integer >= 0",
  "profile_completion_rate": "number between 0 and 1",
  "average_readiness_score": "number between 0 and 100",
  "recent_activity": [
    {
      "type": "string",
      "candidate_id": "string",
      "occurred_at": "ISO-8601 datetime"
    }
  ]
}
```

## 2. Get a candidate profile

`GET /cia/career/profiles/{candidate_id}`  
Route name: `cia.career.profiles.show`

### Path parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `candidate_id` | string | Yes | Candidate identifier |

### Request

No request body.

### `200 OK`

```json
{
  "candidate_id": "cand-1042",
  "name": "Jordan Lee",
  "email": "jordan@example.com",
  "skills": ["Python", "Data Analysis"],
  "scores": {
    "readiness": 82.5,
    "technical": 88
  },
  "history": [
    {
      "event": "assessment_completed",
      "occurred_at": "2026-10-20T09:30:00Z"
    }
  ],
  "created_at": "2026-10-01T10:00:00Z",
  "updated_at": "2026-10-20T09:30:00Z"
}
```

Response schema:

```json
{
  "candidate_id": "string",
  "name": "string",
  "email": "string (email address)",
  "skills": ["string"],
  "scores": {
    "<score_name>": "number"
  },
  "history": [
    {
      "event": "string",
      "occurred_at": "ISO-8601 datetime",
      "details": "object (optional)"
    }
  ],
  "created_at": "ISO-8601 datetime",
  "updated_at": "ISO-8601 datetime"
}
```

## 3. Create a candidate profile

`POST /cia/career/profiles`  
Route name: `cia.career.profiles.store`

### Request headers

Use the common headers and include `Content-Type: application/json`.

### Request body

```json
{
  "name": "Jordan Lee",
  "email": "jordan@example.com",
  "skills": ["Python", "Data Analysis"],
  "scores": {
    "readiness": 82.5
  },
  "history": []
}
```

Request schema:

```json
{
  "name": "string, required, non-empty",
  "email": "string, required, valid email address",
  "skills": ["string"],
  "scores": {
    "<score_name>": "number"
  },
  "history": [
    {
      "event": "string",
      "occurred_at": "ISO-8601 datetime",
      "details": "object (optional)"
    }
  ]
}
```

`skills`, `scores`, and `history` are optional and default to empty values.

### `201 Created`

```json
{
  "candidate_id": "cand-1043",
  "name": "Jordan Lee",
  "email": "jordan@example.com",
  "skills": ["Python", "Data Analysis"],
  "scores": {
    "readiness": 82.5
  },
  "history": [],
  "created_at": "2026-10-26T12:00:00Z",
  "updated_at": "2026-10-26T12:00:00Z"
}
```

## 4. Update candidate skills

`PUT /cia/career/profiles/{candidate_id}/skills`  
Route name: `cia.career.profiles.update_skills`

### Path parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `candidate_id` | string | Yes | Candidate identifier |

### Request headers

Use the common headers and include `Content-Type: application/json`.

### Request body

```json
{
  "skills": ["Python", "Data Analysis", "Machine Learning"]
}
```

Request schema:

```json
{
  "skills": ["string, required"]
}
```

The `skills` array may be empty to clear the candidate's skills.

### `200 OK`

```json
{
  "candidate_id": "cand-1042",
  "skills": ["Python", "Data Analysis", "Machine Learning"],
  "updated_at": "2026-10-26T12:05:00Z"
}
```

## 5. Get candidate recommendations

`GET /cia/career/recommendations/{candidate_id}`  
Route name: `cia.career.recommendations`

### Path parameters

| Name | Type | Required | Description |
| --- | --- | --- | --- |
| `candidate_id` | string | Yes | Candidate identifier |

### Request

No request body or query parameters.

### `200 OK`

```json
{
  "candidate_id": "cand-1042",
  "recommendations": [
    {
      "type": "role",
      "title": "Data Analyst",
      "score": 0.91,
      "reason": "Strong alignment with the candidate's data analysis skills."
    }
  ]
}
```

Response schema:

```json
{
  "candidate_id": "string",
  "recommendations": [
    {
      "type": "string",
      "title": "string",
      "score": "number between 0 and 1",
      "reason": "string"
    }
  ]
}
```

## 6. Match candidate skills to roles

`POST /candidate/cia/career/vector-search`

Route name: `cia.career.vector_search`

This authenticated endpoint embeds the candidate's skill profile, ranks the
seeded internship and career roles using cosine similarity, and logs each
execution in `cia_search_logs`.

### Request headers

Use the common headers and include `Content-Type: application/json`.

### Request body

```json
{
  "skills": ["Python", "SQL", "Data Visualization"]
}
```

Request schema:

```json
{
  "skills": ["string, required; 1 through 50 non-empty skills"]
}
```

### `200 OK`

```json
{
  "log_id": 12,
  "matches": [
    {
      "role_id": "role-04",
      "role": "Data Analyst",
      "match_percentage": 84.2,
      "cosine_similarity": 0.842,
      "required_skills": ["SQL", "Excel", "Python", "Data Visualization", "Statistics"],
      "missing_skills": ["Excel", "Statistics"],
      "match_criteria": "Turn business data into reliable reports, dashboards, and actionable insights.",
      "metadata": {
        "category": "Data",
        "experience_level": "Entry-level",
        "work_mode": "Hybrid"
      }
    }
  ]
}
```

Response schema:

```json
{
  "log_id": "integer",
  "matches": [
    {
      "role_id": "string",
      "role": "string",
      "match_percentage": "number between 0 and 100",
      "cosine_similarity": "number between 0 and 1",
      "required_skills": ["string"],
      "missing_skills": ["string"],
      "match_criteria": "string",
      "metadata": "object containing role category, experience level, and work mode"
    }
  ]
}
```

The response contains the five highest-ranked role profiles. Each role's
`match_percentage` is the ChromaDB cosine similarity expressed as a percentage;
`missing_skills` lists required skills that are not present in the submitted
skill list. Seed or refresh the collection with
`python scripts/seed_chromadb_roles.py` before using the endpoint. Set
`CIA_CHROMA_PERSIST_DIRECTORY` to override the default persistent collection
directory and `CIA_PYTHON_BINARY` to select the Python executable used by
Laravel. ChromaDB's default `all-MiniLM-L6-v2` embedding model is downloaded
the first time roles are embedded.

## 7. Career analytics

`GET /cia/career/analytics`  
Route name: `cia.career.analytics`

### Request

No request body or query parameters.

### `200 OK`

```json
{
  "total_candidates": 128,
  "skill_distribution": [
    {
      "skill": "Python",
      "candidate_count": 64
    }
  ],
  "readiness_distribution": {
    "average": 76.5,
    "median": 79,
    "by_band": {
      "high": 42,
      "medium": 61,
      "low": 25
    }
  }
}
```

Response schema:

```json
{
  "total_candidates": "integer >= 0",
  "skill_distribution": [
    {
      "skill": "string",
      "candidate_count": "integer >= 0"
    }
  ],
  "readiness_distribution": {
    "average": "number",
    "median": "number",
    "by_band": {
      "<band_name>": "integer >= 0"
    }
  }
}
```

## 8. Analyze a candidate's skill gap

`POST /candidate/cia/career/skill-gap`
Route name: `cia.career.skill_gap`

Requires an authenticated candidate. The candidate is taken from the current
session; do not send a candidate ID in the request.

### Request

```json
{
  "target_role": "Full Stack Developer"
}
```

`target_role` is required and must be a string of at most 120 characters.

### `200 OK`

```json
{
  "score": 78,
  "gaps": ["Vector Search", "DevOps"],
  "priority_learning": [
    "Build Vector Search skills with focused practice aligned to the target-role benchmark."
  ],
  "radar_data": {
    "labels": ["Backend PHP", "Laravel", "Vector Search", "API Design", "DevOps"],
    "candidate_scores": [88, 92, 70, 85, 60],
    "target_scores": [88, 92, 75, 90, 75]
  }
}
```

`score` is readiness relative to the target benchmarks (0–100).
`radar_data` arrays share the same index order. Known benchmarks are supplied
for Backend Developer, Full Stack Developer, Data Scientist, and DevOps
Engineer; other roles use the default benchmark.

The illustrative persistence schema is:

- `lesson_user`: `user_id`, `lesson_id`, and nullable `completed_at`.
- `lessons`: `id`, `title`, and `module_name`.
- `quiz_attempts`: `user_id`, `skill`, `score`, and nullable `completed_at`.

The candidate's completed lessons and completed quiz attempts are scoped to
the authenticated user. When `GROQ_API_KEY` is configured, the skill profile
and target role are sent to Groq for structured gap and priority suggestions.
The model is configurable with `GROQ_MODEL`. Missing configuration, a failed
request, or an invalid model response is logged and falls back to local
benchmark-based suggestions.

## 9. Score a resume against a job description

`POST /candidate/cia/career/ats-score`
Route name: `cia.career.ats_score`

Requires an authenticated candidate. Send a `multipart/form-data` request
containing a PDF resume and the target job description. The uploaded file is
temporarily stored for processing and deleted after the scoring attempt. The
candidate identifier, original filename, job description, and result are logged
in `cia_search_logs`; the resume contents are not stored in that log.

### Request fields

| Name | Type | Required | Constraints |
| --- | --- | --- | --- |
| `resume` | file | Yes | PDF, maximum 10 MB |
| `job_description` | string | Yes | Non-empty, maximum 12,000 characters |

### `200 OK`

```json
{
  "log_id": 18,
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
      "suggestion": "Add a truthful metric for the project outcome."
    }
  ]
}
```

The score and four category values are integers between 0 and 100.
`rewrite_suggestions` contains actionable section-level items. The Python
pipeline uses Docling for PDF/DOCX extraction; this authenticated endpoint
currently accepts PDF uploads. Configure `GROQ_API_KEY`, optionally set
`GROQ_MODEL`, and use `CIA_PYTHON_BINARY` to select the Python executable used
by Laravel.

## Errors

The original routes use the common error examples below. Laravel validation
for the skill-gap request returns `422 Unprocessable Entity` when
`target_role` is missing or invalid. Resume scoring validation returns `422`
when the PDF or job description is missing or invalid.

### `400 Bad Request`

Returned when the request body is malformed or fails validation.

```json
{
  "error": {
    "code": "BAD_REQUEST",
    "message": "The request is invalid.",
    "details": {
      "skills": ["The skills field must be an array of strings."]
    }
  }
}
```

`details` may be omitted when there are no field-specific validation errors.

### `401 Unauthorized`

Returned when the request is not authenticated.

```json
{
  "error": {
    "code": "UNAUTHENTICATED",
    "message": "Authentication is required."
  }
}
```

### `404 Not Found`

Returned when a requested candidate does not exist.

```json
{
  "error": {
    "code": "NOT_FOUND",
    "message": "The requested candidate was not found."
  }
}
```

For a request to a valid endpoint path that does not exist, the application may
also return this status using its standard not-found response.
