<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('laraclaw:run-scheduled-tasks')->everyMinute();
Schedule::command('laraclaw:dispatch-notifications')->everyMinute();
Schedule::command('laraclaw:heartbeat:run')->everyFiveMinutes();
