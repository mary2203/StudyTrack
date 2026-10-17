<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

// Importar las clases necesarias para la política
use Illuminate\Support\Facades\Gate;

use App\Models\AcademicSemester;
use App\Policies\SemesterPolicy;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registrar la política para el modelo AcademicSemester
        Gate::policy(AcademicSemester::class, SemesterPolicy::class);
    }
}
