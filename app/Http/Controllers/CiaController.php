<?php

namespace App\Http\Controllers;

use App\Models\LessonUser;
use App\Models\PlacementReadinessScore;
use App\Models\QuizAttempt;
use App\Services\ReadinessScoreService;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class CiaController extends \Illuminate\Routing\Controller
{
    private const READINESS_SCORE_FIELDS = [
        'project_completion_score',
        'assessment_score',
        'cia_usage_score',
        'docs_score',
        'placement_readiness_score',
        'viva_score',
        'code_review_score',
        'demo_clarity_score',
        'git_quality_score',
        'interview_readiness_score',
    ];

    private const RADAR_LABELS = [
        'Backend PHP',
        'Laravel',
        'Vector Search',
        'API Design',
        'DevOps',
    ];

    private const DEFAULT_BENCHMARKS = [90, 90, 85, 90, 80];

    private const ROLE_BENCHMARKS = [
        'backend developer' => [90, 88, 70, 90, 75],
        'full stack developer' => [88, 92, 75, 90, 75],
        'data scientist' => [65, 60, 95, 80, 70],
        'devops engineer' => [85, 80, 70, 85, 95],
    ];

    public function readinessScores(Request $request): JsonResponse
    {
        $scores = PlacementReadinessScore::query()
            ->where('user_id', $request->user()->getAuthIdentifier())
            ->first();

        return response()->json([
            'scores' => $scores?->only(self::READINESS_SCORE_FIELDS),
        ]);
    }

    public function calculateReadinessScores(Request $request, ReadinessScoreService $scoreService): JsonResponse
    {
        $validated = $request->validate([
            'project_completion_score' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'assessment_score' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'cia_usage_score' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'docs_score' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'viva_score' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'code_review_score' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'demo_clarity_score' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'git_quality_score' => ['sometimes', 'numeric', 'min:0', 'max:100'],
        ]);

        $score = $scoreService->calculateAndSave(
            (int) $request->user()->getAuthIdentifier(),
            $validated
        );

        return response()->json([
            'message' => 'Readiness scores calculated successfully.',
            'scores' => $score->only(self::READINESS_SCORE_FIELDS),
        ]);
    }

    public function skillGap(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_role' => ['required', 'string', 'max:120'],
        ]);

        $candidateId = $request->user()->getAuthIdentifier();
        $completedLessons = LessonUser::query()
            ->join('lessons', 'lessons.id', '=', 'lesson_user.lesson_id')
            ->where('lesson_user.user_id', $candidateId)
            ->whereNotNull('lesson_user.completed_at')
            ->get([
                'lessons.title',
                'lessons.module_name',
                'lesson_user.completed_at',
            ]);

        $quizAttempts = QuizAttempt::query()
            ->where('user_id', $candidateId)
            ->whereNotNull('completed_at')
            ->whereNotNull('score')
            ->get(['skill', 'score']);

        $quizScoresBySkill = $quizAttempts
            ->groupBy(fn (QuizAttempt $attempt): string => Str::lower(trim((string) $attempt->skill)))
            ->map(fn ($attempts): float => round((float) $attempts->avg(
                fn (QuizAttempt $attempt): float => (float) $attempt->score
            ), 1));
        $averageQuizScore = $quizAttempts->isEmpty()
            ? 0.0
            : round((float) $quizAttempts->avg(
                fn (QuizAttempt $attempt): float => (float) $attempt->score
            ), 1);

        $targetRole = trim($validated['target_role']);
        $targetScores = self::ROLE_BENCHMARKS[Str::lower($targetRole)]
            ?? self::DEFAULT_BENCHMARKS;
        $candidateScores = array_map(
            fn (string $label): float => (float) ($quizScoresBySkill[Str::lower($label)] ?? $averageQuizScore),
            self::RADAR_LABELS
        );

        $skillProfile = [
            'completed_modules' => $completedLessons
                ->map(fn ($lesson): string => $lesson->module_name ?: $lesson->title)
                ->filter()
                ->unique()
                ->values()
                ->all(),
            'completed_lesson_count' => $completedLessons->count(),
            'quiz_score_average' => $averageQuizScore,
            'quiz_scores_by_skill' => $quizScoresBySkill->all(),
            'target_role' => $targetRole,
            'target_role_benchmarks' => array_combine(self::RADAR_LABELS, $targetScores),
        ];

        $radarData = [
            'labels' => self::RADAR_LABELS,
            'candidate_scores' => $candidateScores,
            'target_scores' => $targetScores,
        ];
        $readinessScore = $this->calculateReadinessScore($candidateScores, $targetScores);
        $analysis = $this->requestGroqAnalysis($skillProfile, $radarData);

        return response()->json([
            'score' => $readinessScore,
            'gaps' => $analysis['gaps'],
            'priority_learning' => $analysis['priority_learning'],
            'radar_data' => $radarData,
        ]);
    }

    public function careerRecommendations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_role' => ['required', 'string', 'max:120'],
            'skills' => ['sometimes', 'array', 'max:50'],
            'skills.*' => ['string', 'max:120'],
            'skill_gaps' => ['sometimes', 'array', 'max:50'],
            'skill_gaps.*' => ['string', 'max:120'],
            'profile' => ['sometimes', 'array'],
        ]);

        $payload = [
            'candidate_id' => (string) $request->user()->getAuthIdentifier(),
            'target_role' => trim($validated['target_role']),
            'skills' => array_values($validated['skills'] ?? []),
            'skill_gaps' => array_values($validated['skill_gaps'] ?? []),
            'profile' => $validated['profile'] ?? [],
        ];

        try {
            $input = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            Log::error('Career recommendation input could not be encoded.', [
                'exception' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Unable to prepare recommendation input.'], 500);
        }

        $process = new Process([
            (string) env('CIA_PYTHON_BINARY', 'python'),
            base_path('scripts/career_recommendations.py'),
        ], base_path());
        $process->setInput($input);
        $process->setTimeout(90);

        try {
            $process->run();
        } catch (ProcessStartFailedException $exception) {
            $result = ['message' => 'Vector search could not be started.'];
            $logId = $this->logVectorSearch($candidateId, $payload, $result);
            Log::error('CIA vector search process could not be started.', [
                'candidate_id' => $candidateId,
                'log_id' => $logId,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json($result, 502);
        } catch (ProcessTimedOutException $exception) {
            Log::warning('Career recommendation agent timed out.', [
                'candidate_id' => $payload['candidate_id'],
            ]);

            return response()->json(['message' => 'Career recommendations timed out. Please try again.'], 504);
        }

        if (! $process->isSuccessful()) {
            Log::error('Career recommendation agent failed.', [
                'candidate_id' => $payload['candidate_id'],
                'exit_code' => $process->getExitCode(),
                'stderr' => $process->getErrorOutput(),
            ]);

            return response()->json(['message' => 'Career recommendations could not be generated.'], 502);
        }

        try {
            $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            Log::error('Career recommendation agent returned invalid JSON.', [
                'candidate_id' => $payload['candidate_id'],
                'exception' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Career recommendation service returned an invalid response.'], 502);
        }

        if (! is_array($result) || ! isset($result['career_paths']) || count($result['career_paths']) !== 3) {
            Log::error('Career recommendation agent returned an invalid response shape.', [
                'candidate_id' => $payload['candidate_id'],
            ]);

            return response()->json(['message' => 'Career recommendation service returned an invalid response.'], 502);
        }

        $logId = DB::table('cia_search_logs')->insertGetId([
            'candidate_id' => $payload['candidate_id'],
            'request_payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'results' => json_encode($result, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json([
            'log_id' => $logId,
            'career_paths' => $result['career_paths'],
        ]);
    }

    public function learningPath(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_role' => ['required', 'string', 'max:120', 'regex:/\S/'],
            'skills' => ['sometimes', 'array', 'max:50'],
            'skills.*' => ['required', 'string', 'max:120', 'regex:/\S/'],
            'skill_gaps' => ['sometimes', 'array', 'max:50'],
            'skill_gaps.*' => ['required', 'string', 'max:120', 'regex:/\S/'],
            'experience_level' => ['sometimes', 'string', 'max:80'],
            'weekly_hours' => ['sometimes', 'integer', 'min:1', 'max:40'],
            'profile' => ['sometimes', 'array', 'max:50'],
        ]);
        $candidateId = (string) $request->user()->getAuthIdentifier();
        $input = [
            'target_role' => trim($validated['target_role']),
            'skills' => array_values(array_map('trim', $validated['skills'] ?? [])),
            'skill_gaps' => array_values(array_map('trim', $validated['skill_gaps'] ?? [])),
            'experience_level' => trim($validated['experience_level'] ?? 'intermediate'),
            'weekly_hours' => (int) ($validated['weekly_hours'] ?? 7),
            'profile' => $validated['profile'] ?? [],
        ];
        $requestPayload = ['candidate_id' => $candidateId] + $input;

        try {
            $encodedInput = json_encode($input, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            Log::error('Learning path input could not be encoded.', [
                'candidate_id' => $candidateId,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Unable to prepare learning path input.'], 500);
        }

        $process = new Process([
            (string) env('CIA_PYTHON_BINARY', 'python'),
            base_path('scripts/langgraph_learning_path.py'),
        ], base_path());
        $process->setInput($encodedInput);
        $process->setEnv([
            'GROQ_API_KEY' => (string) env('GROQ_API_KEY', ''),
            'GROQ_MODEL' => (string) env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        ]);
        $process->setTimeout(240);

        try {
            $process->run();
        } catch (ProcessStartFailedException $exception) {
            $message = 'Learning path service could not be started.';
            $logId = $this->logLearningPath($candidateId, $requestPayload, [
                'status' => 'failed',
                'message' => $message,
            ]);
            Log::error('CIA learning path process could not be started.', [
                'candidate_id' => $candidateId,
                'log_id' => $logId,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json(['message' => $message, 'log_id' => $logId], 502);
        } catch (ProcessTimedOutException $exception) {
            $message = 'Learning path generation timed out. Please try again.';
            $logId = $this->logLearningPath($candidateId, $requestPayload, [
                'status' => 'failed',
                'message' => $message,
            ]);
            Log::warning('CIA learning path process timed out.', [
                'candidate_id' => $candidateId,
                'log_id' => $logId,
            ]);

            return response()->json(['message' => $message, 'log_id' => $logId], 504);
        }

        if (! $process->isSuccessful()) {
            $message = 'Learning path could not be generated.';
            $logId = $this->logLearningPath($candidateId, $requestPayload, [
                'status' => 'failed',
                'message' => $message,
            ]);
            Log::error('CIA learning path process failed.', [
                'candidate_id' => $candidateId,
                'log_id' => $logId,
                'exit_code' => $process->getExitCode(),
                'stderr' => $process->getErrorOutput(),
            ]);

            return response()->json(['message' => $message, 'log_id' => $logId], 502);
        }

        try {
            $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $message = 'Learning path service returned an invalid response.';
            $logId = $this->logLearningPath($candidateId, $requestPayload, [
                'status' => 'failed',
                'message' => $message,
            ]);
            Log::error('CIA learning path service returned invalid JSON.', [
                'candidate_id' => $candidateId,
                'log_id' => $logId,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json(['message' => $message, 'log_id' => $logId], 502);
        }

        if (
            ! is_array($result)
            || ! isset($result['schedule'])
            || ! is_array($result['schedule'])
            || count($result['schedule']) !== 8
            || count(array_filter(
                $result['schedule'],
                fn ($week): bool => ! is_array($week)
                    || ! is_array($week['days'] ?? null)
                    || count($week['days']) !== 7
            )) !== 0
            || ! is_string($result['mermaid'] ?? null)
            || ! is_array($result['validation'] ?? null)
        ) {
            $message = 'Learning path service returned an invalid response.';
            $logId = $this->logLearningPath($candidateId, $requestPayload, [
                'status' => 'failed',
                'message' => $message,
            ]);
            Log::error('CIA learning path service returned an invalid response shape.', [
                'candidate_id' => $candidateId,
                'log_id' => $logId,
            ]);

            return response()->json(['message' => $message, 'log_id' => $logId], 502);
        }

        $logId = $this->logLearningPath(
            $candidateId,
            $requestPayload,
            ['status' => 'success'] + $result
        );

        return response()->json(['log_id' => $logId] + $result);
    }

    public function vectorSearch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'skills' => ['required', 'array', 'min:1', 'max:50'],
            'skills.*' => ['required', 'string', 'max:120', 'regex:/\S/'],
        ]);
        $candidateId = (string) $request->user()->getAuthIdentifier();
        $payload = [
            'candidate_id' => $candidateId,
            'skills' => array_values(array_map('trim', $validated['skills'])),
        ];

        try {
            $input = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            Log::error('Vector search input could not be encoded.', [
                'candidate_id' => $candidateId,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json(['message' => 'Unable to prepare vector-search input.'], 500);
        }

        $process = new Process([
            (string) env('CIA_PYTHON_BINARY', 'python'),
            base_path('scripts/vector_search_roles.py'),
        ], base_path());
        $process->setInput($input);
        $process->setEnv([
            'CIA_CHROMA_PERSIST_DIRECTORY' => (string) env(
                'CIA_CHROMA_PERSIST_DIRECTORY',
                storage_path('app/chromadb')
            ),
        ]);
        $process->setTimeout(90);

        try {
            $process->run();
        } catch (ProcessTimedOutException $exception) {
            $result = ['message' => 'Vector search timed out.'];
            $logId = $this->logVectorSearch($candidateId, $payload, $result);
            Log::warning('CIA vector search timed out.', [
                'candidate_id' => $candidateId,
                'log_id' => $logId,
            ]);

            return response()->json($result, 504);
        }

        if (! $process->isSuccessful()) {
            $result = ['message' => 'Vector search could not be completed.'];
            $logId = $this->logVectorSearch($candidateId, $payload, $result);
            Log::error('CIA vector search process failed.', [
                'candidate_id' => $candidateId,
                'log_id' => $logId,
                'exit_code' => $process->getExitCode(),
                'stderr' => $process->getErrorOutput(),
            ]);

            return response()->json($result, 502);
        }

        try {
            $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $failure = ['message' => 'Vector search returned an invalid response.'];
            $logId = $this->logVectorSearch($candidateId, $payload, $failure);
            Log::error('CIA vector search returned invalid JSON.', [
                'candidate_id' => $candidateId,
                'log_id' => $logId,
                'exception' => $exception->getMessage(),
            ]);

            return response()->json($failure, 502);
        }

        if (
            ! is_array($result)
            || ! isset($result['matches'])
            || ! is_array($result['matches'])
            || count($result['matches']) !== 5
        ) {
            $failure = ['message' => 'Vector search returned an invalid response.'];
            $logId = $this->logVectorSearch($candidateId, $payload, $failure);
            Log::error('CIA vector search returned an invalid response shape.', [
                'candidate_id' => $candidateId,
                'log_id' => $logId,
            ]);

            return response()->json($failure, 502);
        }

        $logId = $this->logVectorSearch($candidateId, $payload, $result);

        return response()->json([
            'log_id' => $logId,
            'matches' => $result['matches'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $requestPayload
     * @param  array<string, mixed>  $results
     */
    private function logLearningPath(string $candidateId, array $requestPayload, array $results): int
    {
        return DB::table('cia_search_logs')->insertGetId([
            'candidate_id' => $candidateId,
            'request_payload' => json_encode($requestPayload, JSON_THROW_ON_ERROR),
            'results' => json_encode($results, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $requestPayload
     * @param  array<string, mixed>  $results
     */
    private function logVectorSearch(string $candidateId, array $requestPayload, array $results): int
    {
        return DB::table('cia_search_logs')->insertGetId([
            'candidate_id' => $candidateId,
            'request_payload' => json_encode($requestPayload, JSON_THROW_ON_ERROR),
            'results' => json_encode($results, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $skillProfile
     * @param  array{labels: list<string>, candidate_scores: list<float>, target_scores: list<int>}  $radarData
     * @return array{gaps: list<string>, priority_learning: list<string>}
     */
    private function requestGroqAnalysis(array $skillProfile, array $radarData): array
    {
        $fallback = $this->buildMockAnalysis($radarData);
        $apiKey = config('services.groq.api_key') ?: env('GROQ_API_KEY');

        if (! is_string($apiKey) || trim($apiKey) === '') {
            Log::notice('Groq skill-gap analysis is not configured; using mock analysis.');

            return $fallback;
        }

        $profilePayload = json_encode([
            'skill_profile' => $skillProfile,
            'target_role' => $skillProfile['target_role'],
            'radar_data' => $radarData,
        ]);

        if (! is_string($profilePayload)) {
            Log::warning('Groq skill-gap profile could not be encoded; using mock analysis.');

            return $fallback;
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->timeout(25)
                ->post('https://api.groq.com/openai/v1/chat/completions', [
                    'model' => env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
                    'temperature' => 0.2,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Analyze the skill profile for the target role. Return only a JSON object with string arrays "gaps" and "priority_learning". Identify actionable gaps by comparing candidate scores to target scores. This is a LlamaIndex-style structured analysis prompt.',
                        ],
                        [
                            'role' => 'user',
                            'content' => $profilePayload,
                        ],
                    ],
                ]);
        } catch (ConnectionException $exception) {
            Log::warning('Groq skill-gap request failed to connect; using mock analysis.', [
                'exception' => $exception->getMessage(),
            ]);

            return $fallback;
        }

        if (! $response->successful()) {
            Log::warning('Groq skill-gap request returned an unsuccessful response; using mock analysis.', [
                'status' => $response->status(),
            ]);

            return $fallback;
        }

        $content = $response->json('choices.0.message.content');
        $decoded = is_string($content) ? json_decode($content, true) : null;

        if (
            ! is_array($decoded)
            || ! $this->isStringList($decoded['gaps'] ?? null)
            || ! $this->isStringList($decoded['priority_learning'] ?? null)
        ) {
            Log::warning('Groq skill-gap response had an invalid JSON shape; using mock analysis.');

            return $fallback;
        }

        return [
            'gaps' => array_values(array_slice($decoded['gaps'], 0, 10)),
            'priority_learning' => array_values(array_slice($decoded['priority_learning'], 0, 10)),
        ];
    }

    /**
     * @param  array{labels: list<string>, candidate_scores: list<float>, target_scores: list<int>}  $radarData
     * @return array{gaps: list<string>, priority_learning: list<string>}
     */
    private function buildMockAnalysis(array $radarData): array
    {
        $gaps = [];
        $priorities = [];

        foreach ($radarData['labels'] as $index => $label) {
            if ($radarData['candidate_scores'][$index] >= $radarData['target_scores'][$index]) {
                continue;
            }

            $gaps[] = $label;
            $priorities[] = "Build {$label} skills with focused practice aligned to the target-role benchmark.";
        }

        return [
            'gaps' => $gaps,
            'priority_learning' => array_slice($priorities, 0, 3),
        ];
    }

    /**
     * @param  list<float>  $candidateScores
     * @param  list<int>  $targetScores
     */
    private function calculateReadinessScore(array $candidateScores, array $targetScores): int
    {
        $normalizedScores = array_map(
            fn (float $candidateScore, int $targetScore): float => $targetScore > 0
                ? min(100, ($candidateScore / $targetScore) * 100)
                : 100,
            $candidateScores,
            $targetScores
        );

        return (int) round(array_sum($normalizedScores) / count($normalizedScores));
    }

    private function isStringList(mixed $value): bool
    {
        return is_array($value)
            && array_is_list($value)
            && count(array_filter($value, 'is_string')) === count($value);
    }
}
