<?php

use App\Http\Controllers\CiaController;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

$ciaTelemetryResponse = static function (JsonResponse $response): JsonResponse {
    $request = request();
    $status = $response->getStatusCode();

    Log::log(
        $status >= 500 ? 'error' : ($status >= 400 ? 'warning' : 'info'),
        'CIA_CAREER_TELEMETRY',
        [
            'route_name' => $request->route()?->getName(),
            'http_method' => $request->method(),
            'http_status' => $status,
        ]
    );

    return $response;
};

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
    ->group(function () use ($ciaTelemetryResponse) {
        Route::get('/dashboard', function () use ($ciaTelemetryResponse) {
            return $ciaTelemetryResponse(response()->json([
                'message' => 'Career intelligence dashboard endpoint is ready.',
            ]));
        })->name('cia.career.dashboard');

        Route::get('/profiles/{candidate_id}', function (string $candidate_id) use ($ciaTelemetryResponse) {
            return $ciaTelemetryResponse(response()->json([
                'message' => 'Candidate profile endpoint is ready.',
                'candidate_id' => $candidate_id,
            ]));
        })->name('cia.career.profiles.show');

        Route::post('/profiles', function () use ($ciaTelemetryResponse) {
            return $ciaTelemetryResponse(response()->json([
                'message' => 'Candidate profile creation endpoint is ready.',
            ], 201));
        })->name('cia.career.profiles.store');

        Route::put('/profiles/{candidate_id}/skills', function (string $candidate_id) use ($ciaTelemetryResponse) {
            return $ciaTelemetryResponse(response()->json([
                'message' => 'Candidate skills update endpoint is ready.',
                'candidate_id' => $candidate_id,
            ]));
        })->name('cia.career.profiles.update_skills');

        Route::get('/recommendations/{candidate_id}', function (string $candidate_id) use ($ciaTelemetryResponse) {
            return $ciaTelemetryResponse(response()->json([
                'message' => 'Candidate recommendations endpoint is ready.',
                'candidate_id' => $candidate_id,
                'recommendations' => [],
            ]));
        })->name('cia.career.recommendations');

        Route::get('/analytics', function () use ($ciaTelemetryResponse) {
            return $ciaTelemetryResponse(response()->json([
                'message' => 'Career analytics endpoint is ready.',
            ]));
        })->name('cia.career.analytics');
    });
