<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;


// Public routes (tidak perlu token)
Route::post('/login', [AuthController::class, 'login']);

// Protected routes (perlu token)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
});
