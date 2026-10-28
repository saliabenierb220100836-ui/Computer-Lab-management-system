<?php

use App\Models\ActivitySession;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('lab:sessions:expire', function () {
    $count = ActivitySession::query()
        ->whereNull('logout_time')
        ->whereHas('schedule', fn ($query) => $query->where('ends_at', '<=', now()))
        ->update(['logout_time' => now()]);

    $this->info("Closed {$count} expired lab session(s).");
})->purpose('Automatically check out sessions when their class ends');

Schedule::command('lab:sessions:expire')->everyMinute();
