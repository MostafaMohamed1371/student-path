<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('send-otp', [AuthController::class, 'sendOtp'])
        ->middleware('throttle:otp-send');

    Route::post('verify-otp', [AuthController::class, 'verifyOtp'])
        ->middleware('throttle:otp-verify');
});

Route::middleware('auth:sanctum')->prefix('auth')->group(function (): void {
    Route::post('logout', [AuthController::class, 'logout']);
    Route::get('me', [AuthController::class, 'me']);
});
