<?php

use App\Jobs\Central\CheckPendingPaymentsJob;
use App\Jobs\Central\SendBoletoRemindersJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled Tasks
|--------------------------------------------------------------------------
*/

// Check pending PIX/Boleto payments every 5 minutes (fallback for webhooks)
Schedule::job(new CheckPendingPaymentsJob)
    ->everyFiveMinutes()
    ->withoutOverlapping()
    ->onOneServer();

// Send boleto payment reminders daily at 9am
Schedule::job(new SendBoletoRemindersJob)
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->onOneServer();
