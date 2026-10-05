<?php

use App\Http\Controllers\Api\V1\AdminController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\VendorProductController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {

    // 1. Authentication Endpoints
    Route::prefix('auth')->group(function () {
        Route::post('/register', [AuthController::class, 'register']);
        Route::post('/login', [AuthController::class, 'login']);

        Route::middleware('auth:sanctum')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/me', [AuthController::class, 'me']);
        });
    });

    // 2. Public Catalog
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{identifier}', [ProductController::class, 'show']);

    // 3. Customer Orders & Checkout
    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('/orders/checkout', [OrderController::class, 'checkout'])->middleware('role:customer');
        Route::get('/orders', [OrderController::class, 'index']);
        Route::get('/orders/{id}', [OrderController::class, 'show']);
    });

    // 4. Vendor Storefront Portal
    Route::middleware(['auth:sanctum', 'role:vendor'])->prefix('vendor')->group(function () {
        Route::get('/products', [VendorProductController::class, 'index']);
        Route::post('/products', [VendorProductController::class, 'store']);
        Route::put('/products/{id}', [VendorProductController::class, 'update']);
        Route::delete('/products/{id}', [VendorProductController::class, 'destroy']);
        Route::get('/wallet', [VendorProductController::class, 'wallet']);
    });

    // 5. Admin Management & Escrow Releases
    Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
        Route::patch('/orders/{id}/deliver', [AdminController::class, 'deliverOrder']);
        Route::patch('/vendors/{id}/verify', [AdminController::class, 'verifyVendor']);
    });

    // 6. Payment Webhook Ingestion
    Route::post('/webhooks/payment', [WebhookController::class, 'handle']);
});
