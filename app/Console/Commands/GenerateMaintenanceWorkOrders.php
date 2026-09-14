<?php

namespace App\Console\Commands;

use App\Services\MaintenanceScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Opens the work orders that active maintenance plans have due within their lead
 * time. Safe to run repeatedly: an asset never gets a second open work order for
 * the same plan.
 */
class GenerateMaintenanceWorkOrders extends Command
{
    protected $signature = 'maintenance:generate-work-orders
        {--date= : Treat this day (YYYY-MM-DD) as today; the actual date when omitted}';

    protected $description = 'Open work orders for maintenance plans that fall due within their lead time';

    public function handle(MaintenanceScheduler $scheduler): int
    {
        $today = $this->today();

        if ($today === null) {
            return self::INVALID;
        }

        $opened = $scheduler->generate($today);

        $this->components->info("{$opened} work order(s) opened for {$today->format('d M Y')}.");

        return self::SUCCESS;
    }

    private function today(): ?CarbonImmutable
    {
        $requested = $this->option('date');

        if ($requested === null) {
            return CarbonImmutable::today();
        }

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $requested) || ! checkdate((int) substr($requested, 5, 2), (int) substr($requested, 8, 2), (int) substr($requested, 0, 4))) {
            $this->components->error("Date [{$requested}] must be a real date like 2026-09-14.");

            return null;
        }

        return CarbonImmutable::createFromFormat('!Y-m-d', $requested);
    }
}
