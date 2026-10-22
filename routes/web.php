<?php

use App\Http\Controllers\WebAuthController;
use App\Http\Controllers\WebDashboardController;
use App\Http\Controllers\ClassroomController;
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
    Route::get('/classroom/connect', [ClassroomController::class, 'connect'])->name('classroom.connect');
    Route::get('/classroom/callback', [ClassroomController::class, 'callback'])->name('classroom.callback');
    Route::post('/classroom/sync', [ClassroomController::class, 'sync'])->name('classroom.sync');
    Route::delete('/classroom/disconnect', [ClassroomController::class, 'disconnect'])->name('classroom.disconnect');
    Route::post('/classroom/imports/{import}/resolve', [ClassroomController::class, 'resolveImport'])->name('classroom.imports.resolve');
    Route::post('/classroom/imports/{import}/ignore', [ClassroomController::class, 'ignoreImport'])->name('classroom.imports.ignore');
    Route::post('/logout', [WebAuthController::class, 'logout'])->name('logout');
});
