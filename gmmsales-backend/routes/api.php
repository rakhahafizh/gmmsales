<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\AdminCustomerController;
use App\Http\Controllers\AdminSalesController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\DashboardController;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);

    // Profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::post('/profile/photo', [ProfileController::class, 'uploadPhoto']);

    // Dashboard (sales)
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Product (dropdown untuk semua user)
    Route::get('/products', [ProductController::class, 'index']);

    // Customer (sales)
    Route::post('/customers', [CustomerController::class, 'store']);
    Route::get('/customers', [CustomerController::class, 'index']);
    Route::get('/customers/history', [CustomerController::class, 'history']);
    Route::get('/customers/{id}', [CustomerController::class, 'show']);
    Route::put('/customers/{id}', [CustomerController::class, 'update']);
    Route::delete('/customers/{id}', [CustomerController::class, 'destroy']);

    // Admin only
    Route::middleware('is_admin')->prefix('admin')->group(function () {
        // Dashboard
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);

        // Customer management
        Route::get('/customers', [AdminCustomerController::class, 'index']);
        Route::get('/customers/{id}', [AdminCustomerController::class, 'show']);
        Route::put('/customers/{id}', [AdminCustomerController::class, 'update']);
        Route::delete('/customers/{id}', [AdminCustomerController::class, 'destroy']);

        // Sales management
        Route::post('/sales', [AdminSalesController::class, 'store']);
        Route::get('/sales', [AdminSalesController::class, 'index']);
        Route::get('/sales/{id}', [AdminSalesController::class, 'show']);
        Route::put('/sales/{id}', [AdminSalesController::class, 'update']);
        Route::delete('/sales/{id}', [AdminSalesController::class, 'destroy']);

        // Product management
        Route::post('/products', [ProductController::class, 'store']);
        Route::put('/products/{id}', [ProductController::class, 'update']);
        Route::delete('/products/{id}', [ProductController::class, 'destroy']);
    });
});