<?php

namespace App\Providers;

use App\Models\Activity;
use App\Observers\ActivityObserver;
use Illuminate\Support\ServiceProvider;

// Importar las clases necesarias para la política
use Illuminate\Support\Facades\Gate;

use App\Models\AcademicSemester;
use App\Policies\SemesterPolicy;

use App\Models\Course;
use App\Policies\CoursePolicy;

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
        Activity::observe(ActivityObserver::class);

        // Registrar la política para el modelo AcademicSemester
        Gate::policy(AcademicSemester::class, SemesterPolicy::class);
        Gate::policy(Course::class, CoursePolicy::class);
    }
}