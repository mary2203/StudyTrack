<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Schedule::command('activities:mark-overdue')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('analytics:refresh')->hourly()->withoutOverlapping();
Schedule::command('classroom:sync')->everyFifteenMinutes()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');
