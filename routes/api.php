<?php

use App\Http\Controllers\CommunicationController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

// Main endpoint - Sync messages and return threads
//Route::get('/communication', [CommunicationController::class, 'index']);

// In routes/api.php or any controller
Route::get('/timezone', function () {
    $dateTime = now()->setTimezone('Europe/Podgorica')->format('d.m.Y H:i:s');
    return response()->json(['Date time' => $dateTime]);
});

Route::group([
    'middleware' => 'api',
    'prefix' => 'auth'
], function ($router) {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:api');
    Route::post('/refresh', [AuthController::class, 'refresh'])->middleware('auth:api');
    Route::get('/profile', [AuthController::class, 'profile'])->middleware('auth:api');
    Route::get('/me', [AuthController::class, 'me'])->middleware('auth:api');
});

// Protected routes example
Route::group([
    'middleware' => ['api', 'auth:api'],
    'prefix' => 'v1'
], function () {
    Route::get('/dashboard', function () {
        return response()->json(['message' => 'Welcome to dashboard']);
    });
});

/*
|--------------------------------------------------------------------------
| Messaging System Routes
|--------------------------------------------------------------------------
*/

// Public routes (bez auth - za testiranje)
// Kasnije dodaj middleware(['auth:sanctum']) kada testiraš sa JWT
//Route::prefix('communication')->group(function () {
//
//    // GET /api/communication - Sync and get all threads with new messages
//    Route::get('/', [CommunicationController::class, 'index'])
//        ->name('communication.index');
//
//    // POST /api/communication/sync - Manual sync trigger
//    Route::post('/sync', [CommunicationController::class, 'sync'])
//        ->name('communication.sync');
//
//    // GET /api/communication/stats - System statistics
//    Route::get('/stats', [CommunicationController::class, 'stats'])
//        ->name('communication.stats');
//
//    // PATCH /api/communication/threads/{threadId}/read - Mark thread as read
//    Route::patch('/threads/{threadId}/read', [CommunicationController::class, 'markAsRead'])
//        ->name('communication.threads.read');
//
//    // GET /api/communication/threads/{threadId} - Get single thread with messages
//    Route::get('/threads/{threadId}', [CommunicationController::class, 'show'])
//        ->name('communication.threads.show');
//
//    // GET /api/communication/channels - List all channels
//    Route::get('/channels', [CommunicationController::class, 'channels'])
//        ->name('communication.channels');
//
//    // GET /api/communication/channels/{channelId}/health - Channel health check
//    Route::get('/channels/{channelId}/health', [CommunicationController::class, 'channelHealth'])
//        ->name('communication.channels.health');
//});


/*
|--------------------------------------------------------------------------
| API Routes - Week 1 (Pull Only)
|--------------------------------------------------------------------------
*/

Route::prefix('communication')->group(function () {
    // Main endpoint - Sync and return threads
    Route::get('/', [CommunicationController::class, 'index']);

    // Manual sync trigger
    Route::post('/sync', [CommunicationController::class, 'sync']);

    // Get single thread with messages
    Route::get('/threads/{id}', [CommunicationController::class, 'showThread']);
});
