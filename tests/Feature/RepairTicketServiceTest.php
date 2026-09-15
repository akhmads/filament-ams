<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\MovementType;
use App\Enums\PlacementType;
use App\Enums\RepairPriority;
use App\Enums\RepairTicketStatus;
use App\Enums\RepairType;
use App\Exceptions\RepairTicketException;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\Employee;
use App\Models\Location;
use App\Models\RepairTicket;
use App\Models\Supplier;
use App\Models\User;
use App\Services\RepairTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairTicketServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_reporting_numbers_the_ticket_and_records_the_warranty_at_that_moment(): void
    {
        $asset = Asset::factory()->warrantyUntil(now()->addMonth()->toDateString())->create();
        $reporter = User::factory()->create();

        $ticket = $this->service()->report($asset, ['title' => 'Screen flickers', 'priority' => RepairPriority::High->value], $reporter);

        $ticket->refresh();
        $this->assertStringStartsWith('RPR/', $ticket->number);
        $this->assertSame(RepairTicketStatus::Reported, $ticket->status);
        $this->assertSame(RepairPriority::High, $ticket->priority);
        $this->assertTrue($ticket->is_under_warranty);
        $this->assertSame($reporter->id, $ticket->created_by);
    }

    public function test_a_lost_asset_cannot_be_reported_for_repair(): void
    {
        $asset = Asset::factory()->status(AssetStatus::Lost)->create();

        $this->expectException(RepairTicketException::class);

        $this->service()->report($asset, ['title' => 'Broken']);
    }

    public function test_verifying_an_in_house_repair_records_the_technician_and_estimate(): void
    {
        $ticket = RepairTicket::factory()->create();
        $verifier = User::factory()->create();
        $technician = User::factory()->create();

        $this->service()->verify($ticket, RepairType::Internal, $verifier, $technician->id, Supplier::factory()->create()->id, '250000');

        $ticket->refresh();
        $this->assertSame(RepairTicketStatus::Verified, $ticket->status);
        $this->assertSame(RepairType::Internal, $ticket->repair_type);
        $this->assertSame($technician->id, $ticket->assigned_to);
        $this->assertNull($ticket->supplier_id);
        $this->assertSame('250000.00', $ticket->estimated_cost);
        $this->assertSame($verifier->id, $ticket->verified_by);
    }

    public function test_a_vendor_repair_needs_a_vendor(): void
    {
        $ticket = RepairTicket::factory()->create();

        $this->expectException(RepairTicketException::class);

        $this->service()->verify($ticket, RepairType::Vendor, User::factory()->create());
    }

    public function test_only_a_verified_ticket_can_be_approved(): void
    {
        $ticket = RepairTicket::factory()->create();

        $this->expectException(RepairTicketException::class);

        $this->service()->approve($ticket, User::factory()->create());
    }

    public function test_starting_an_in_house_repair_keeps_the_asset_in_place_but_takes_it_out_of_use(): void
    {
        $employee = Employee::factory()->create();
        $asset = Asset::factory()->heldBy($employee)->create();
        $ticket = RepairTicket::factory()->approved()->for($asset)->create();

        $this->service()->start($ticket);

        $asset->refresh();
        $this->assertSame(AssetStatus::UnderRepair, $asset->status);
        $this->assertSame(PlacementType::Employee, $asset->placement_type);
        $this->assertSame($employee->id, $asset->current_employee_id);

        $movement = AssetMovement::query()->where('asset_id', $asset->id)->sole();
        $this->assertSame(MovementType::Repair, $movement->movement_type);
        $this->assertTrue($movement->reference->is($ticket));

        $ticket->refresh();
        $this->assertSame(RepairTicketStatus::InRepair, $ticket->status);
        $this->assertSame($movement->id, $ticket->repair_movement_id);
        $this->assertNotNull($ticket->started_at);
    }

    public function test_starting_a_vendor_repair_hands_the_asset_to_the_vendor(): void
    {
        $asset = Asset::factory()->heldBy(Employee::factory()->create())->create();
        $ticket = RepairTicket::factory()->atVendor()->approved()->for($asset)->create();

        $this->service()->start($ticket);

        $asset->refresh();
        $this->assertSame(PlacementType::Vendor, $asset->placement_type);
        $this->assertNull($asset->current_employee_id);
        $this->assertSame(AssetStatus::UnderRepair, $asset->status);
    }

    public function test_finishing_a_vendor_repair_returns_the_asset_to_its_holder_with_its_old_status(): void
    {
        $employee = Employee::factory()->create();
        $asset = Asset::factory()->heldBy($employee)->create(['condition' => AssetCondition::MajorDamage]);
        $ticket = RepairTicket::factory()->atVendor()->approved()->for($asset)->create();
        $technician = User::factory()->create();

        $this->service()->start($ticket);
        $this->service()->complete($ticket, true, $technician, '480000', 'Main board replaced');

        $asset->refresh();
        $this->assertSame(PlacementType::Employee, $asset->placement_type);
        $this->assertSame($employee->id, $asset->current_employee_id);
        $this->assertSame(AssetStatus::InUse, $asset->status);
        $this->assertSame(AssetCondition::Good, $asset->condition);

        $ticket->refresh();
        $this->assertSame(RepairTicketStatus::Repaired, $ticket->status);
        $this->assertSame('480000.00', $ticket->actual_cost);
        $this->assertSame('Main board replaced', $ticket->resolution);
        $this->assertSame($technician->id, $ticket->completed_by);
        $this->assertSame(2, AssetMovement::query()->where('asset_id', $asset->id)->where('movement_type', MovementType::Repair->value)->count());
    }

    public function test_an_asset_that_cannot_be_repaired_is_retired(): void
    {
        $warehouse = Location::factory()->warehouse()->create();
        $asset = Asset::factory()->inWarehouse($warehouse)->create();
        $ticket = RepairTicket::factory()->approved()->for($asset)->create();

        $this->service()->start($ticket);
        $this->service()->complete($ticket, false, User::factory()->create(), resolution: 'Spare parts are no longer made');

        $asset->refresh();
        $this->assertSame(AssetStatus::Retired, $asset->status);
        $this->assertSame(AssetCondition::Broken, $asset->condition);
        $this->assertSame($warehouse->id, $asset->current_location_id);
        $this->assertSame(RepairTicketStatus::Unrepairable, $ticket->refresh()->status);
    }

    public function test_writing_an_asset_off_needs_a_reason(): void
    {
        $ticket = RepairTicket::factory()->inRepair()->create();

        $this->expectException(RepairTicketException::class);

        $this->service()->complete($ticket, false, User::factory()->create());
    }

    public function test_an_asset_already_in_repair_cannot_start_a_second_repair(): void
    {
        $asset = Asset::factory()->create();
        RepairTicket::factory()->inRepair()->for($asset)->create(['number' => 'RPR/2609/0001']);
        $ticket = RepairTicket::factory()->approved()->for($asset)->create();

        try {
            $this->service()->start($ticket);
            $this->fail('A second repair on the same asset should not start.');
        } catch (RepairTicketException $exception) {
            $this->assertStringContainsString('RPR/2609/0001', $exception->getMessage());
        }

        $this->assertSame(RepairTicketStatus::Approved, $ticket->refresh()->status);
    }

    public function test_a_repair_cannot_start_while_the_asset_is_in_transit(): void
    {
        $asset = Asset::factory()->status(AssetStatus::InTransit)->create();
        $ticket = RepairTicket::factory()->atVendor()->approved()->for($asset)->create();

        try {
            $this->service()->start($ticket);
            $this->fail('An asset in transit should not go into repair.');
        } catch (RepairTicketException) {
            // Expected.
        }

        $this->assertSame(RepairTicketStatus::Approved, $ticket->refresh()->status);
        $this->assertSame(AssetStatus::InTransit, $asset->refresh()->status);
        $this->assertSame(0, AssetMovement::query()->count());
    }

    public function test_a_ticket_can_be_rejected_before_the_repair_starts(): void
    {
        $ticket = RepairTicket::factory()->verified()->create();
        $rejectedBy = User::factory()->create();

        $this->service()->reject($ticket, 'Duplicate of RPR/2609/0003', $rejectedBy);

        $ticket->refresh();
        $this->assertSame(RepairTicketStatus::Rejected, $ticket->status);
        $this->assertSame('Duplicate of RPR/2609/0003', $ticket->rejection_reason);
        $this->assertSame($rejectedBy->id, $ticket->rejected_by);
    }

    public function test_a_repair_in_progress_cannot_be_rejected(): void
    {
        $ticket = RepairTicket::factory()->inRepair()->create();

        $this->expectException(RepairTicketException::class);

        $this->service()->reject($ticket, 'Too late', User::factory()->create());
    }

    public function test_downtime_runs_from_the_start_of_the_repair_to_its_end(): void
    {
        $this->travelTo('2026-09-15 08:00:00');
        $warehouse = Location::factory()->warehouse()->create();
        $ticket = RepairTicket::factory()->approved()->for(Asset::factory()->inWarehouse($warehouse))->create();

        $this->service()->start($ticket);
        $this->travelTo('2026-09-16 10:30:00');
        $this->service()->complete($ticket, true, User::factory()->create());

        $this->assertSame(26 * 60 + 30, $ticket->refresh()->downtimeMinutes());
    }

    private function service(): RepairTicketService
    {
        return app(RepairTicketService::class);
    }
}
