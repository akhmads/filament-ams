<?php

namespace App\Services;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\MovementType;
use App\Enums\PlacementType;
use App\Exceptions\AssetTransitionException;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Location;
use App\Support\Placement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * The only way an asset moves. Writes a ledger row to `asset_movements`,
 * then updates the cached position columns on `assets`.
 */
class AssetMovementRecorder
{
    public function record(
        Asset $asset,
        Placement $to,
        MovementType $movementType,
        ?AssetStatus $status = null,
        ?AssetCondition $condition = null,
        ?Model $reference = null,
        ?string $notes = null,
        ?\DateTimeInterface $movedAt = null,
    ): AssetMovement {
        $this->guard($asset, $to, $movementType);

        $from = Placement::fromAsset($asset);
        $targetStatus = $status ?? $this->defaultStatusFor($to->type, $asset->status);

        if (! $asset->status->canTransitionTo($targetStatus)) {
            throw AssetTransitionException::invalidStatus($asset, $targetStatus);
        }

        return DB::transaction(function () use ($asset, $from, $to, $movementType, $targetStatus, $condition, $reference, $notes, $movedAt): AssetMovement {
            $movement = new AssetMovement([
                'asset_id' => $asset->id,
                'movement_type' => $movementType,
                'moved_at' => $movedAt ?? now(),
                'condition_before' => $asset->condition,
                'condition_after' => $condition ?? $asset->condition,
                'status_before' => $asset->status,
                'status_after' => $targetStatus,
                'notes' => $notes,
                'performed_by' => Auth::id(),
                ...$from->toColumns('from'),
                ...$to->toColumns('to'),
            ]);

            if ($reference !== null) {
                $movement->reference()->associate($reference);
            }

            $movement->save();

            $asset->forceFill([
                'placement_type' => $to->type,
                'branch_id' => $to->branchId ?? $asset->branch_id,
                'current_location_id' => $to->locationId,
                'current_employee_id' => $to->employeeId,
                'status' => $targetStatus,
                'condition' => $condition ?? $asset->condition,
            ])->save();

            return $movement;
        });
    }

    /**
     * Records where an asset sits when it is first registered.
     */
    public function recordInitial(Asset $asset, ?string $notes = null): AssetMovement
    {
        $movement = new AssetMovement([
            'asset_id' => $asset->id,
            'movement_type' => MovementType::Initial,
            'moved_at' => $asset->acquisition_date ?? now(),
            'condition_after' => $asset->condition,
            'status_after' => $asset->status,
            'notes' => $notes ?? 'Initial asset record',
            'performed_by' => Auth::id(),
            ...Placement::fromAsset($asset)->toColumns('to'),
        ]);

        $movement->save();

        return $movement;
    }

    private function guard(Asset $asset, Placement $to, MovementType $movementType): void
    {
        if ($asset->status->isTerminal()) {
            throw AssetTransitionException::terminal($asset);
        }

        // An asset still recorded in an employee's hands must be returned
        // before it can be handed to anyone else.
        if ($movementType === MovementType::Assignment
            && $asset->current_employee_id !== null
            && $asset->current_employee_id !== $to->employeeId) {
            throw AssetTransitionException::alreadyHeld($asset);
        }

        if ($to->type->requiresEmployee() && $to->employeeId === null) {
            throw AssetTransitionException::missingTarget('A receiving employee is required for an employee placement.');
        }

        if ($to->type->requiresLocation()) {
            if ($to->locationId === null) {
                throw AssetTransitionException::missingTarget('A destination room or warehouse is required.');
            }

            $location = Location::find($to->locationId);

            if ($location === null || ! $location->type->canHoldAssets()) {
                throw AssetTransitionException::missingTarget('The destination must be a room or warehouse that can hold assets.');
            }
        }
    }

    private function defaultStatusFor(PlacementType $type, AssetStatus $current): AssetStatus
    {
        return match ($type) {
            PlacementType::Employee, PlacementType::Location => AssetStatus::InUse,
            PlacementType::Warehouse => AssetStatus::Available,
            PlacementType::InTransit => AssetStatus::InTransit,
            PlacementType::Vendor => $current,
        };
    }
}
