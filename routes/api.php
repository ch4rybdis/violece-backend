<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\{RegisterController, LoginController};
use App\Http\Controllers\Api\Psychology\QuestionnaireController;
use App\Http\Controllers\Api\Matching\MatchingController;

/*
|--------------------------------------------------------------------------
| API Routes - Clean Version (No Duplicates)
|--------------------------------------------------------------------------
*/

// Public Auth Routes
Route::prefix('auth')->group(function () {
    Route::post('register', [RegisterController::class, 'register']);
    Route::post('login', [LoginController::class, 'login']);
});

// Protected Auth Routes
Route::middleware('auth:sanctum')->prefix('auth')->group(function () {
    Route::post('logout', [LoginController::class, 'logout']);
    Route::get('me', [LoginController::class, 'me']);
});

// Psychology System Routes
Route::middleware('auth:sanctum')->prefix('psychology')->group(function () {
    // Questionnaire endpoints
    Route::get('/questionnaire/questions', [QuestionnaireController::class, 'getQuestions']);
    Route::post('/questionnaire/submit', [QuestionnaireController::class, 'submitQuestionnaire']);

    // Profile Management
    Route::get('/profile', [QuestionnaireController::class, 'getProfile']);
    Route::get('/profile/compatibility/{userId}', [QuestionnaireController::class, 'getCompatibilityScore']);

    // Analytics & Insights
    Route::get('/analytics/traits-distribution', [QuestionnaireController::class, 'getTraitsDistribution']);
    Route::get('/insights/personality-summary', [QuestionnaireController::class, 'getPersonalitySummary']);
});

// Matching and Dating Routes
Route::middleware('auth:sanctum')->prefix('matching')->group(function () {
    // Discovery and Compatibility
    Route::get('/potential-matches', [MatchingController::class, 'getPotentialMatches']);
    Route::get('/compatibility/{userId}', [MatchingController::class, 'getCompatibilityAnalysis']);

    // Swipe Actions
    Route::post('/like/{userId}', [MatchingController::class, 'likeUser']);
    Route::post('/pass/{userId}', [MatchingController::class, 'passUser']);
    Route::post('/super-like/{userId}', [MatchingController::class, 'superLikeUser']);

    // Match Management
    Route::get('/matches', [MatchingController::class, 'getUserMatches']);
    Route::get('/stats', [MatchingController::class, 'getUserStats']);

    // Advanced Features (Future Implementation)
    // Route::post('/boost', [MatchingController::class, 'boostProfile']);
    // Route::post('/rewind', [MatchingController::class, 'undoLastSwipe']);
    // Route::post('/matches/{matchId}/unmatch', [MatchingController::class, 'unmatch']);
});

// API Health Check
Route::get('health', function () {
    return response()->json([
        'status' => 'online',
        'service' => 'Violece API',
        'version' => '1.0',
        'timestamp' => now()->toISOString(),
        'environment' => app()->environment()
    ]);
});
