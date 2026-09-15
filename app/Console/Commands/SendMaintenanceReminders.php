<?php

namespace App\Console\Commands;

use App\Services\MaintenanceNotifier;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Reminds technicians of work orders that are overdue or due today. Safe to run
 * repeatedly: a user is reminded at most once a day.
 */
class SendMaintenanceReminders extends Command
{
    protected $signature = 'maintenance:send-reminders';

    protected $description = 'Remind technicians of work orders that are overdue or due today';

    public function handle(MaintenanceNotifier $notifier): int
    {
        $reminded = $notifier->sendDailyReminders(CarbonImmutable::today());

        $this->components->info("{$reminded} user(s) reminded.");

        return self::SUCCESS;
    }
}
