<?php

namespace App\Exceptions;

use App\Enums\RepairTicketStatus;
use App\Models\Asset;
use App\Models\RepairTicket;
use Exception;

class RepairTicketException extends Exception
{
    public static function notInStatus(RepairTicket $ticket, string $action, RepairTicketStatus ...$allowed): self
    {
        $allowedLabels = collect($allowed)->map(fn (RepairTicketStatus $status): string => $status->getLabel())->join(', ', ' or ');

        return new self("Repair ticket {$ticket->number} is {$ticket->status->getLabel()}; only {$allowedLabels} tickets can be {$action}.");
    }

    public static function assetOutOfService(Asset $asset): self
    {
        return new self("Asset {$asset->code} is {$asset->status->getLabel()} and can no longer be repaired.");
    }

    public static function alreadyInRepair(Asset $asset, RepairTicket $other): self
    {
        return new self("Asset {$asset->code} is already being repaired under {$other->number}. Finish that repair first.");
    }

    public static function vendorRequired(RepairTicket $ticket): self
    {
        return new self("Repair ticket {$ticket->number} is a vendor repair and needs a service vendor.");
    }

    public static function resolutionRequired(RepairTicket $ticket): self
    {
        return new self("Explain why the asset on repair ticket {$ticket->number} cannot be repaired.");
    }

    public static function assetCannotMove(RepairTicket $ticket, AssetTransitionException $previous): self
    {
        return new self("Repair ticket {$ticket->number}: {$previous->getMessage()}", previous: $previous);
    }
}
