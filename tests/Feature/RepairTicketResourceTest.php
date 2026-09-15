<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\RepairPriority;
use App\Enums\RepairTicketStatus;
use App\Filament\Resources\Assets\Pages\ViewAsset;
use App\Filament\Resources\Assets\RelationManagers\RepairTicketsRelationManager;
use App\Filament\Resources\RepairTickets\Pages\CreateRepairTicket;
use App\Filament\Resources\RepairTickets\Pages\ViewRepairTicket;
use App\Filament\Resources\RepairTickets\RepairTicketResource;
use App\Models\Asset;
use App\Models\Location;
use App\Models\RepairTicket;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RepairTicketResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_damage_reported_from_the_form_gets_a_number(): void
    {
        $staff = $this->userWithRole('asset_staff');
        $asset = Asset::factory()->create();

        Livewire::actingAs($staff)
            ->test(CreateRepairTicket::class)
            ->fillForm([
                'asset_id' => $asset->id,
                'title' => 'Fan is noisy',
                'priority' => RepairPriority::High->value,
                'reported_at' => '2026-09-15 09:00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $ticket = RepairTicket::query()->sole();
        $this->assertStringStartsWith('RPR/', $ticket->number);
        $this->assertSame($asset->id, $ticket->asset_id);
        $this->assertSame(RepairPriority::High, $ticket->priority);
        $this->assertSame(RepairTicketStatus::Reported, $ticket->status);
        $this->assertSame($staff->id, $ticket->created_by);
    }

    public function test_opening_the_form_from_an_asset_picks_that_asset(): void
    {
        $asset = Asset::factory()->create();

        Livewire::withQueryParams(['asset' => (string) $asset->id])
            ->actingAs($this->userWithRole('asset_staff'))
            ->test(CreateRepairTicket::class)
            ->assertSchemaStateSet(['asset_id' => $asset->id]);
    }

    public function test_staff_verify_start_and_finish_a_repair_that_a_manager_approves(): void
    {
        $staff = $this->userWithRole('asset_staff');
        $manager = $this->userWithRole('asset_manager');
        $asset = Asset::factory()->inWarehouse(Location::factory()->warehouse()->create())->create();
        $ticket = RepairTicket::factory()->for($asset)->create();

        Livewire::actingAs($staff)
            ->test(ViewRepairTicket::class, ['record' => $ticket->getKey()])
            ->callAction('verifyRepairTicket', ['repair_type' => 'internal', 'estimated_cost' => '250000'])
            ->assertHasNoFormErrors();

        $this->assertSame(RepairTicketStatus::Verified, $ticket->refresh()->status);

        Livewire::actingAs($staff)
            ->test(ViewRepairTicket::class, ['record' => $ticket->getKey()])
            ->assertActionHidden('approveRepairTicket');

        Livewire::actingAs($manager)
            ->test(ViewRepairTicket::class, ['record' => $ticket->getKey()])
            ->callAction('approveRepairTicket');

        $this->assertSame(RepairTicketStatus::Approved, $ticket->refresh()->status);

        Livewire::actingAs($staff)
            ->test(ViewRepairTicket::class, ['record' => $ticket->getKey()])
            ->callAction('startRepair');

        $this->assertSame(RepairTicketStatus::InRepair, $ticket->refresh()->status);
        $this->assertSame(AssetStatus::UnderRepair, $asset->refresh()->status);

        Livewire::actingAs($staff)
            ->test(ViewRepairTicket::class, ['record' => $ticket->getKey()])
            ->callAction('completeRepair', [
                'outcome' => RepairTicketStatus::Repaired->value,
                'condition' => 'good',
                'actual_cost' => '300000',
                'resolution' => 'Fan replaced',
            ])
            ->assertHasNoFormErrors();

        $ticket->refresh();
        $this->assertSame(RepairTicketStatus::Repaired, $ticket->status);
        $this->assertSame('300000.00', $ticket->actual_cost);
        $this->assertSame(AssetStatus::Available, $asset->refresh()->status);
    }

    public function test_a_vendor_repair_needs_a_vendor_on_the_form(): void
    {
        $ticket = RepairTicket::factory()->create();

        Livewire::actingAs($this->userWithRole('asset_staff'))
            ->test(ViewRepairTicket::class, ['record' => $ticket->getKey()])
            ->callAction('verifyRepairTicket', ['repair_type' => 'vendor'])
            ->assertHasFormErrors(['supplier_id' => 'required']);

        $this->assertSame(RepairTicketStatus::Reported, $ticket->refresh()->status);
    }

    public function test_writing_an_asset_off_needs_an_explanation_on_the_form(): void
    {
        $ticket = RepairTicket::factory()->inRepair()->create();

        Livewire::actingAs($this->userWithRole('asset_staff'))
            ->test(ViewRepairTicket::class, ['record' => $ticket->getKey()])
            ->callAction('completeRepair', ['outcome' => RepairTicketStatus::Unrepairable->value, 'resolution' => null])
            ->assertHasFormErrors(['resolution' => 'required']);

        $this->assertSame(RepairTicketStatus::InRepair, $ticket->refresh()->status);
    }

    public function test_an_auditor_sees_repairs_but_cannot_act_on_them(): void
    {
        $ticket = RepairTicket::factory()->create();

        Livewire::actingAs($this->userWithRole('auditor'))
            ->test(ViewRepairTicket::class, ['record' => $ticket->getKey()])
            ->assertActionHidden('verifyRepairTicket')
            ->assertActionHidden('rejectRepairTicket')
            ->assertActionHidden('edit');
    }

    public function test_a_ticket_can_no_longer_be_edited_once_approved(): void
    {
        $ticket = RepairTicket::factory()->approved()->create();

        $this->actingAs($this->userWithRole('super_admin'))
            ->get(RepairTicketResource::getUrl('edit', ['record' => $ticket]))
            ->assertForbidden();
    }

    public function test_the_repair_ticket_page_renders(): void
    {
        $ticket = RepairTicket::factory()->repaired()->create(['resolution' => 'Keyboard cable reseated']);

        $this->actingAs($this->userWithRole('auditor'))
            ->get(RepairTicketResource::getUrl('view', ['record' => $ticket]))
            ->assertSuccessful()
            ->assertSee('Keyboard cable reseated');
    }

    public function test_the_asset_page_lists_its_repairs(): void
    {
        $ticket = RepairTicket::factory()->create(['number' => 'RPR/2609/0042']);

        Livewire::actingAs($this->userWithRole('auditor'))
            ->test(RepairTicketsRelationManager::class, ['ownerRecord' => $ticket->asset, 'pageClass' => ViewAsset::class])
            ->assertSee('RPR/2609/0042');
    }

    public function test_staff_can_report_damage_from_the_asset_page(): void
    {
        $asset = Asset::factory()->create();

        Livewire::actingAs($this->userWithRole('asset_staff'))
            ->test(ViewAsset::class, ['record' => $asset->getRouteKey()])
            ->assertActionVisible('reportDamage');
    }

    public function test_a_disposed_asset_offers_no_damage_report(): void
    {
        $asset = Asset::factory()->status(AssetStatus::Disposed)->create();

        Livewire::actingAs($this->userWithRole('asset_staff'))
            ->test(ViewAsset::class, ['record' => $asset->getRouteKey()])
            ->assertActionHidden('reportDamage');
    }

    private function userWithRole(string $role): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
