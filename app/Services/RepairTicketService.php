<?php

namespace App\Services;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\MovementType;
use App\Enums\PlacementType;
use App\Enums\RepairTicketStatus;
use App\Enums\RepairType;
use App\Exceptions\AssetTransitionException;
use App\Exceptions\RepairTicketException;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\RepairTicket;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Placement;
use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

/**
 * Moves a repair ticket through reported → verified → approved → in repair →
 * repaired or cannot be repaired; a ticket can be rejected until the repair starts.
 *
 * Unlike preventive work orders, a repair takes the asset out of use. Starting it
 * records a movement to Under Repair — into the vendor's hands for a vendor
 * repair — and finishing it records the asset's return to where it was sent from.
 */
class RepairTicketService
{
    public function __construct(
        private readonly DocumentNumberGenerator $numbers,
        private readonly AssetMovementRecorder $recorder,
        private readonly MaintenanceNotifier $notifier,
    ) {}

    /**
     * A repair ticket number shaped RPR/YYMM/SEQ.
     */
    public function nextNumber(): string
    {
        return $this->numbers->document('repair_ticket', 'RPR');
    }

    /**
     * Records damage to an asset. Whether the asset is under warranty is fixed at
     * this moment, so the ticket keeps saying the vendor should pay even after the
     * warranty runs out.
     *
     * @param  array{title: string, description?: ?string, priority?: mixed, reported_by_employee_id?: ?int, reported_at?: mixed}  $details
     */
    public function report(Asset $asset, array $details, ?User $reportedBy = null): RepairTicket
    {
        if ($asset->status->isTerminal()) {
            throw RepairTicketException::assetOutOfService($asset);
        }

        $ticket = RepairTicket::create([
            ...Arr::only($details, ['title', 'description', 'priority', 'reported_by_employee_id']),
            'number' => $this->nextNumber(),
            'asset_id' => $asset->id,
            'reported_at' => $details['reported_at'] ?? now(),
            'is_under_warranty' => $asset->isUnderWarranty(),
            'created_by' => $reportedBy?->id,
        ]);

        $this->notifier->repairTicketReported($ticket, $reportedBy);

        return $ticket;
    }

    /**
     * Confirms the damage and decides how it will be repaired: in-house, or by a
     * service vendor.
     */
    public function verify(
        RepairTicket $ticket,
        RepairType $repairType,
        User $verifiedBy,
        ?int $assignedTo = null,
        ?int $supplierId = null,
        ?string $estimatedCost = null,
    ): RepairTicket {
        $this->ensureStatus($ticket, 'verified', RepairTicketStatus::Reported);

        if ($repairType === RepairType::Vendor && $supplierId === null) {
            throw RepairTicketException::vendorRequired($ticket);
        }

        $ticket->forceFill([
            'status' => RepairTicketStatus::Verified,
            'repair_type' => $repairType,
            'assigned_to' => $assignedTo,
            'supplier_id' => $repairType === RepairType::Vendor ? $supplierId : null,
            'estimated_cost' => $estimatedCost ?? 0,
            'verified_at' => now(),
            'verified_by' => $verifiedBy->id,
        ])->save();

        $this->notifier->repairTicketAwaitingApproval($ticket, $verifiedBy);
        $this->notifier->repairTicketAssigned($ticket, $verifiedBy);

        return $ticket;
    }

    public function approve(RepairTicket $ticket, User $approvedBy): RepairTicket
    {
        $this->ensureStatus($ticket, 'approved', RepairTicketStatus::Verified);

        $ticket->forceFill([
            'status' => RepairTicketStatus::Approved,
            'approved_at' => now(),
            'approved_by' => $approvedBy->id,
        ])->save();

        return $ticket;
    }

    /**
     * Closes a ticket that will not be repaired — a false report or a duplicate —
     * any time before the repair starts.
     */
    public function reject(RepairTicket $ticket, string $reason, User $rejectedBy): RepairTicket
    {
        $this->ensureStatus($ticket, 'rejected', RepairTicketStatus::Reported, RepairTicketStatus::Verified, RepairTicketStatus::Approved);

        $ticket->forceFill([
            'status' => RepairTicketStatus::Rejected,
            'rejected_at' => now(),
            'rejected_by' => $rejectedBy->id,
            'rejection_reason' => $reason,
        ])->save();

        return $ticket;
    }

    /**
     * Takes the asset out of use. A vendor repair moves it to the vendor; an
     * in-house repair leaves it where it is. Either way it stays Under Repair until
     * the ticket is finished.
     */
    public function start(RepairTicket $ticket): RepairTicket
    {
        $this->ensureStatus($ticket, 'started', RepairTicketStatus::Approved);

        $asset = $this->assetOf($ticket);

        $otherRepair = RepairTicket::query()
            ->where('asset_id', $asset->id)
            ->where('status', RepairTicketStatus::InRepair->value)
            ->whereKeyNot($ticket->getKey())
            ->first();

        if ($otherRepair !== null) {
            throw RepairTicketException::alreadyInRepair($asset, $otherRepair);
        }

        $destination = $ticket->repair_type === RepairType::Vendor
            ? new Placement(type: PlacementType::Vendor, branchId: $asset->branch_id)
            : Placement::fromAsset($asset);

        return DB::transaction(function () use ($ticket, $asset, $destination): RepairTicket {
            $movement = $this->recordMovement($ticket, fn (): AssetMovement => $this->recorder->record(
                asset: $asset,
                to: $destination,
                movementType: MovementType::Repair,
                status: AssetStatus::UnderRepair,
                reference: $ticket,
                notes: $this->movementNotes($ticket, 'Sent for repair'),
            ));

            $ticket->forceFill([
                'status' => RepairTicketStatus::InRepair,
                'started_at' => $movement->moved_at,
                'repair_movement_id' => $movement->id,
            ])->save();

            return $ticket;
        });
    }

    /**
     * Ends the repair and records the asset's return to the placement it was sent
     * from. A repaired asset gets back the status it had; one that cannot be
     * repaired is retired, ready to be proposed for disposal.
     */
    public function complete(
        RepairTicket $ticket,
        bool $isRepaired,
        User $completedBy,
        ?string $actualCost = null,
        ?string $resolution = null,
        ?AssetCondition $condition = null,
    ): RepairTicket {
        $this->ensureStatus($ticket, 'completed', RepairTicketStatus::InRepair);

        if (! $isRepaired && blank($resolution)) {
            throw RepairTicketException::resolutionRequired($ticket);
        }

        $asset = $this->assetOf($ticket);
        $sentFrom = $ticket->repairMovement()->first();

        $returnTo = $sentFrom?->from_placement_type === null
            ? Placement::fromAsset($asset)
            : new Placement(
                type: $sentFrom->from_placement_type,
                branchId: $sentFrom->from_branch_id,
                locationId: $sentFrom->from_location_id,
                employeeId: $sentFrom->from_employee_id,
            );

        $status = $isRepaired ? ($sentFrom?->status_before ?? AssetStatus::Available) : AssetStatus::Retired;
        $condition ??= $isRepaired ? AssetCondition::Good : AssetCondition::Broken;

        return DB::transaction(function () use ($ticket, $asset, $returnTo, $status, $condition, $isRepaired, $completedBy, $actualCost, $resolution): RepairTicket {
            $movement = $this->recordMovement($ticket, fn (): AssetMovement => $this->recorder->record(
                asset: $asset,
                to: $returnTo,
                movementType: MovementType::Repair,
                status: $status,
                condition: $condition,
                reference: $ticket,
                notes: $this->movementNotes($ticket, $isRepaired ? 'Returned from repair' : 'Cannot be repaired'),
            ));

            $ticket->forceFill([
                'status' => $isRepaired ? RepairTicketStatus::Repaired : RepairTicketStatus::Unrepairable,
                'completed_at' => $movement->moved_at,
                'completed_by' => $completedBy->id,
                'actual_cost' => $actualCost,
                'resolution' => $resolution,
            ])->save();

            return $ticket;
        });
    }

    private function ensureStatus(RepairTicket $ticket, string $action, RepairTicketStatus ...$allowed): void
    {
        if (! in_array($ticket->status, $allowed, strict: true)) {
            throw RepairTicketException::notInStatus($ticket, $action, ...$allowed);
        }
    }

    private function assetOf(RepairTicket $ticket): Asset
    {
        return $ticket->asset()->withTrashed()->firstOrFail();
    }

    /**
     * @param  Closure(): AssetMovement  $record
     */
    private function recordMovement(RepairTicket $ticket, Closure $record): AssetMovement
    {
        try {
            return $record();
        } catch (AssetTransitionException $exception) {
            throw RepairTicketException::assetCannotMove($ticket, $exception);
        }
    }

    private function movementNotes(RepairTicket $ticket, string $event): string
    {
        $vendor = $ticket->repair_type === RepairType::Vendor
            ? Supplier::query()->whereKey($ticket->supplier_id)->value('name')
            : null;

        return $vendor === null
            ? "{$event} — {$ticket->number}"
            : "{$event} — {$ticket->number}, {$vendor}";
    }
}
