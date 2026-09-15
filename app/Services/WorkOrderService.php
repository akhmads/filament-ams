<?php

namespace App\Services;

use App\Enums\WorkOrderResult;
use App\Enums\WorkOrderStatus;
use App\Exceptions\WorkOrderException;
use App\Models\Asset;
use App\Models\MaintenancePlan;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChecklistItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Opens work orders and moves them through open → in progress → completed, or
 * cancelled. The asset's status is left alone: routine maintenance does not take
 * an asset out of use.
 */
class WorkOrderService
{
    public function __construct(
        private readonly DocumentNumberGenerator $numbers,
        private readonly MaintenanceNotifier $notifier,
    ) {}

    /**
     * A work order number shaped WO/YYMM/SEQ.
     */
    public function nextNumber(): string
    {
        return $this->numbers->document('work_order', 'WO');
    }

    /**
     * Opens a work order for a plan's asset, copying the plan's assignee,
     * estimates and checklist so later plan edits do not rewrite past work.
     */
    public function openFromPlan(MaintenancePlan $plan, Asset $asset, CarbonImmutable $dueDate): WorkOrder
    {
        return DB::transaction(function () use ($plan, $asset, $dueDate): WorkOrder {
            $workOrder = WorkOrder::create([
                'number' => $this->nextNumber(),
                'maintenance_plan_id' => $plan->id,
                'asset_id' => $asset->id,
                'title' => $plan->name,
                'due_date' => $dueDate->toDateString(),
                'assigned_to' => $plan->assigned_to,
                'supplier_id' => $plan->supplier_id,
                'estimated_cost' => $plan->estimated_cost,
                'estimated_minutes' => $plan->estimated_minutes,
                'instructions' => $plan->notes,
            ]);

            $workOrder->checklistItems()->createMany(
                collect($plan->checklist ?? [])
                    ->filter(fn (mixed $task): bool => is_string($task) && filled($task))
                    ->values()
                    ->map(fn (string $task, int $index): array => ['task' => $task, 'sort_order' => $index + 1])
                    ->all()
            );

            $this->notifier->workOrderAssigned($workOrder);

            return $workOrder;
        });
    }

    public function start(WorkOrder $workOrder): WorkOrder
    {
        if ($workOrder->status !== WorkOrderStatus::Open) {
            throw WorkOrderException::notInStatus($workOrder, 'started', WorkOrderStatus::Open);
        }

        $workOrder->forceFill([
            'status' => WorkOrderStatus::InProgress,
            'started_at' => now(),
        ])->save();

        return $workOrder;
    }

    public function markChecklistItem(WorkOrderChecklistItem $item, bool $isDone, User $user): WorkOrderChecklistItem
    {
        $workOrder = $item->loadMissing('workOrder')->workOrder;

        if ($workOrder->status !== WorkOrderStatus::InProgress) {
            throw WorkOrderException::notInStatus($workOrder, 'ticked off', WorkOrderStatus::InProgress);
        }

        $item->forceFill([
            'is_done' => $isDone,
            'done_at' => $isDone ? now() : null,
            'done_by' => $isDone ? $user->id : null,
        ])->save();

        return $item;
    }

    /**
     * An OK result needs every checklist item ticked. Work that could not be
     * finished is completed as Needs Follow-up, so the gap stays on record.
     */
    public function complete(
        WorkOrder $workOrder,
        WorkOrderResult $result,
        User $user,
        ?string $actualCost = null,
        ?int $laborMinutes = null,
        ?string $findings = null,
    ): WorkOrder {
        if ($workOrder->status !== WorkOrderStatus::InProgress) {
            throw WorkOrderException::notInStatus($workOrder, 'completed', WorkOrderStatus::InProgress);
        }

        if ($result === WorkOrderResult::Ok) {
            $remaining = $workOrder->checklistItems()->where('is_done', false)->count();

            if ($remaining > 0) {
                throw WorkOrderException::unfinishedChecklist($workOrder, $remaining);
            }
        }

        $workOrder->forceFill([
            'status' => WorkOrderStatus::Completed,
            'result' => $result,
            'completed_at' => now(),
            'completed_by' => $user->id,
            'actual_cost' => $actualCost,
            'labor_minutes' => $laborMinutes,
            'findings' => $findings,
        ])->save();

        return $workOrder;
    }

    public function cancel(WorkOrder $workOrder, string $reason): WorkOrder
    {
        if (! $workOrder->status->isOpen()) {
            throw WorkOrderException::notInStatus($workOrder, 'cancelled', WorkOrderStatus::Open, WorkOrderStatus::InProgress);
        }

        $workOrder->forceFill([
            'status' => WorkOrderStatus::Cancelled,
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ])->save();

        return $workOrder;
    }
}
