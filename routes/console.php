<?php

use App\Services\KrooPlusReferralQualification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::call(fn () => app(KrooPlusReferralQualification::class)->qualifyEligibleMemberships())
    ->hourly()
    ->name('qualify-kroo-plus-referrals')
    ->withoutOverlapping();
