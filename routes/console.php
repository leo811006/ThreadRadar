<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('app:dispatch-due-crawls')
    ->everyMinute()
    ->withoutOverlapping();

Schedule::command('app:aggregate-daily-statistics')
    ->dailyAt('00:10')
    ->withoutOverlapping();
