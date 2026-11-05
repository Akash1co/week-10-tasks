<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('placement_readiness_scores', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->float('project_completion_score');
            $table->float('assessment_score');
            $table->float('cia_usage_score');
            $table->float('docs_score');
            $table->float('placement_readiness_score');
            $table->float('viva_score');
            $table->float('code_review_score');
            $table->float('demo_clarity_score');
            $table->float('git_quality_score');
            $table->float('interview_readiness_score');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('placement_readiness_scores');
    }
};
