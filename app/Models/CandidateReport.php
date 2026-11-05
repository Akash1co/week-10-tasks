<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CandidateReport extends Model
{
    protected $fillable = [
        'user_id',
        'report_type',
        'overall_employability_score',
        'archetype_scores',
        'narrative_summary',
        'recommendations',
        'pdf_path',
    ];

    protected $casts = [
        'overall_employability_score' => 'float',
        'archetype_scores' => 'array',
        'recommendations' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
