<?php

namespace App\Http\Controllers;

use App\Models\LessonUser;
use App\Models\QuizAttempt;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process as ProcessFacade;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class CiaController extends \Illuminate\Routing\Controller
{
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

    public function skillGap(Request $request): JsonResponse
    {
        $validated = $this->validateRequest($request, [
            'target_role' => ['required', 'string', 'max:120'],
        ]);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

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
        if ($analysis instanceof JsonResponse) {
            return $analysis;
        }

        return response()->json([
            'score' => $readinessScore,
            'gaps' => $analysis['gaps'],
            'priority_learning' => $analysis['priority_learning'],
            'radar_data' => $radarData,
        ]);
    }

    public function careerFallback(Request $request): JsonResponse
    {
        $validated = $this->validateRequest($request, [
            'prompt' => ['required', 'string', 'min:3', 'regex:/\S/'],
        ]);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        try {
            $process = ProcessFacade::timeout(15)
                ->env([
                    'GROQ_API_KEY' => (string) (config('services.groq.api_key') ?: env('GROQ_API_KEY', '')),
                    'OPENROUTER_API_KEY' => (string) (
                        config('services.openrouter.api_key') ?: env('OPENROUTER_API_KEY', '')
                    ),
                ])
                ->run([
                    (string) env('CIA_PYTHON_BINARY', 'python'),
                    base_path('scripts/llm_provider_fallback.py'),
                    '--prompt',
                    $validated['prompt'],
                ]);
        } catch (ProcessTimedOutException $exception) {
            Log::error('CIA LLM Failure', [
                'message' => 'LLM provider process exceeded its 15-second deadline.',
            ]);

            return $this->errorResponse('LLM provider process timed out.', 504);
        } catch (ProcessStartFailedException $exception) {
            Log::error('CIA LLM Failure', [
                'message' => 'LLM provider process could not be started.',
                'exception' => $exception->getMessage(),
            ]);

            return $this->errorResponse('LLM provider process could not be started.', 502);
        }

        $decoded = json_decode($process->output(), true);
        if (
            $process->failed()
            || ! is_array($decoded)
            || ($decoded['status'] ?? null) === 'error'
        ) {
            $httpCode = is_array($decoded) && in_array(
                $decoded['code'] ?? null,
                [422, 502, 504],
                true
            ) ? $decoded['code'] : 502;
            $message = is_array($decoded) && is_string($decoded['message'] ?? null)
                ? $decoded['message']
                : 'LLM provider process returned an invalid response.';

            Log::error('CIA LLM Failure', [
                'code' => $httpCode,
                'message' => $message,
                'stderr' => $process->errorOutput(),
            ]);

            return $this->errorResponse($message, $httpCode);
        }

        if (($decoded['status'] ?? null) !== 'success') {
            Log::error('CIA LLM Failure', [
                'message' => 'LLM provider process returned an unexpected response.',
            ]);

            return $this->errorResponse('LLM provider process returned an invalid response.', 502);
        }

        return response()->json($decoded, 200);
    }

    public function careerRecommendations(Request $request): JsonResponse
    {
        $validated = $this->validateRequest($request, [
            'target_role' => ['required', 'string', 'max:120'],
            'skills' => ['sometimes', 'array', 'max:50'],
            'skills.*' => ['string', 'max:120'],
            'skill_gaps' => ['sometimes', 'array', 'max:50'],
            'skill_gaps.*' => ['string', 'max:120'],
            'profile' => ['sometimes', 'array'],
        ]);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

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

            return $this->errorResponse('Unable to prepare recommendation input.', 500);
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
            $result = ['status' => 'failed', 'message' => 'Career recommendations could not be started.'];
            $logId = $this->logSearchRequest($payload['candidate_id'], $payload, $result);
            Log::error('CIA career recommendation process could not be started.', [
                'candidate_id' => $payload['candidate_id'],
                'log_id' => $logId,
                'exception' => $exception->getMessage(),
            ]);

            return $this->errorResponse($result['message'], 502, ['log_id' => $logId]);
        } catch (ProcessTimedOutException $exception) {
            $result = ['status' => 'failed', 'message' => 'Career recommendations timed out.'];
            $logId = $this->logSearchRequest($payload['candidate_id'], $payload, $result);
            Log::warning('Career recommendation agent timed out.', [
                'candidate_id' => $payload['candidate_id'],
                'log_id' => $logId,
            ]);

            return $this->errorResponse(
                'Career recommendations timed out. Please try again.',
                504,
                ['log_id' => $logId]
            );
        } catch (\Throwable $exception) {
            $result = ['status' => 'failed', 'message' => 'Career recommendations could not be generated.'];
            $logId = $this->logSearchRequest($payload['candidate_id'], $payload, $result);
            Log::error('Career recommendation process could not be completed.', [
                'candidate_id' => $payload['candidate_id'],
                'log_id' => $logId,
                'exception' => $exception->getMessage(),
            ]);

            return $this->errorResponse($result['message'], 502, ['log_id' => $logId]);
        }

        if (! $process->isSuccessful()) {
            $result = ['status' => 'failed', 'message' => 'Career recommendations could not be generated.'];
            $logId = $this->logSearchRequest($payload['candidate_id'], $payload, $result);
            Log::error('Career recommendation agent failed.', [
                'candidate_id' => $payload['candidate_id'],
                'log_id' => $logId,
                'exit_code' => $process->getExitCode(),
                'stderr' => $process->getErrorOutput(),
            ]);

            return $this->errorResponse($result['message'], 502, ['log_id' => $logId]);
        }

        try {
            $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $failure = ['status' => 'failed', 'message' => 'Career recommendation service returned invalid JSON.'];
            $logId = $this->logSearchRequest($payload['candidate_id'], $payload, $failure);
            Log::error('Career recommendation agent returned invalid JSON.', [
                'candidate_id' => $payload['candidate_id'],
                'log_id' => $logId,
                'exception' => $exception->getMessage(),
            ]);

            return $this->errorResponse(
                'Career recommendation service returned an invalid response.',
                502,
                ['log_id' => $logId]
            );
        }

        if (
            ! is_array($result)
            || ! is_array($result['career_paths'] ?? null)
            || count($result['career_paths']) !== 3
        ) {
            $failure = ['status' => 'failed', 'message' => 'Career recommendation service returned an invalid response.'];
            $logId = $this->logSearchRequest($payload['candidate_id'], $payload, $failure);
            Log::error('Career recommendation agent returned an invalid response shape.', [
                'candidate_id' => $payload['candidate_id'],
                'log_id' => $logId,
            ]);

            return $this->errorResponse($failure['message'], 502, ['log_id' => $logId]);
        }

        $logId = $this->logSearchRequest($payload['candidate_id'], $payload, $result);

        return response()->json([
            'log_id' => $logId,
            'career_paths' => $result['career_paths'],
        ]);
    }

    public function learningPath(Request $request): JsonResponse
    {
        $validated = $this->validateRequest($request, [
            'target_role' => ['required', 'string', 'max:120', 'regex:/\S/'],
            'skills' => ['sometimes', 'array', 'max:50'],
            'skills.*' => ['required', 'string', 'max:120', 'regex:/\S/'],
            'skill_gaps' => ['sometimes', 'array', 'max:50'],
            'skill_gaps.*' => ['required', 'string', 'max:120', 'regex:/\S/'],
            'experience_level' => ['sometimes', 'string', 'max:80'],
            'weekly_hours' => ['sometimes', 'integer', 'min:1', 'max:40'],
            'profile' => ['sometimes', 'array', 'max:50'],
        ]);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }
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

            return $this->errorResponse('Unable to prepare learning path input.', 500);
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

            return $this->errorResponse($message, 502, ['log_id' => $logId]);
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

            return $this->errorResponse($message, 504, ['log_id' => $logId]);
        } catch (\Throwable $exception) {
            $message = 'Learning path could not be generated.';
            $logId = $this->logLearningPath($candidateId, $requestPayload, [
                'status' => 'failed',
                'message' => $message,
            ]);
            Log::error('CIA learning path process could not be completed.', [
                'candidate_id' => $candidateId,
                'log_id' => $logId,
                'exception' => $exception->getMessage(),
            ]);

            return $this->errorResponse($message, 502, ['log_id' => $logId]);
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

            return $this->errorResponse($message, 502, ['log_id' => $logId]);
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

            return $this->errorResponse($message, 502, ['log_id' => $logId]);
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

            return $this->errorResponse($message, 502, ['log_id' => $logId]);
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
        $validated = $this->validateRequest($request, [
            'skills' => ['required', 'array', 'min:1', 'max:50'],
            'skills.*' => ['required', 'string', 'max:120', 'regex:/\S/'],
        ]);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }
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

            return $this->errorResponse('Unable to prepare vector-search input.', 500);
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
        } catch (ProcessStartFailedException $exception) {
            $failure = ['message' => 'Vector search service could not be started.'];
            $logId = $this->logSearchRequest($candidateId, $payload, $failure);
            Log::error('CIA vector search process could not be started.', [
                'candidate_id' => $candidateId,
                'log_id' => $logId,
                'exception' => $exception->getMessage(),
            ]);

            return $this->errorResponse($failure['message'], 502, ['log_id' => $logId]);
        } catch (ProcessTimedOutException $exception) {
            $result = ['message' => 'Vector search timed out.'];
            $logId = $this->logSearchRequest($candidateId, $payload, $result);
            Log::warning('CIA vector search timed out.', [
                'candidate_id' => $candidateId,
                'log_id' => $logId,
            ]);

            return $this->errorResponse($result['message'], 504, ['log_id' => $logId]);
        } catch (\Throwable $exception) {
            $failure = ['message' => 'Vector search could not be completed.'];
            $logId = $this->logSearchRequest($candidateId, $payload, $failure);
            Log::error('CIA vector search process could not be completed.', [
                'candidate_id' => $candidateId,
                'log_id' => $logId,
                'exception' => $exception->getMessage(),
            ]);

            return $this->errorResponse($failure['message'], 502, ['log_id' => $logId]);
        }

        if (! $process->isSuccessful()) {
            $result = ['message' => 'Vector search could not be completed.'];
            $logId = $this->logSearchRequest($candidateId, $payload, $result);
            Log::error('CIA vector search process failed.', [
                'candidate_id' => $candidateId,
                'log_id' => $logId,
                'exit_code' => $process->getExitCode(),
                'stderr' => $process->getErrorOutput(),
            ]);

            return $this->errorResponse($result['message'], 502, ['log_id' => $logId]);
        }

        try {
            $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            $failure = ['message' => 'Vector search returned an invalid response.'];
            $logId = $this->logSearchRequest($candidateId, $payload, $failure);
            Log::error('CIA vector search returned invalid JSON.', [
                'candidate_id' => $candidateId,
                'log_id' => $logId,
                'exception' => $exception->getMessage(),
            ]);

            return $this->errorResponse($failure['message'], 502, ['log_id' => $logId]);
        }

        if (
            ! is_array($result)
            || ! isset($result['matches'])
            || ! is_array($result['matches'])
            || count($result['matches']) !== 5
        ) {
            $failure = ['message' => 'Vector search returned an invalid response.'];
            $logId = $this->logSearchRequest($candidateId, $payload, $failure);
            Log::error('CIA vector search returned an invalid response shape.', [
                'candidate_id' => $candidateId,
                'log_id' => $logId,
            ]);

            return $this->errorResponse($failure['message'], 502, ['log_id' => $logId]);
        }

        $logId = $this->logSearchRequest($candidateId, $payload, $result);

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
            'target_role' => $requestPayload['target_role'] ?? null,
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
    private function logSearchRequest(string $candidateId, array $requestPayload, array $results): int
    {
        return DB::table('cia_search_logs')->insertGetId([
            'candidate_id' => $candidateId,
            'target_role' => $requestPayload['target_role'] ?? null,
            'request_payload' => json_encode($requestPayload, JSON_THROW_ON_ERROR),
            'results' => json_encode($results, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $skillProfile
     * @param  array{labels: list<string>, candidate_scores: list<float>, target_scores: list<int>}  $radarData
     * @return array{gaps: list<string>, priority_learning: list<string>}|JsonResponse
     */
    private function requestGroqAnalysis(array $skillProfile, array $radarData): array|JsonResponse
    {
        $fallback = $this->buildMockAnalysis($radarData);
        $apiKey = config('services.groq.api_key') ?: env('GROQ_API_KEY');
        $openRouterKey = config('services.openrouter.api_key') ?: env('OPENROUTER_API_KEY');

        if (
            (! is_string($apiKey) || trim($apiKey) === '')
            && (! is_string($openRouterKey) || trim($openRouterKey) === '')
        ) {
            Log::notice('LLM providers are not configured; using mock skill-gap analysis.');

            return $fallback;
        }

        try {
            $prompt = 'Analyze the skill profile for the target role. Return only a JSON object '
                . 'with string arrays "gaps" and "priority_learning". Identify actionable gaps by '
                . 'comparing candidate scores to target scores. Profile: '
                . json_encode([
                    'skill_profile' => $skillProfile,
                    'target_role' => $skillProfile['target_role'],
                    'radar_data' => $radarData,
                ], JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            Log::error('Groq skill-gap profile could not be encoded.', [
                'exception' => $exception->getMessage(),
            ]);

            return $this->errorResponse('Unable to prepare skill-gap analysis input.', 500);
        }

        $process = new Process([
            (string) env('CIA_PYTHON_BINARY', 'python'),
            base_path('scripts/llm_provider_fallback.py'),
            '--prompt',
            $prompt,
        ], base_path());
        $process->setEnv([
            'GROQ_API_KEY' => is_string($apiKey) ? $apiKey : '',
            'GROQ_MODEL' => (string) env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
            'OPENROUTER_API_KEY' => is_string($openRouterKey) ? $openRouterKey : '',
            'OPENROUTER_MODEL' => (string) env('OPENROUTER_MODEL', 'openai/gpt-4o-mini'),
        ]);
        $process->setTimeout(15);

        try {
            $process->run();
        } catch (ProcessStartFailedException $exception) {
            Log::error('CIA LLM failover process could not be started.', [
                'exception' => $exception->getMessage(),
            ]);

            return $this->errorResponse('Skill-gap analysis service could not be started.', 502);
        } catch (ProcessTimedOutException $exception) {
            Log::warning('CIA LLM failover process timed out.');

            return $this->errorResponse('Skill-gap analysis timed out. Please try again.', 504);
        } catch (\Throwable $exception) {
            Log::error('CIA LLM failover process could not be completed.', [
                'exception' => $exception->getMessage(),
            ]);

            return $this->errorResponse('Skill-gap analysis could not be completed.', 502);
        }

        try {
            $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            Log::error('CIA LLM failover process returned invalid JSON.', [
                'exception' => $exception->getMessage(),
                'stderr' => $process->getErrorOutput(),
            ]);

            return $this->errorResponse('Skill-gap analysis service returned an invalid response.', 502);
        }

        if (
            ! $process->isSuccessful()
            || ! is_array($result)
            || ($result['status'] ?? null) !== 'success'
        ) {
            $httpStatus = is_array($result) && in_array(
                $result['code'] ?? null,
                [422, 502, 504],
                true
            ) ? $result['code'] : 502;
            Log::warning('CIA LLM provider request failed.', [
                'http_status' => $httpStatus,
                'stderr' => $process->getErrorOutput(),
            ]);

            return $this->errorResponse(
                is_array($result) && is_string($result['message'] ?? null)
                    ? $result['message']
                    : 'Skill-gap analysis providers are currently unavailable.',
                $httpStatus
            );
        }

        $responseText = $result['data']['response'] ?? null;
        $decoded = is_string($responseText) ? json_decode($responseText, true) : null;
        if (
            ! is_array($decoded)
            || ! $this->isStringList($decoded['gaps'] ?? null)
            || ! $this->isStringList($decoded['priority_learning'] ?? null)
        ) {
            Log::warning('LLM skill-gap response had an invalid JSON shape.');

            return $this->errorResponse('Skill-gap analysis service returned an invalid response.', 502);
        }

        return [
            'gaps' => array_values(array_slice($decoded['gaps'], 0, 10)),
            'priority_learning' => array_values(array_slice($decoded['priority_learning'], 0, 10)),
        ];
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>|JsonResponse
     */
    private function validateRequest(Request $request, array $rules): array|JsonResponse
    {
        try {
            return $request->validate($rules);
        } catch (ValidationException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'The provided data is invalid.',
                'errors' => $exception->errors(),
            ], 422);
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function errorResponse(string $message, int $status, array $extra = []): JsonResponse
    {
        return response()->json([
            'status' => 'error',
            'message' => $message,
            ...$extra,
        ], $status);
    }

    public function searchHistory(Request $request): JsonResponse
    {
        $validated = $this->validateRequest($request, [
            'target_role' => ['sometimes', 'string', 'max:120'],
        ]);
        if ($validated instanceof JsonResponse) {
            return $validated;
        }

        $query = DB::table('cia_search_logs')
            ->where('candidate_id', (string) $request->user()->getAuthIdentifier());
        if (isset($validated['target_role'])) {
            $query->where('target_role', trim($validated['target_role']));
        }

        return response()->json([
            'search_history' => $query
                ->orderByDesc('created_at')
                ->limit(50)
                ->get(['id', 'target_role', 'request_payload', 'results', 'created_at']),
        ]);
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
