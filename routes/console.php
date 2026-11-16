<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('platform:status', function () {
    $this->info('VTU SaaS foundation is installed. This command does not indicate production readiness.');
})->purpose('Display foundation status');

Schedule::command('vtu:dispatch-reserved --limit=100')->everyMinute()->withoutOverlapping();
Schedule::command('vtu:requery-pending --limit=100')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('payments:requery-pending --limit=100')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('webhooks:retry-due --limit=100')->everyMinute()->withoutOverlapping();
