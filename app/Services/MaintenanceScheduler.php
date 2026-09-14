<?php

namespace App\Services;

use App\Enums\WorkOrderStatus;
use App\Models\Asset;
use App\Models\MaintenancePlan;
use App\Models\WorkOrder;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Opens work orders for maintenance plans as they fall due.
 *
 * Due dates follow the calendar, not the completion date: the next one is the
 * previous due date plus the interval, so a late service does not push the whole
 * schedule back. An asset has at most one open work order per plan, and missed
 * occurrences collapse into the most recent one instead of piling up.
 */
class MaintenanceScheduler
{
    public function __construct(
        private readonly WorkOrderService $workOrders,
    ) {}

    /**
     * @return int The number of work orders opened.
     */
    public function generate(CarbonImmutable $today): int
    {
        $opened = 0;

        MaintenancePlan::query()
            ->where('is_active', true)
            ->chunkById(100, function (Collection $plans) use ($today, &$opened): void {
                foreach ($plans as $plan) {
                    $opened += $this->generateForPlan($plan, $today);
                }
            });

        return $opened;
    }

    /**
     * @return int The number of work orders opened.
     */
    public function generateForPlan(MaintenancePlan $plan, CarbonImmutable $today): int
    {
        if (! $plan->is_active) {
            return 0;
        }

        $horizon = $today->addDays($plan->lead_days);

        $assetsWithOpenWork = WorkOrder::query()
            ->where('maintenance_plan_id', $plan->id)
            ->whereIn('status', WorkOrderStatus::openValues())
            ->pluck('asset_id')
            ->flip();

        // Deleted work orders still count, so deleting one never reopens the same date.
        $lastDueDates = WorkOrder::withTrashed()
            ->where('maintenance_plan_id', $plan->id)
            ->groupBy('asset_id')
            ->selectRaw('asset_id, max(due_date) as last_due_date')
            ->pluck('last_due_date', 'asset_id');

        $opened = 0;

        $plan->targetAssets()->chunkById(200, function (Collection $assets) use ($plan, $today, $horizon, $assetsWithOpenWork, $lastDueDates, &$opened): void {
            foreach ($assets as $asset) {
                if ($assetsWithOpenWork->has($asset->id)) {
                    continue;
                }

                $lastDueDate = $lastDueDates->get($asset->id);
                $dueDate = $this->nextDueDate($plan, $asset, $lastDueDate === null ? null : CarbonImmutable::parse($lastDueDate), $today);

                if ($dueDate->gt($horizon)) {
                    continue;
                }

                try {
                    $this->workOrders->openFromPlan($plan, $asset, $dueDate);
                } catch (UniqueConstraintViolationException) {
                    // Another run opened it first.
                    continue;
                }

                $opened++;
            }
        });

        return $opened;
    }

    /**
     * The first due date is the plan's start date, or one interval after
     * acquisition for an asset that arrived later. After that each due date is the
     * previous one plus the interval, skipping ahead past occurrences that have
     * already gone by.
     */
    public function nextDueDate(MaintenancePlan $plan, Asset $asset, ?CarbonImmutable $lastDueDate, CarbonImmutable $today): CarbonImmutable
    {
        $unit = $plan->interval_unit;
        $interval = $plan->interval_value;

        if ($lastDueDate !== null) {
            $dueDate = $unit->addTo($lastDueDate->startOfDay(), $interval);
        } else {
            $startDate = CarbonImmutable::parse($plan->start_date)->startOfDay();
            $acquiredOn = CarbonImmutable::parse($asset->acquisition_date)->startOfDay();

            $dueDate = $acquiredOn->gt($startDate) ? $unit->addTo($acquiredOn, $interval) : $startDate;
        }

        while (($following = $unit->addTo($dueDate, $interval))->lte($today)) {
            $dueDate = $following;
        }

        return $dueDate;
    }
}
