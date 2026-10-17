<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\CalendarController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API de StudyTrack  (todas las rutas llevan el prefijo /api)
|--------------------------------------------------------------------------
| Formato de respuestas:
|   éxito  -> { "data": ..., "message": "opcional" }   (usar API Resources)
|   error  -> { "message": "...", "errors": { ... } | null }
|
| Cada módulo descomenta SOLO su bloque cuando su controlador exista
| (una ruta que apunta a un controlador inexistente rompe route:list).
*/

// Ruta pública de prueba
Route::get('/ping', fn() => response()->json([
    'data' => ['status' => 'ok', 'time' => now()->toIso8601String()],
]));

/*
|--------------------------------------------------------------------------
| Rutas públicas de autenticación  (MÓDULO 1: auth, semestres y cursos)
|--------------------------------------------------------------------------
*/
// Route::post('/register', [AuthController::class, 'register']);
// Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');

/*
|--------------------------------------------------------------------------
| Rutas protegidas (requieren token Sanctum: Authorization: Bearer <token>)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // Ruta protegida de prueba
    Route::get('/me', fn(Request $request) => response()->json([
        'data' => $request->user()->only(['id', 'name', 'email']),
    ]));

    // ---- MÓDULO 1: auth, semestres y cursos --------------------------------
    // Route::post('/logout', [AuthController::class, 'logout']);
    // Route::get('/semesters/current', [SemesterController::class, 'current']);
    // Route::apiResource('semesters', SemesterController::class);
    // Route::apiResource('courses', CourseController::class);

    // ---- MÓDULO 2: actividades y calendario --------------------------------
    Route::apiResource('activities', ActivityController::class);
    Route::patch('/activities/{activity}/grade', [ActivityController::class, 'grade']);
    Route::patch('/activities/{activity}/complete', [ActivityController::class, 'complete']);
    Route::get('/calendar', [CalendarController::class, 'index']);

    // ---- MÓDULO 3: dashboard, recomendaciones y analítica ------------------
    // Route::get('/dashboard', [DashboardController::class, 'index']);
    // Route::get('/recommendations', [RecommendationController::class, 'index']);
    // Route::patch('/recommendations/{recommendation}/read', [RecommendationController::class, 'markAsRead']);
    // Route::get('/analytics/{level}', [AnalyticsController::class, 'show'])
    //     ->whereIn('level', ['descriptive', 'diagnostic', 'predictive', 'prescriptive']);
});
