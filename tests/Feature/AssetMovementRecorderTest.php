<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\MovementType;
use App\Enums\PlacementType;
use App\Exceptions\AssetTransitionException;
use App\Models\Asset;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Location;
use App\Services\AssetMovementRecorder;
use App\Support\Placement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetMovementRecorderTest extends TestCase
{
    use RefreshDatabase;

    private AssetMovementRecorder $recorder;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->recorder = app(AssetMovementRecorder::class);
        $this->branch = Branch::factory()->create();
    }

    public function test_moving_an_asset_to_an_employee_updates_its_position(): void
    {
        $warehouse = Location::factory()->warehouse()->for($this->branch)->create();
        $employee = Employee::factory()->for($this->branch)->create();
        $asset = Asset::factory()->inWarehouse($warehouse)->create();

        $movement = $this->recorder->record(
            asset: $asset,
            to: Placement::toEmployee($employee),
            movementType: MovementType::Assignment,
        );

        $asset->refresh();

        $this->assertSame(PlacementType::Employee, $asset->placement_type);
        $this->assertSame($employee->id, $asset->current_employee_id);
        $this->assertNull($asset->current_location_id);
        $this->assertSame(AssetStatus::InUse, $asset->status);

        $this->assertSame(PlacementType::Warehouse, $movement->from_placement_type);
        $this->assertSame($warehouse->id, $movement->from_location_id);
        $this->assertSame($employee->id, $movement->to_employee_id);
    }

    public function test_an_asset_already_held_cannot_be_handed_to_someone_else(): void
    {
        $holder = Employee::factory()->for($this->branch)->create();
        $other = Employee::factory()->for($this->branch)->create();
        $asset = Asset::factory()->heldBy($holder)->create();

        $this->expectException(AssetTransitionException::class);
        $this->expectExceptionMessage('must be returned first');

        $this->recorder->record(
            asset: $asset,
            to: Placement::toEmployee($other),
            movementType: MovementType::Assignment,
        );
    }

    public function test_a_disposed_asset_cannot_be_moved(): void
    {
        $warehouse = Location::factory()->warehouse()->for($this->branch)->create();
        $employee = Employee::factory()->for($this->branch)->create();
        $asset = Asset::factory()->inWarehouse($warehouse)->status(AssetStatus::Disposed)->create();

        $this->expectException(AssetTransitionException::class);
        $this->expectExceptionMessage('can no longer be moved');

        $this->recorder->record(
            asset: $asset,
            to: Placement::toEmployee($employee),
            movementType: MovementType::Assignment,
        );
    }

    public function test_a_location_that_cannot_hold_assets_is_rejected(): void
    {
        $warehouse = Location::factory()->warehouse()->for($this->branch)->create();
        $floor = Location::factory()->floor()->for($this->branch)->create();
        $asset = Asset::factory()->inWarehouse($warehouse)->create();

        $this->expectException(AssetTransitionException::class);
        $this->expectExceptionMessage('room or warehouse');

        $this->recorder->record(
            asset: $asset,
            to: new Placement(PlacementType::Location, $this->branch->id, $floor->id),
            movementType: MovementType::Transfer,
        );
    }

    public function test_placing_an_asset_in_a_room_marks_it_in_use(): void
    {
        $warehouse = Location::factory()->warehouse()->for($this->branch)->create();
        $room = Location::factory()->for($this->branch)->create();
        $asset = Asset::factory()->inWarehouse($warehouse)->create();

        $this->recorder->record(
            asset: $asset,
            to: Placement::toLocation($room),
            movementType: MovementType::Transfer,
        );

        $asset->refresh();

        $this->assertSame(AssetStatus::InUse, $asset->status);
        $this->assertSame($room->id, $asset->current_location_id);
        $this->assertNull($asset->current_employee_id);
    }

    public function test_returning_an_asset_to_the_warehouse_makes_it_available_again(): void
    {
        $employee = Employee::factory()->for($this->branch)->create();
        $warehouse = Location::factory()->warehouse()->for($this->branch)->create();
        $asset = Asset::factory()->heldBy($employee)->create();

        $this->recorder->record(
            asset: $asset,
            to: Placement::toLocation($warehouse),
            movementType: MovementType::Return_,
        );

        $asset->refresh();

        $this->assertSame(AssetStatus::Available, $asset->status);
        $this->assertNull($asset->current_employee_id);
        $this->assertTrue($asset->isAssignable());
    }

    public function test_every_movement_is_appended_to_the_ledger(): void
    {
        $warehouse = Location::factory()->warehouse()->for($this->branch)->create();
        $room = Location::factory()->for($this->branch)->create();
        $employee = Employee::factory()->for($this->branch)->create();
        $asset = Asset::factory()->inWarehouse($warehouse)->create();

        $this->recorder->recordInitial($asset);
        $this->recorder->record($asset, Placement::toEmployee($employee), MovementType::Assignment);
        $this->recorder->record($asset, Placement::toLocation($warehouse), MovementType::Return_);
        $this->recorder->record($asset, Placement::toLocation($room), MovementType::Transfer);

        $this->assertSame(4, $asset->movements()->count());
    }
}
