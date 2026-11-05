<?php

namespace App\Services;

use App\Models\PlacementReadinessScore;

class ReadinessScoreService
{
    private const METRIC_FIELDS = [
        'project_completion_score',
        'assessment_score',
        'cia_usage_score',
        'docs_score',
        'viva_score',
        'code_review_score',
        'demo_clarity_score',
        'git_quality_score',
    ];

    public function calculateAndSave(int $userId, array $metrics): PlacementReadinessScore
    {
        $scores = [];
        foreach (self::METRIC_FIELDS as $field) {
            $value = $metrics[$field] ?? 0;
            $score = is_numeric($value) ? (float) $value : 0.0;
            $scores[$field] = is_finite($score) ? $score : 0.0;
        }

        $scores['placement_readiness_score'] = round(
            ($scores['project_completion_score'] * 0.40)
            + ($scores['assessment_score'] * 0.30)
            + ($scores['cia_usage_score'] * 0.10)
            + ($scores['docs_score'] * 0.20),
            2
        );
        $scores['interview_readiness_score'] = round(
            ($scores['viva_score'] * 0.40)
            + ($scores['code_review_score'] * 0.30)
            + ($scores['demo_clarity_score'] * 0.20)
            + ($scores['git_quality_score'] * 0.10),
            2
        );

        return PlacementReadinessScore::query()->updateOrCreate(
            ['user_id' => $userId],
            $scores
        );
    }
}
