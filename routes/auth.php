<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\ConfirmablePasswordController;
use App\Http\Controllers\Auth\ClaimAccountController;
use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\Auth\GoogleOAuthController;
use App\Http\Controllers\Auth\CustomerPasswordResetLinkController;
use App\Http\Controllers\Auth\CustomerNewPasswordController;
use App\Http\Controllers\Auth\EmailVerificationNotificationController;
use App\Http\Controllers\Auth\EmailVerificationPromptController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\Auth\PasswordResetLinkController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Auth\VerifyEmailController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest:customer')->group(function () {
    Route::get('register', [RegisteredUserController::class, 'create'])
        ->name('register');

    Route::post('register', [RegisteredUserController::class, 'store']);

    Route::get('login', [AuthenticatedSessionController::class, 'create'])
        ->name('login');

    Route::post('login', [AuthenticatedSessionController::class, 'store']);

    Route::get('auth/{provider}', [SocialAuthController::class, 'redirect'])
        ->name('social.redirect');

    Route::get('auth/{provider}/callback', [SocialAuthController::class, 'callback'])
        ->name('social.callback');

    Route::get('claim-account', [ClaimAccountController::class, 'create'])
        ->name('claim-account.create');

    Route::post('claim-account', [ClaimAccountController::class, 'store'])
        ->name('claim-account.store');

    Route::get('forgot-password', [CustomerPasswordResetLinkController::class, 'create'])
        ->name('password.request');

    Route::post('forgot-password', [CustomerPasswordResetLinkController::class, 'store'])
        ->name('password.email');

    Route::get('reset-password/{token}', [CustomerNewPasswordController::class, 'create'])
        ->name('password.reset');

    Route::post('reset-password', [CustomerNewPasswordController::class, 'store'])
        ->name('password.store');
});

// Google OAuth routes
Route::get('/auth/google/redirect', [GoogleOAuthController::class, 'redirect'])
    ->name('auth.google.redirect');

Route::get('/auth/google/callback', [GoogleOAuthController::class, 'callback'])
    ->name('auth.google.callback');

Route::middleware('auth:customer')->group(function () {
    Route::get('verify-email', EmailVerificationPromptController::class)
        ->name('verification.notice');

    Route::get('verify-email/{id}/{hash}', VerifyEmailController::class)
        ->middleware(['signed', 'throttle:6,1'])
        ->name('verification.verify');

    Route::post('email/verification-notification', [EmailVerificationNotificationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('verification.send');

    Route::get('confirm-password', [ConfirmablePasswordController::class, 'show'])
        ->name('password.confirm');

    Route::post('confirm-password', [ConfirmablePasswordController::class, 'store']);

    Route::put('password', [PasswordController::class, 'update'])->name('password.update');

    Route::post('logout', [AuthenticatedSessionController::class, 'destroy'])
        ->name('logout');

    // Google OAuth authenticated routes
    Route::get('/auth/google/refresh', [GoogleOAuthController::class, 'refresh'])
        ->name('auth.google.refresh');

    Route::get('/api/calendar/events', [GoogleOAuthController::class, 'getCalendarEvents'])
        ->name('api.calendar.events');

    Route::post('/auth/google/logout', [GoogleOAuthController::class, 'logout'])
        ->name('auth.google.logout');
});
