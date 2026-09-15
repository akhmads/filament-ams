<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Refresh this month's depreciation drafts on its last evening so they are ready
// to review and post. Posting stays a deliberate step in the panel.
Schedule::command('depreciation:calculate')
    ->lastDayOfMonth('22:00')
    ->withoutOverlapping()
    ->onOneServer();

// Open preventive maintenance work orders before the working day starts.
Schedule::command('maintenance:generate-work-orders')
    ->dailyAt('06:00')
    ->withoutOverlapping()
    ->onOneServer();

// Remind technicians of overdue work and work due today, after the day's work
// orders have been opened.
Schedule::command('maintenance:send-reminders')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->onOneServer();
