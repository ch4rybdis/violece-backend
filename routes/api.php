<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\{RegisterController, LoginController};
use App\Http\Controllers\Api\Psychology\QuestionnaireController;

Route::middleware('auth:sanctum')->prefix('psychology')->group(function () {
    // Questionnaire endpoints
    Route::get('/questionnaire/questions', [QuestionnaireController::class, 'getQuestions']);
    Route::post('/questionnaire/submit', [QuestionnaireController::class, 'submitQuestionnaire']);

    // Profile endpoints
    Route::get('/profile', [QuestionnaireController::class, 'getProfile']);
    Route::get('/profile/compatibility/{userId}', [QuestionnaireController::class, 'getCompatibilityScore']);

    // Analytics endpoints (for later)
    Route::get('/analytics/traits-distribution', [QuestionnaireController::class, 'getTraitsDistribution']);
});

// Public Auth Routes
Route::prefix('auth')->group(function () {
    Route::post('register', [RegisterController::class, 'register']);
    Route::post('login', [LoginController::class, 'login']);

    // TODO: Verification routes - will be implemented later
    // Route::post('verify-sms', [VerificationController::class, 'verifySMS']);
    // Route::post('verify-email', [VerificationController::class, 'verifyEmail']);
    // Route::post('resend-verification', [VerificationController::class, 'resend']);
});

// Protected Auth Routes
Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'logout']);
    Route::get('me', [LoginController::class, 'me']);
});

// API Health Check
Route::get('health', function () {
    return response()->json([
        'status' => 'online',
        'service' => 'Violece API',
        'version' => '1.0',
        'timestamp' => now()->toISOString()
    ]);
});
