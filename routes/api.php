<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\{RegisterController, LoginController};
use App\Http\Controllers\Api\Psychology\QuestionnaireController;
use App\Http\Controllers\Api\Matching\MatchingController;

// Matching and Dating Routes
Route::prefix('matching')->middleware('auth:sanctum')->group(function () {
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

    // Advanced Features (for future implementation)
    Route::post('/boost', [MatchingController::class, 'boostProfile']); // Premium feature
    Route::post('/rewind', [MatchingController::class, 'undoLastSwipe']); // Premium feature
});

Route::middleware('auth:sanctum')->prefix('psychology')->group(function () {
    // Questionnaire endpoints
    Route::get('/questionnaire/questions', [QuestionnaireController::class, 'getQuestions'])
        ->name('questionnaire.questions');

    Route::post('/questionnaire/submit', [QuestionnaireController::class, 'submitQuestionnaire'])
        ->name('questionnaire.submit');

    // Profile Management
    Route::get('/profile', [QuestionnaireController::class, 'getProfile'])
        ->name('profile.get');

    Route::get('/profile/compatibility/{userId}', [QuestionnaireController::class, 'getCompatibilityScore'])
        ->name('profile.compatibility');

    // Analytics & Insights
    Route::get('/analytics/traits-distribution', [QuestionnaireController::class, 'getTraitsDistribution'])
        ->name('analytics.traits');

    Route::get('/insights/personality-summary', [QuestionnaireController::class, 'getPersonalitySummary'])
        ->name('insights.summary');
});


Route::middleware('auth:sanctum')->prefix('matching')->name('matching.')->group(function () {
    // Core Matching
    Route::get('/potential-matches', [MatchingController::class, 'getPotentialMatches'])
        ->name('potential.matches');

    Route::get('/compatibility/{userId}', [MatchingController::class, 'getCompatibilityAnalysis'])
        ->name('compatibility.analysis');

    // User Actions
    Route::post('/like/{userId}', [MatchingController::class, 'likeUser'])
        ->name('like.user');

    Route::post('/pass/{userId}', [MatchingController::class, 'passUser'])
        ->name('pass.user');

    Route::post('/super-like/{userId}', [MatchingController::class, 'superLikeUser'])
        ->name('super.like');

    // Match Management
    Route::get('/matches', [MatchingController::class, 'getMatches'])
        ->name('get.matches');

    Route::post('/matches/{matchId}/unmatch', [MatchingController::class, 'unmatch'])
        ->name('unmatch');

    // Advanced Features
    Route::get('/discovery/preferences', [MatchingController::class, 'getDiscoveryPreferences'])
        ->name('discovery.preferences');

    Route::post('/discovery/preferences', [MatchingController::class, 'updateDiscoveryPreferences'])
        ->name('discovery.update');
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
