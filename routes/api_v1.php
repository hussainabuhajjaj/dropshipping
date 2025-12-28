<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Auth\TokenController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\OrderController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\SupplierController;

/*
|--------------------------------------------------------------------------
| API V1 Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for version 1 of your API.
| These routes are loaded by the RouteServiceProvider within a group
| which is assigned the "api" middleware group and versioned as "v1".
|
*/

// Public routes
Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register'])->name('auth.register');
    Route::post('login', [AuthController::class, 'login'])->name('auth.login');
    
    // For SPA - get CSRF cookie
    Route::get('csrf-cookie', function () {
        return response()->json(['message' => 'CSRF cookie set']);
    })->name('auth.csrf');
});

// Protected routes - require authentication
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::prefix('auth')->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('user', [AuthController::class, 'user'])->name('auth.user');
        Route::put('profile', [AuthController::class, 'updateProfile'])->name('auth.profile.update');
        Route::put('password', [AuthController::class, 'updatePassword'])->name('auth.password.update');
    });

    // Token management routes (for mobile/external clients)
    Route::prefix('tokens')->group(function () {
        Route::get('/', [TokenController::class, 'index'])->name('tokens.index');
        Route::post('/', [TokenController::class, 'create'])->name('tokens.create');
        Route::delete('{id}', [TokenController::class, 'revoke'])->name('tokens.revoke');
        Route::post('revoke-all', [TokenController::class, 'revokeAll'])->name('tokens.revoke-all');
    });

    // Resource routes will be added here
    // Example: Products, Categories, Orders, etc.
    
    // Products API - with rate limiting
    Route::apiResource('products', ProductController::class)
        ->middleware('throttle:authenticated');

    // Categories API
    Route::get('categories/tree', [CategoryController::class, 'tree'])->name('categories.tree');
    Route::get('categories/{category}/children', [CategoryController::class, 'children'])->name('categories.children');
    Route::apiResource('categories', CategoryController::class)
        ->middleware('throttle:authenticated');

    // Orders API
    Route::get('orders/statistics', [OrderController::class, 'statistics'])->name('orders.statistics');
    Route::post('orders/{order}/status', [OrderController::class, 'updateStatus'])->name('orders.status.update');
    Route::post('orders/{order}/payment-status', [OrderController::class, 'updatePaymentStatus'])->name('orders.payment-status.update');
    Route::apiResource('orders', OrderController::class)
        ->middleware('throttle:authenticated');

    // Customers API
    Route::get('customers/statistics', [CustomerController::class, 'statistics'])->name('customers.statistics');
    Route::get('customers/top', [CustomerController::class, 'topCustomers'])->name('customers.top');
    Route::apiResource('customers', CustomerController::class)
        ->middleware('throttle:authenticated');

    // Suppliers API
    Route::get('suppliers/statistics', [SupplierController::class, 'statistics'])->name('suppliers.statistics');
    Route::get('suppliers/top', [SupplierController::class, 'topSuppliers'])->name('suppliers.top');
    Route::apiResource('suppliers', SupplierController::class)
        ->middleware('throttle:authenticated');
});
