<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\SchoolController;
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
        Route::get('driver', [DriverController::class, 'myDriver']);
    });

    Route::prefix('bus')->group(function (): void {
        Route::get('my-bus', [BusController::class, 'showMyBus']);
        Route::post('my-bus', [BusController::class, 'store']);
        Route::put('my-bus', [BusController::class, 'update']);
        Route::delete('my-bus', [BusController::class, 'destroy']);
    });

    Route::prefix('schools')->group(function (): void {
        Route::get('/', [SchoolController::class, 'index']);
        Route::post('/', [SchoolController::class, 'store']);
        Route::get('{school}', [SchoolController::class, 'show']);
        Route::put('{school}', [SchoolController::class, 'update']);
        Route::delete('{school}', [SchoolController::class, 'destroy']);
    });

    Route::prefix('drivers')->group(function (): void {
        Route::get('/', [DriverController::class, 'index']);
        Route::post('/', [DriverController::class, 'store']);
        Route::get('{driver}', [DriverController::class, 'show']);
        Route::put('{driver}', [DriverController::class, 'update']);
        Route::delete('{driver}', [DriverController::class, 'destroy']);
    });
});
