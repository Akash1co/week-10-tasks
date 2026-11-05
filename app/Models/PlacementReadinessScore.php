<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlacementReadinessScore extends Model
{
    protected $fillable = [
        'user_id',
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

    protected $casts = [
        'project_completion_score' => 'float',
        'assessment_score' => 'float',
        'cia_usage_score' => 'float',
        'docs_score' => 'float',
        'placement_readiness_score' => 'float',
        'viva_score' => 'float',
        'code_review_score' => 'float',
        'demo_clarity_score' => 'float',
        'git_quality_score' => 'float',
        'interview_readiness_score' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
