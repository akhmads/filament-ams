<?php

namespace App\Services;

use App\Enums\CalendarEntryKind;
use App\Enums\RepairTicketStatus;
use App\Enums\WorkOrderStatus;
use App\Filament\Resources\MaintenancePlans\MaintenancePlanResource;
use App\Filament\Resources\RepairTickets\RepairTicketResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\MaintenancePlan;
use App\Models\RepairTicket;
use App\Models\WorkOrder;
use App\Support\CalendarEntry;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * One month of the maintenance calendar: work orders on their due date, repair
 * tickets on the day they were reported, and planned maintenance — the dates a
 * plan falls due that have no work order yet.
 */
class MaintenanceCalendarFeed
{
    /**
     * Planned dates are projected this far ahead at most, so paging years into the
     * future does not walk every daily plan across every asset.
     */
    private const PROJECTION_MONTHS = 12;

    public function __construct(
        private readonly MaintenanceScheduler $scheduler,
    ) {}

    /**
     * @param  array<int, CalendarEntryKind>  $kinds  The kinds of entry to include.
     * @return array<string, list<CalendarEntry>> Entries keyed by date (Y-m-d), in date order.
     */
    public function forMonth(CarbonImmutable $month, CarbonImmutable $today, array $kinds): array
    {
        $start = $month->startOfMonth();
        $end = $start->addMonth();

        $entries = [
            ...in_array(CalendarEntryKind::WorkOrder, $kinds, strict: true) ? $this->workOrders($start, $end, $today) : [],
            ...in_array(CalendarEntryKind::Repair, $kinds, strict: true) ? $this->repairTickets($start, $end) : [],
            ...in_array(CalendarEntryKind::Planned, $kinds, strict: true) ? $this->plannedMaintenance($start, $end, $today) : [],
        ];

        $byDate = [];

        foreach ($entries as $entry) {
            $byDate[$entry->date->toDateString()][] = $entry;
        }

        ksort($byDate);

        return $byDate;
    }

    /**
     * Cancelled work orders are left out: nothing will happen on that day.
     *
     * @return list<CalendarEntry>
     */
    private function workOrders(CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $today): array
    {
        return WorkOrder::query()
            ->where('due_date', '>=', $start->toDateString())
            ->where('due_date', '<', $end->toDateString())
            ->where('status', '!=', WorkOrderStatus::Cancelled->value)
            ->with('asset:id,code')
            ->orderBy('due_date')
            ->orderBy('number')
            ->get()
            ->map(fn (WorkOrder $workOrder): CalendarEntry => new CalendarEntry(
                kind: CalendarEntryKind::WorkOrder,
                date: CarbonImmutable::parse($workOrder->due_date),
                title: $workOrder->title,
                detail: collect([$workOrder->number, $workOrder->asset?->code])->filter()->join(' · '),
                color: $this->isOverdue($workOrder, $today) ? 'danger' : $workOrder->status->getColor(),
                statusLabel: $this->isOverdue($workOrder, $today) ? 'Overdue' : $workOrder->status->getLabel(),
                url: WorkOrderResource::getUrl('view', ['record' => $workOrder]),
            ))
            ->values()
            ->all();
    }

    /**
     * @return list<CalendarEntry>
     */
    private function repairTickets(CarbonImmutable $start, CarbonImmutable $end): array
    {
        return RepairTicket::query()
            ->where('reported_at', '>=', $start)
            ->where('reported_at', '<', $end)
            ->where('status', '!=', RepairTicketStatus::Rejected->value)
            ->with('asset:id,code')
            ->orderBy('reported_at')
            ->orderBy('number')
            ->get()
            ->map(fn (RepairTicket $ticket): CalendarEntry => new CalendarEntry(
                kind: CalendarEntryKind::Repair,
                date: CarbonImmutable::parse($ticket->reported_at)->startOfDay(),
                title: $ticket->title,
                detail: collect([$ticket->number, $ticket->asset?->code])->filter()->join(' · '),
                color: $ticket->status->getColor(),
                statusLabel: $ticket->status->getLabel(),
                url: RepairTicketResource::getUrl('view', ['record' => $ticket]),
            ))
            ->values()
            ->all();
    }

    /**
     * Follows the scheduler's own rule for the next due date, then steps through the
     * plan's interval. Only dates after today and after the asset's latest work
     * order are planned; everything earlier is either a work order already or
     * about to become one.
     *
     * @return list<CalendarEntry>
     */
    private function plannedMaintenance(CarbonImmutable $start, CarbonImmutable $end, CarbonImmutable $today): array
    {
        if ($end->lte($today->addDay()) || $start->gt($today->addMonths(self::PROJECTION_MONTHS))) {
            return [];
        }

        /** @var array<string, array{plan: MaintenancePlan, date: CarbonImmutable, assets: list<string>}> $occurrences */
        $occurrences = [];

        MaintenancePlan::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->each(function (MaintenancePlan $plan) use ($start, $end, $today, &$occurrences): void {
                $lastDueDates = WorkOrder::withTrashed()
                    ->where('maintenance_plan_id', $plan->id)
                    ->groupBy('asset_id')
                    ->selectRaw('asset_id, max(due_date) as last_due_date')
                    ->pluck('last_due_date', 'asset_id');

                $plan->targetAssets()
                    ->select(['id', 'code', 'acquisition_date'])
                    ->chunkById(200, function (Collection $assets) use ($plan, $start, $end, $today, $lastDueDates, &$occurrences): void {
                        foreach ($assets as $asset) {
                            $lastDueDate = $lastDueDates->get($asset->id);
                            $lastDueDate = $lastDueDate === null ? null : CarbonImmutable::parse($lastDueDate)->startOfDay();

                            $date = $this->scheduler->nextDueDate($plan, $asset, $lastDueDate, $today);

                            for (; $date->lt($end); $date = $plan->interval_unit->addTo($date, $plan->interval_value)) {
                                if ($date->lte($today) || $date->lt($start) || ($lastDueDate !== null && $date->lte($lastDueDate))) {
                                    continue;
                                }

                                $key = $plan->id.'|'.$date->toDateString();
                                $occurrences[$key] ??= ['plan' => $plan, 'date' => $date, 'assets' => []];
                                $occurrences[$key]['assets'][] = $asset->code;
                            }
                        }
                    });
            });

        return collect($occurrences)
            ->sortBy(fn (array $occurrence): string => $occurrence['date']->toDateString())
            ->map(fn (array $occurrence): CalendarEntry => new CalendarEntry(
                kind: CalendarEntryKind::Planned,
                date: $occurrence['date'],
                title: $occurrence['plan']->name,
                detail: count($occurrence['assets']) === 1 ? $occurrence['assets'][0] : count($occurrence['assets']).' assets',
                color: 'gray',
                statusLabel: 'Planned',
                url: MaintenancePlanResource::getUrl('edit', ['record' => $occurrence['plan']]),
            ))
            ->values()
            ->all();
    }

    private function isOverdue(WorkOrder $workOrder, CarbonImmutable $today): bool
    {
        return $workOrder->status->isOpen() && $workOrder->due_date->lt($today);
    }
}
