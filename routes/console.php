<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Scheduler
|--------------------------------------------------------------------------
|
| One cron entry runs `php artisan schedule:run` every minute (the
| `scheduler` container in Docker). Every task is idempotent, so a missed
| or repeated run is harmless, and onOneServer() keeps a multi-node deploy
| from running it twice (lock in the shared cache).
|
*/

// Hourly rather than daily: each company closes its day in its own
// timezone, and re-running on already-overdue documents changes nothing.
Schedule::command('invoices:mark-overdue')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

// Expired API tokens (a day of grace so audit/log correlation still works).
Schedule::command('sanctum:prune-expired --hours=24')->daily()->onOneServer();

// Prunable models: finished webhook events past retention, old report
// exports and their files.
Schedule::command('model:prune')->dailyAt('02:00')->onOneServer();

Schedule::command('queue:prune-failed --hours=720')->daily()->onOneServer();
Schedule::command('queue:prune-batches --hours=48')->daily()->onOneServer();
Schedule::command('auth:clear-resets')->everyFifteenMinutes()->onOneServer();
