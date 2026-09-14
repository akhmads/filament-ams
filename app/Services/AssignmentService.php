<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\AssignmentStatus;
use App\Enums\AssignmentType;
use App\Enums\PlacementType;
use App\Exceptions\AssetTransitionException;
use App\Models\AssetAssignment;
use App\Support\Placement;
use Illuminate\Support\Facades\DB;

/**
 * Executes a handover document: moves every asset on it to the destination
 * and locks the document against further edits.
 */
class AssignmentService
{
    public function __construct(
        private readonly AssetMovementRecorder $recorder,
    ) {}

    public function complete(AssetAssignment $assignment): AssetAssignment
    {
        if ($assignment->status !== AssignmentStatus::Draft) {
            throw AssetTransitionException::missingTarget('Only draft documents can be completed.');
        }

        $assignment->loadMissing('items.asset');

        if ($assignment->items->isEmpty()) {
            throw AssetTransitionException::missingTarget('The document does not contain any assets yet.');
        }

        return DB::transaction(function () use ($assignment): AssetAssignment {
            $destination = new Placement(
                type: $assignment->to_placement_type,
                branchId: $assignment->to_branch_id ?? $assignment->branch_id,
                locationId: $assignment->to_location_id,
                employeeId: $assignment->to_employee_id,
            );

            foreach ($assignment->items as $item) {
                $this->recorder->record(
                    asset: $item->asset,
                    to: $destination,
                    movementType: $assignment->type->toMovementType(),
                    status: $this->statusFor($assignment),
                    condition: $item->condition,
                    reference: $assignment,
                    notes: $item->notes ?? $assignment->purpose,
                    movedAt: $assignment->assignment_date,
                );
            }

            $assignment->forceFill([
                'status' => AssignmentStatus::Completed,
                'completed_at' => now(),
            ])->save();

            return $assignment->refresh();
        });
    }

    public function cancel(AssetAssignment $assignment): AssetAssignment
    {
        if ($assignment->status === AssignmentStatus::Completed) {
            throw AssetTransitionException::missingTarget('A completed document cannot be cancelled. Create a return document to correct it.');
        }

        $assignment->forceFill(['status' => AssignmentStatus::Cancelled])->save();

        return $assignment;
    }

    private function statusFor(AssetAssignment $assignment): AssetStatus
    {
        return match (true) {
            $assignment->type === AssignmentType::Checkin => AssetStatus::Available,
            $assignment->to_placement_type === PlacementType::Warehouse => AssetStatus::Available,
            $assignment->to_placement_type === PlacementType::InTransit => AssetStatus::InTransit,
            default => AssetStatus::InUse,
        };
    }
}
