<?php

namespace App\Support;

use App\Enums\PlacementType;
use App\Models\Asset;
use App\Models\Employee;
use App\Models\Location;

/**
 * Where an asset sits: a placement type together with the branch, room or
 * employee it belongs to.
 */
final readonly class Placement
{
    public function __construct(
        public PlacementType $type,
        public ?int $branchId = null,
        public ?int $locationId = null,
        public ?int $employeeId = null,
    ) {}

    public static function fromAsset(Asset $asset): self
    {
        return new self(
            type: $asset->placement_type,
            branchId: $asset->branch_id,
            locationId: $asset->current_location_id,
            employeeId: $asset->current_employee_id,
        );
    }

    public static function toEmployee(Employee $employee): self
    {
        return new self(
            type: PlacementType::Employee,
            branchId: $employee->branch_id,
            employeeId: $employee->id,
        );
    }

    public static function toLocation(Location $location): self
    {
        return new self(
            type: $location->type->canHoldAssets() && $location->type->value === 'warehouse'
                ? PlacementType::Warehouse
                : PlacementType::Location,
            branchId: $location->branch_id,
            locationId: $location->id,
        );
    }

    public function isSameAs(self $other): bool
    {
        return $this->type === $other->type
            && $this->branchId === $other->branchId
            && $this->locationId === $other->locationId
            && $this->employeeId === $other->employeeId;
    }

    /**
     * @return array<string, mixed>
     */
    public function toColumns(string $prefix): array
    {
        return [
            "{$prefix}_placement_type" => $this->type->value,
            "{$prefix}_branch_id" => $this->branchId,
            "{$prefix}_location_id" => $this->locationId,
            "{$prefix}_employee_id" => $this->employeeId,
        ];
    }
}
