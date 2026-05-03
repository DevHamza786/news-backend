<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\ArticleChatController;
use App\Http\Controllers\Api\ArticleController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\FactCheckController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| - Authentication: register, login (public), logout (Sanctum protected).
| - Article listing: GET /articles (public).
| - Article details: GET /articles/{article} (public).
| - Sending chat messages: POST /chat-rooms/{chatRoom}/messages (Sanctum protected).
| - All other write/read-protected routes use auth:sanctum.
|
*/

// -------------------------------------------------------------------------
// Authentication
// -------------------------------------------------------------------------
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
});

// -------------------------------------------------------------------------
// Article listing & article details (public)
// -------------------------------------------------------------------------
Route::get('articles', [ArticleController::class, 'index']);
Route::get('articles/{article}', [ArticleController::class, 'show']);

// -------------------------------------------------------------------------
// Protected routes (Laravel Sanctum)
// -------------------------------------------------------------------------
Route::middleware('auth:sanctum')->group(function () {
    Route::get('user', fn (\Illuminate\Http\Request $request) => response()->json([
        'id' => $request->user()->id,
        'name' => $request->user()->name,
        'email' => $request->user()->email,
        'is_admin' => $request->user()->is_admin,
    ]));

    // Article create/update/delete
    Route::post('articles', [ArticleController::class, 'store']);
    Route::put('articles/{article}', [ArticleController::class, 'update']);
    Route::patch('articles/{article}', [ArticleController::class, 'update']);
    Route::delete('articles/{article}', [ArticleController::class, 'destroy']);

    // Article chat (WebSocket server uses these)
    Route::get('articles/{article}/chat-room', [ArticleChatController::class, 'getOrCreateRoom']);
    Route::post('articles/{article}/messages', [ArticleChatController::class, 'storeMessage']);
    Route::post('articles/{article}/chat-upload', [ArticleChatController::class, 'uploadImage']);

    // Chat rooms & sending messages
    Route::prefix('chat-rooms')->group(function () {
        Route::get('/', [ChatController::class, 'indexRooms']);
        Route::post('/', [ChatController::class, 'storeRoom']);
        Route::get('{chatRoom}', [ChatController::class, 'showRoom']);
        Route::get('{chatRoom}/messages', [ChatController::class, 'indexMessages']);
        Route::post('{chatRoom}/messages', [ChatController::class, 'storeMessage']);
    });

    // Fact checks
    Route::apiResource('fact-checks', FactCheckController::class);

    // Admin portal
    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('dashboard', [AdminController::class, 'dashboard']);
        Route::get('articles', [AdminController::class, 'articles']);
        Route::patch('articles/{article}/status', [AdminController::class, 'updateArticleStatus']);
        Route::get('chat-rooms', [AdminController::class, 'chatRooms']);
        Route::get('chat-rooms/{chatRoom}', [AdminController::class, 'showChatRoom']);
        Route::delete('messages/{message}', [AdminController::class, 'destroyMessage']);
    });
});
