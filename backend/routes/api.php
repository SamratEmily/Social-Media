<?php

use App\Http\Controllers\AuthController;
use Illuminate\Support\Facades\Route;

// Public routes (rate limited: 5 attempts per minute per IP to slow brute force)
Route::middleware('throttle:5,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

// Protected routes
Route::middleware(['auth:api', 'throttle:60,1'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/refresh', [AuthController::class, 'refresh']);
    
    // Posts
    Route::get('/posts', [\App\Http\Controllers\PostController::class, 'index']);
    Route::post('/posts', [\App\Http\Controllers\PostController::class, 'store']);
    Route::post('/posts/{id}/like', [\App\Http\Controllers\PostController::class, 'like']);
    Route::get('/posts/{id}/comments', [\App\Http\Controllers\PostController::class, 'getComments']);
    Route::post('/posts/{id}/comments', [\App\Http\Controllers\PostController::class, 'addComment']);
    Route::post('/comments/{id}/like', [\App\Http\Controllers\PostController::class, 'likeComment']);
    Route::post('/comments/{id}/replies', [\App\Http\Controllers\PostController::class, 'addReply']);
    Route::post('/replies/{id}/like', [\App\Http\Controllers\PostController::class, 'likeReply']);
});
