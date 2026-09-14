<?php

namespace App\Exceptions;

use App\Enums\WorkOrderStatus;
use App\Models\WorkOrder;
use Exception;

class WorkOrderException extends Exception
{
    public static function notInStatus(WorkOrder $workOrder, string $action, WorkOrderStatus ...$allowed): self
    {
        $allowedLabels = collect($allowed)->map(fn (WorkOrderStatus $status): string => $status->getLabel())->join(' or ');

        return new self("Work order {$workOrder->number} is {$workOrder->status->getLabel()}; only {$allowedLabels} work orders can be {$action}.");
    }

    public static function unfinishedChecklist(WorkOrder $workOrder, int $remaining): self
    {
        return new self("Work order {$workOrder->number} still has {$remaining} unticked checklist item(s). Tick them, or complete it as Needs Follow-up.");
    }
}
