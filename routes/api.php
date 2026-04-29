<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BusController;
use App\Http\Controllers\Api\DriverController;
use App\Http\Controllers\Api\GuardianController;
use App\Http\Controllers\Api\SchoolController;
use App\Http\Controllers\Api\StudentController;
use App\Http\Controllers\Api\TripHistoryController;
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

    Route::prefix('students')->group(function (): void {
        Route::get('/', [StudentController::class, 'index']);
        Route::post('/', [StudentController::class, 'store']);
        Route::get('{student}', [StudentController::class, 'show']);
        Route::put('{student}', [StudentController::class, 'update']);
        Route::delete('{student}', [StudentController::class, 'destroy']);
    });

    Route::prefix('guardians')->group(function (): void {
        Route::get('/', [GuardianController::class, 'index']);
        Route::post('/', [GuardianController::class, 'store']);
        Route::get('{guardian}', [GuardianController::class, 'show']);
        Route::put('{guardian}', [GuardianController::class, 'update']);
        Route::delete('{guardian}', [GuardianController::class, 'destroy']);
    });

    Route::prefix('trips')->group(function (): void {
        Route::get('history', [TripHistoryController::class, 'history']);
        Route::get('/', [TripHistoryController::class, 'index']);
        Route::post('/', [TripHistoryController::class, 'store']);
        Route::get('{trip}', [TripHistoryController::class, 'show']);
        Route::put('{trip}', [TripHistoryController::class, 'update']);
        Route::delete('{trip}', [TripHistoryController::class, 'destroy']);
    });
});
