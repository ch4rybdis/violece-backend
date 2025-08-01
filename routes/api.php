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


// Add these to your routes/api.php file

// Media Routes
Route::middleware('auth:sanctum')->prefix('media')->group(function () {
    Route::post('/upload', [MediaController::class, 'upload']);
    Route::delete('/delete/{id}', [MediaController::class, 'delete']);
});

// Messaging Status Routes
Route::middleware('auth:sanctum')->prefix('messaging')->group(function () {
    Route::get('/unread-count', [MessagingController::class, 'getUnreadMessageCount']);
    Route::post('/typing/{match}', [MessagingController::class, 'sendTypingIndicator']);
});

// Unmatch Routes
Route::middleware('auth:sanctum')->prefix('matching')->group(function () {
    Route::post('/matches/{matchId}/unmatch', [MatchingController::class, 'unmatch']);
});

// User Profile Routes
Route::middleware('auth:sanctum')->prefix('profile')->group(function () {
    Route::get('/', [ProfileController::class, 'show']);
    Route::put('/', [ProfileController::class, 'update']);
    Route::post('/photos', [ProfileController::class, 'addPhoto']);
    Route::delete('/photos/{id}', [ProfileController::class, 'removePhoto']);
    Route::put('/preferences', [ProfileController::class, 'updatePreferences']);
    Route::put('/location', [ProfileController::class, 'updateLocation']);
});

// Notifications Routes
Route::middleware('auth:sanctum')->prefix('notifications')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::post('/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/fcm-token', [NotificationController::class, 'updateFcmToken']);
    Route::put('/preferences', [NotificationController::class, 'updatePreferences']);
});

Route::middleware('auth:sanctum')->prefix('messaging')->group(function () {
    Route::get('/matches/{match}/messages', [MessagingController::class, 'getMessages']);
    Route::post('/matches/{match}/messages', [MessagingController::class, 'sendMessage']);
    Route::post('/matches/{match}/read', [MessagingController::class, 'markAsRead']);
});

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
