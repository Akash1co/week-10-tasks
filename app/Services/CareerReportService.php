<?php

namespace App\Services;

use App\Models\CandidateReport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Throwable;

class CareerReportService
{
    private const ARCHETYPES = [
        'Builder',
        'Analyst',
        'Leader',
        'Innovator',
        'Specialist',
        'Generalist',
    ];

    /**
     * @param  array<string, mixed>  $profile
     */
    public function generate(int $userId, string $candidateName, array $profile): CandidateReport
    {
        try {
            $input = json_encode([
                'candidate_name' => $candidateName,
                'profile' => $profile,
            ], JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Candidate profile could not be prepared.', 0, $exception);
        }

        $process = new Process([
            (string) env('CIA_PYTHON_BINARY', 'python'),
            base_path('scripts/career_report_generator.py'),
        ], base_path());
        $process->setInput($input);
        $process->setEnv([
            'GROQ_API_KEY' => (string) (config('services.groq.api_key') ?: env('GROQ_API_KEY', '')),
            'GROQ_MODEL' => (string) env('GROQ_MODEL', 'llama-3.3-70b-versatile'),
        ]);
        $process->setTimeout(120);

        try {
            $process->run();
        } catch (ProcessStartFailedException $exception) {
            throw new RuntimeException('Career report generator could not be started.', 0, $exception);
        } catch (ProcessTimedOutException $exception) {
            throw new RuntimeException('Career report generation timed out. Please try again.', 0, $exception);
        }

        if (! $process->isSuccessful()) {
            Log::error('Career report generator process failed.', [
                'exit_code' => $process->getExitCode(),
                'stderr' => $process->getErrorOutput(),
            ]);

            throw new RuntimeException('Career report could not be generated.');
        }

        try {
            $result = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Career report service returned invalid JSON.', 0, $exception);
        }

        $reportData = $this->validateReport($result);
        $pdfContents = $this->renderPdf($candidateName, $reportData);
        $pdfPath = 'reports/career_report_'.$userId.'_'.now()->format('Ymd_His_u').'_'.Str::random(8).'.pdf';

        if (! Storage::disk('public')->put($pdfPath, $pdfContents)) {
            throw new RuntimeException('Career report PDF could not be saved.');
        }

        try {
            return CandidateReport::query()->create([
                'user_id' => $userId,
                'report_type' => 'career_intelligence',
                'overall_employability_score' => $reportData['overall_employability_score'],
                'archetype_scores' => $reportData['archetype_scores'],
                'narrative_summary' => $reportData['narrative_summary'],
                'recommendations' => $reportData['recommendations'],
                'pdf_path' => $pdfPath,
            ]);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($pdfPath);
            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $reportData
     * @return array{
     *     archetype_scores: array<string, float>,
     *     overall_employability_score: float,
     *     narrative_summary: string,
     *     recommendations: list<string>
     * }
     */
    private function validateReport(mixed $reportData): array
    {
        if (! is_array($reportData)) {
            throw new RuntimeException('Career report service returned an invalid response.');
        }

        $scores = $reportData['archetype_scores'] ?? null;
        if (! is_array($scores) || array_diff(self::ARCHETYPES, array_keys($scores)) !== []) {
            throw new RuntimeException('Career report service returned invalid archetype scores.');
        }

        $normalizedScores = [];
        foreach (self::ARCHETYPES as $archetype) {
            $score = $scores[$archetype] ?? null;
            if (! is_numeric($score) || ! is_finite((float) $score) || $score < 0 || $score > 100) {
                throw new RuntimeException('Career report service returned an out-of-range archetype score.');
            }
            $normalizedScores[$archetype] = round((float) $score, 1);
        }

        $employabilityScore = $reportData['overall_employability_score'] ?? null;
        $narrative = $reportData['narrative_summary'] ?? null;
        $recommendations = $reportData['recommendations'] ?? null;

        if (
            ! is_numeric($employabilityScore)
            || ! is_finite((float) $employabilityScore)
            || $employabilityScore < 0
            || $employabilityScore > 100
            || ! is_string($narrative)
            || trim($narrative) === ''
            || ! is_array($recommendations)
            || ! array_is_list($recommendations)
            || count($recommendations) < 3
            || count(array_filter(
                $recommendations,
                fn (mixed $recommendation): bool => is_string($recommendation) && trim($recommendation) !== ''
            )) !== count($recommendations)
        ) {
            throw new RuntimeException('Career report service returned incomplete report content.');
        }

        return [
            'archetype_scores' => $normalizedScores,
            'overall_employability_score' => round((float) $employabilityScore, 1),
            'narrative_summary' => trim($narrative),
            'recommendations' => array_values(array_map('trim', $recommendations)),
        ];
    }

    /**
     * @param  array{
     *     archetype_scores: array<string, float>,
     *     overall_employability_score: float,
     *     narrative_summary: string,
     *     recommendations: list<string>
     * }  $reportData
     */
    private function renderPdf(string $candidateName, array $reportData): string
    {
        $pdfFacade = 'Barryvdh\\DomPDF\\Facade\\Pdf';
        if (! class_exists($pdfFacade)) {
            throw new RuntimeException('PDF export is unavailable because the dompdf package is not installed.');
        }

        return $pdfFacade::loadView('pdf.career-report', [
            'candidateName' => $candidateName,
            'report' => $reportData,
            'generatedAt' => now(),
        ])->setPaper('a4')->output();
    }
}
