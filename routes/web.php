<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\SemesterController;
use App\Http\Controllers\WebAuthController;
use App\Http\Controllers\WebDashboardController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));
Route::middleware('guest')->group(function () {
    Route::get('/login', [WebAuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [WebAuthController::class, 'login'])
        ->middleware('throttle:6,1')
        ->name('login.store');
    Route::get('/register', [WebAuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [WebAuthController::class, 'register'])->name('register.store');
});

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [WebDashboardController::class, 'index'])->name('dashboard');
    Route::prefix('dashboard-data')->name('dashboard-data.')->group(function () {
        Route::apiResource('semesters', SemesterController::class);
        Route::apiResource('courses', CourseController::class);
        Route::apiResource('activities', ActivityController::class);
        Route::patch('/activities/{activity}/grade', [ActivityController::class, 'grade'])
            ->name('activities.grade');
        Route::patch('/activities/{activity}/complete', [ActivityController::class, 'complete'])
            ->name('activities.complete');
    });
    Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');
});
