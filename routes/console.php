<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

// Automated Canonical Pipeline: 500 words/day (RPD) at 2:00 AM,
// one word per request with 4.5s delay (15 RPM). Resumes automatically
// from the next untranslated word via translated_at (Gen.1.1 → Rev).
Schedule::command('bible:translate-lexicon --canonical --limit=500 --delay=4.5')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->runInBackground()
    ->appendOutputTo(storage_path('logs/translate-lexicon.log'));
