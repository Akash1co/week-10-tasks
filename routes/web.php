<?php

use App\Http\Controllers\CiaController;
use Illuminate\Support\Facades\Route;

Route::get('/candidate/cia/career/readiness-scores', [CiaController::class, 'readinessScores'])
    ->middleware(['auth'])
    ->name('cia.career.readiness_scores');

Route::post('/candidate/cia/career/calculate-scores', [CiaController::class, 'calculateReadinessScores'])
    ->middleware(['auth'])
    ->name('cia.career.calculate_scores');

Route::post('/candidate/cia/career/skill-gap', [CiaController::class, 'skillGap'])
    ->middleware(['auth'])
    ->name('cia.career.skill_gap');

Route::post('/candidate/cia/career/recommendations', [CiaController::class, 'careerRecommendations'])
    ->middleware(['auth'])
    ->name('cia.career.recommendations.generate');

Route::post('/candidate/cia/career/learning-path', [CiaController::class, 'learningPath'])
    ->middleware(['auth'])
    ->name('cia.career.learning_path');

Route::post('/candidate/cia/career/vector-search', [CiaController::class, 'vectorSearch'])
    ->middleware(['auth'])
    ->name('cia.career.vector_search');

Route::prefix('cia/career')
    ->middleware(['auth'])
    ->group(function () {
        Route::get('/dashboard', function () {
            return response()->json([
                'message' => 'Career intelligence dashboard endpoint is ready.',
            ]);
        })->name('cia.career.dashboard');

        Route::get('/profiles/{candidate_id}', function (string $candidate_id) {
            return response()->json([
                'message' => 'Candidate profile endpoint is ready.',
                'candidate_id' => $candidate_id,
            ]);
        })->name('cia.career.profiles.show');

        Route::post('/profiles', function () {
            return response()->json([
                'message' => 'Candidate profile creation endpoint is ready.',
            ], 201);
        })->name('cia.career.profiles.store');

        Route::put('/profiles/{candidate_id}/skills', function (string $candidate_id) {
            return response()->json([
                'message' => 'Candidate skills update endpoint is ready.',
                'candidate_id' => $candidate_id,
            ]);
        })->name('cia.career.profiles.update_skills');

        Route::get('/recommendations/{candidate_id}', function (string $candidate_id) {
            return response()->json([
                'message' => 'Candidate recommendations endpoint is ready.',
                'candidate_id' => $candidate_id,
                'recommendations' => [],
            ]);
        })->name('cia.career.recommendations');

        Route::get('/analytics', function () {
            return response()->json([
                'message' => 'Career analytics endpoint is ready.',
            ]);
        })->name('cia.career.analytics');
    });
