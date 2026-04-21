<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusController;
use App\Http\Controllers\Api\UserProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('send-otp', [AuthController::class, 'sendOtp'])
        ->middleware('throttle:otp-send');

    Route::post('verify-otp', [AuthController::class, 'verifyOtp'])
        ->middleware('throttle:otp-verify');
});

Route::middleware('auth:sanctum')->group(function (): void {
    Route::prefix('auth')->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::get('me', [AuthController::class, 'me']);
    });

    Route::prefix('user')->group(function (): void {
        Route::get('profile', [UserProfileController::class, 'show']);
        Route::put('profile', [UserProfileController::class, 'update']);
        Route::delete('profile', [UserProfileController::class, 'destroy']);
        Route::post('language', [UserProfileController::class, 'changeLanguage']);

        Route::get('bus', [BusController::class, 'showMyBus']);
    });

    Route::prefix('bus')->group(function (): void {
        Route::get('my-bus', [BusController::class, 'showMyBus']);
        Route::post('my-bus', [BusController::class, 'store']);
        Route::put('my-bus', [BusController::class, 'update']);
        Route::delete('my-bus', [BusController::class, 'destroy']);
    });
});
