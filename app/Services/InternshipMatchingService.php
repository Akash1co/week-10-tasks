<?php

namespace App\Services;

use App\Models\LessonUser;
use App\Models\QuizAttempt;
use Illuminate\Support\Facades\Log;
use JsonException;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class InternshipMatchingService
{
    private const FALLBACK_SKILLS = ['PHP', 'Laravel', 'SQL', 'JavaScript', 'Git'];

    /**
     * @param  array<int, string>|null  $customSkills
     * @return array{
     *     status: string,
     *     total_matched: int,
     *     candidate_skills: array<int, string>,
     *     matches: array<int, array<string, mixed>>
     * }
     */
    public function matchInternships(int $userId, ?array $customSkills = null): array
    {
        $skills = $this->normalizeSkills($customSkills ?? $this->skillsFromHistory($userId));
        if ($skills === []) {
            $skills = self::FALLBACK_SKILLS;
        }

        try {
            $input = json_encode(['skills' => $skills], JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Candidate skills could not be prepared.', 0, $exception);
        }

        $process = new Process([
            (string) env('CIA_PYTHON_BINARY', 'python'),
            base_path('scripts/internship_matcher.py'),
            $input,
        ], base_path());
        $process->setTimeout(90);

        try {
            $process->run();
        } catch (ProcessStartFailedException $exception) {
            throw new RuntimeException('Internship matcher could not be started.', 0, $exception);
        } catch (ProcessTimedOutException $exception) {
            throw new RuntimeException('Internship matching timed out. Please try again.', 0, $exception);
        }

        if (! $process->isSuccessful()) {
            Log::error('Internship matcher process failed.', [
                'user_id' => $userId,
                'exit_code' => $process->getExitCode(),
                'stderr' => $process->getErrorOutput(),
            ]);

            throw new RuntimeException('Internship matches could not be generated.');
        }

        try {
            $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Internship matcher returned invalid JSON.', 0, $exception);
        }

        if (
            ! is_array($result)
            || ($result['status'] ?? null) !== 'success'
            || ! is_int($result['total_matched'] ?? null)
            || $result['total_matched'] !== 5
            || ! is_array($result['candidate_skills'] ?? null)
            || ! is_array($result['matches'] ?? null)
            || count($result['matches']) !== 5
        ) {
            throw new RuntimeException('Internship matcher returned an invalid response.');
        }

        return $result;
    }

    /**
     * @return array<int, string>
     */
    private function skillsFromHistory(int $userId): array
    {
        $quizSkills = QuizAttempt::query()
            ->where('user_id', $userId)
            ->whereNotNull('completed_at')
            ->whereNotNull('skill')
            ->latest('completed_at')
            ->limit(100)
            ->pluck('skill')
            ->all();

        $completedLearning = LessonUser::query()
            ->join('lessons', 'lessons.id', '=', 'lesson_user.lesson_id')
            ->where('lesson_user.user_id', $userId)
            ->whereNotNull('lesson_user.completed_at')
            ->latest('lesson_user.completed_at')
            ->limit(30)
            ->get(['lessons.title', 'lessons.module_name'])
            ->flatMap(fn ($lesson): array => [
                (string) ($lesson->module_name ?: ''),
                (string) ($lesson->title ?: ''),
            ])
            ->all();

        return $this->normalizeSkills([...$quizSkills, ...$completedLearning]);
    }

    /**
     * @param  array<int, mixed>  $skills
     * @return array<int, string>
     */
    private function normalizeSkills(array $skills): array
    {
        $normalized = [];
        $seen = [];

        foreach ($skills as $skill) {
            if (! is_string($skill) || trim($skill) === '') {
                continue;
            }

            $value = trim($skill);
            $key = mb_strtolower($value);
            if (! isset($seen[$key])) {
                $normalized[] = $value;
                $seen[$key] = true;
            }
        }

        return $normalized;
    }
}
