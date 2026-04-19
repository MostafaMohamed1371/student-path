<?php

use App\Http\Controllers\Web\DashboardHomeController;
use App\Http\Controllers\Web\DashboardLoginController;
use App\Http\Controllers\Web\DashboardUserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::get('/locale/{locale}', function (string $locale) {
    abort_unless(in_array($locale, ['en', 'ar'], true), 400);
    session(['locale' => $locale]);

    return redirect()->back();
})->name('locale.switch');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [DashboardLoginController::class, 'show'])->name('login');
    Route::post('/login', [DashboardLoginController::class, 'authenticate']);
});

Route::post('/logout', [DashboardLoginController::class, 'destroy'])->middleware('auth')->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [DashboardHomeController::class, 'index'])->name('dashboard');
    Route::get('/dashboard/users', [DashboardUserController::class, 'index'])->name('dashboard.users.index');
    Route::get('/dashboard/users/create', [DashboardUserController::class, 'create'])->name('dashboard.users.create');
    Route::post('/dashboard/users', [DashboardUserController::class, 'store'])->name('dashboard.users.store');
    Route::get('/dashboard/users/{user}/edit', [DashboardUserController::class, 'edit'])->name('dashboard.users.edit');
    Route::put('/dashboard/users/{user}', [DashboardUserController::class, 'update'])->name('dashboard.users.update');
    Route::delete('/dashboard/users/{user}', [DashboardUserController::class, 'destroy'])->name('dashboard.users.destroy');
});
