<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\AssignmentStatus;
use App\Enums\AssignmentType;
use App\Enums\MovementType;
use App\Enums\PlacementType;
use App\Exceptions\AssetTransitionException;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetAssignmentItem;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Location;
use App\Services\AssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private AssignmentService $service;

    private Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(AssignmentService::class);
        $this->branch = Branch::factory()->create();
    }

    public function test_completing_a_checkout_moves_every_asset_on_the_document(): void
    {
        $warehouse = Location::factory()->warehouse()->for($this->branch)->create();
        $employee = Employee::factory()->for($this->branch)->create();

        $assets = Asset::factory()->count(3)->inWarehouse($warehouse)->create();

        $assignment = AssetAssignment::factory()->for($this->branch)->create([
            'type' => AssignmentType::Checkout,
            'to_placement_type' => PlacementType::Employee,
            'to_employee_id' => $employee->id,
        ]);

        foreach ($assets as $asset) {
            AssetAssignmentItem::factory()->create([
                'asset_assignment_id' => $assignment->id,
                'asset_id' => $asset->id,
            ]);
        }

        $this->service->complete($assignment);

        $this->assertSame(AssignmentStatus::Completed, $assignment->refresh()->status);
        $this->assertNotNull($assignment->completed_at);

        foreach ($assets as $asset) {
            $asset->refresh();

            $this->assertSame($employee->id, $asset->current_employee_id);
            $this->assertSame(AssetStatus::InUse, $asset->status);
            $this->assertSame(
                MovementType::Assignment,
                $asset->movements()->first()->movement_type,
            );
        }
    }

    public function test_the_movement_references_the_assignment_document(): void
    {
        $warehouse = Location::factory()->warehouse()->for($this->branch)->create();
        $employee = Employee::factory()->for($this->branch)->create();
        $asset = Asset::factory()->inWarehouse($warehouse)->create();

        $assignment = AssetAssignment::factory()->for($this->branch)->create([
            'to_placement_type' => PlacementType::Employee,
            'to_employee_id' => $employee->id,
        ]);

        AssetAssignmentItem::factory()->create([
            'asset_assignment_id' => $assignment->id,
            'asset_id' => $asset->id,
        ]);

        $this->service->complete($assignment);

        $movement = $asset->movements()->first();

        $this->assertSame(AssetAssignment::class, $movement->reference_type);
        $this->assertSame($assignment->id, $movement->reference_id);
    }

    public function test_the_condition_recorded_on_the_document_is_carried_to_the_asset(): void
    {
        $warehouse = Location::factory()->warehouse()->for($this->branch)->create();
        $employee = Employee::factory()->for($this->branch)->create();
        $asset = Asset::factory()->inWarehouse($warehouse)->create();

        $assignment = AssetAssignment::factory()->for($this->branch)->create([
            'to_placement_type' => PlacementType::Employee,
            'to_employee_id' => $employee->id,
        ]);

        AssetAssignmentItem::factory()->create([
            'asset_assignment_id' => $assignment->id,
            'asset_id' => $asset->id,
            'condition' => AssetCondition::MinorDamage,
        ]);

        $this->service->complete($assignment);

        $this->assertSame(AssetCondition::MinorDamage, $asset->refresh()->condition);
    }

    public function test_a_checkin_returns_the_asset_to_the_warehouse_and_frees_it(): void
    {
        $employee = Employee::factory()->for($this->branch)->create();
        $warehouse = Location::factory()->warehouse()->for($this->branch)->create();
        $asset = Asset::factory()->heldBy($employee)->create();

        $assignment = AssetAssignment::factory()->for($this->branch)->create([
            'type' => AssignmentType::Checkin,
            'to_placement_type' => PlacementType::Warehouse,
            'to_location_id' => $warehouse->id,
        ]);

        AssetAssignmentItem::factory()->create([
            'asset_assignment_id' => $assignment->id,
            'asset_id' => $asset->id,
        ]);

        $this->service->complete($assignment);

        $asset->refresh();

        $this->assertNull($asset->current_employee_id);
        $this->assertSame($warehouse->id, $asset->current_location_id);
        $this->assertSame(AssetStatus::Available, $asset->status);
    }

    public function test_a_document_cannot_be_completed_twice(): void
    {
        $warehouse = Location::factory()->warehouse()->for($this->branch)->create();
        $employee = Employee::factory()->for($this->branch)->create();
        $asset = Asset::factory()->inWarehouse($warehouse)->create();

        $assignment = AssetAssignment::factory()->for($this->branch)->create([
            'to_placement_type' => PlacementType::Employee,
            'to_employee_id' => $employee->id,
        ]);

        AssetAssignmentItem::factory()->create([
            'asset_assignment_id' => $assignment->id,
            'asset_id' => $asset->id,
        ]);

        $this->service->complete($assignment);

        $this->expectException(AssetTransitionException::class);
        $this->expectExceptionMessage('Only draft documents');

        $this->service->complete($assignment);
    }

    public function test_an_empty_document_cannot_be_completed(): void
    {
        $assignment = AssetAssignment::factory()->for($this->branch)->create([
            'to_placement_type' => PlacementType::Employee,
            'to_employee_id' => Employee::factory()->for($this->branch)->create()->id,
        ]);

        $this->expectException(AssetTransitionException::class);
        $this->expectExceptionMessage('does not contain any assets');

        $this->service->complete($assignment);
    }

    public function test_nothing_is_moved_when_one_asset_on_the_document_is_rejected(): void
    {
        $warehouse = Location::factory()->warehouse()->for($this->branch)->create();
        $employee = Employee::factory()->for($this->branch)->create();
        $otherHolder = Employee::factory()->for($this->branch)->create();

        $movable = Asset::factory()->inWarehouse($warehouse)->create();
        $blocked = Asset::factory()->heldBy($otherHolder)->create();

        $assignment = AssetAssignment::factory()->for($this->branch)->create([
            'to_placement_type' => PlacementType::Employee,
            'to_employee_id' => $employee->id,
        ]);

        foreach ([$movable, $blocked] as $asset) {
            AssetAssignmentItem::factory()->create([
                'asset_assignment_id' => $assignment->id,
                'asset_id' => $asset->id,
            ]);
        }

        try {
            $this->service->complete($assignment);
            $this->fail('A document containing a blocked asset should have been rejected.');
        } catch (AssetTransitionException) {
            // The whole transaction must be rolled back.
        }

        $this->assertNull($movable->refresh()->current_employee_id);
        $this->assertSame($otherHolder->id, $blocked->refresh()->current_employee_id);
        $this->assertSame(AssignmentStatus::Draft, $assignment->refresh()->status);
    }

    public function test_an_overdue_loan_is_detected(): void
    {
        $assignment = AssetAssignment::factory()->for($this->branch)->completed()->create([
            'type' => AssignmentType::Checkout,
            'expected_return_date' => now()->subDays(3),
            'to_employee_id' => Employee::factory()->for($this->branch)->create()->id,
        ]);

        $this->assertTrue($assignment->isOverdue());
        $this->assertSame(1, AssetAssignment::query()->overdue()->count());
    }
}
