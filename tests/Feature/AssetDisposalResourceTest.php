<?php

namespace Tests\Feature;

use App\Enums\AssetDisposalStatus;
use App\Enums\AssetStatus;
use App\Enums\DisposalMethod;
use App\Filament\Resources\AssetDisposals\AssetDisposalResource;
use App\Filament\Resources\AssetDisposals\Pages\CreateAssetDisposal;
use App\Filament\Resources\AssetDisposals\Pages\ViewAssetDisposal;
use App\Filament\Resources\Assets\Pages\ViewAsset;
use App\Filament\Resources\RepairTickets\Pages\ViewRepairTicket;
use App\Models\Asset;
use App\Models\AssetDisposal;
use App\Models\AssetDisposalLine;
use App\Models\Employee;
use App\Models\Location;
use App\Models\RepairTicket;
use App\Models\User;
use App\Notifications\AssetDisposalProposed;
use App\Services\DisposalDocumentGenerator;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class AssetDisposalResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_proposal_from_the_form_is_numbered_and_sent_to_approvers(): void
    {
        Notification::fake();
        $staff = $this->userWithRole('asset_staff');
        $manager = $this->userWithRole('asset_manager');
        $asset = $this->retiredAsset();

        Livewire::actingAs($staff)
            ->test(CreateAssetDisposal::class)
            ->fillForm([
                'disposal_date' => '2026-09-15',
                'reason' => 'Obsolete office equipment auction',
                'lines' => [['asset_id' => $asset->id, 'method' => DisposalMethod::Sale->value, 'proceeds' => '750000']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $disposal = AssetDisposal::query()->sole();
        $this->assertStringStartsWith('DSP/', $disposal->number);
        $this->assertSame(AssetDisposalStatus::Proposed, $disposal->status);
        $this->assertSame($staff->id, $disposal->created_by);
        $this->assertSame('750000.00', $disposal->lines()->sole()->proceeds);

        Notification::assertSentTo($manager, AssetDisposalProposed::class);
    }

    public function test_proposing_from_a_repair_that_could_not_be_done_carries_the_asset_and_reason_over(): void
    {
        $asset = $this->retiredAsset();
        $ticket = RepairTicket::factory()->for($asset)->create([
            'status' => 'unrepairable',
            'resolution' => 'Spare parts are no longer made',
        ]);

        Livewire::withQueryParams(['ticket' => (string) $ticket->id])
            ->actingAs($this->userWithRole('asset_staff'))
            ->test(CreateAssetDisposal::class)
            ->call('create')
            ->assertHasNoFormErrors();

        $disposal = AssetDisposal::query()->sole();
        $this->assertStringContainsString('Spare parts are no longer made', $disposal->reason);

        $line = $disposal->lines()->sole();
        $this->assertSame($asset->id, $line->asset_id);
        $this->assertSame($ticket->id, $line->repair_ticket_id);
        $this->assertSame(DisposalMethod::Scrapped, $line->method);
    }

    public function test_the_repair_page_offers_a_disposal_proposal_only_once_the_asset_cannot_be_repaired(): void
    {
        $staff = $this->userWithRole('asset_staff');
        $unrepairable = RepairTicket::factory()->for($this->retiredAsset())->create(['status' => 'unrepairable']);
        $inRepair = RepairTicket::factory()->inRepair()->create();

        Livewire::actingAs($staff)
            ->test(ViewRepairTicket::class, ['record' => $unrepairable->getKey()])
            ->assertActionVisible('proposeDisposal');

        Livewire::actingAs($staff)
            ->test(ViewRepairTicket::class, ['record' => $inRepair->getKey()])
            ->assertActionHidden('proposeDisposal');
    }

    public function test_an_asset_held_by_an_employee_offers_no_disposal_proposal(): void
    {
        $staff = $this->userWithRole('asset_staff');
        $held = Asset::factory()->heldBy(Employee::factory()->create())->create();

        Livewire::actingAs($staff)
            ->test(ViewAsset::class, ['record' => $this->retiredAsset()->getRouteKey()])
            ->assertActionVisible('proposeDisposal');

        Livewire::actingAs($staff)
            ->test(ViewAsset::class, ['record' => $held->getRouteKey()])
            ->assertActionHidden('proposeDisposal');
    }

    public function test_staff_propose_and_complete_a_disposal_that_a_manager_approves(): void
    {
        $staff = $this->userWithRole('asset_staff');
        $manager = $this->userWithRole('asset_manager');
        $asset = $this->retiredAsset();
        $disposal = AssetDisposal::factory()->create(['created_by' => $staff->id]);
        AssetDisposalLine::factory()->for($disposal)->for($asset)->sold('1000000')->create();

        Livewire::actingAs($staff)
            ->test(ViewAssetDisposal::class, ['record' => $disposal->getKey()])
            ->assertActionHidden('approveAssetDisposal')
            ->assertActionHidden('rejectAssetDisposal');

        Livewire::actingAs($manager)
            ->test(ViewAssetDisposal::class, ['record' => $disposal->getKey()])
            ->callAction('approveAssetDisposal');

        $this->assertSame(AssetDisposalStatus::Approved, $disposal->refresh()->status);

        Livewire::actingAs($staff)
            ->test(ViewAssetDisposal::class, ['record' => $disposal->getKey()])
            ->callAction('completeAssetDisposal')
            ->assertNotified('Disposal completed');

        $this->assertSame(AssetDisposalStatus::Completed, $disposal->refresh()->status);
        $this->assertSame(AssetStatus::Disposed, $asset->refresh()->status);
    }

    public function test_a_completion_that_fails_is_reported_and_leaves_the_disposal_approved(): void
    {
        // Depreciated, but no depreciation has been posted.
        $asset = $this->retiredAsset(['is_depreciable' => true, 'acquisition_date' => now()->subYear()]);
        $disposal = AssetDisposal::factory()->approved()->create();
        AssetDisposalLine::factory()->for($disposal)->for($asset)->create();

        Livewire::actingAs($this->userWithRole('asset_staff'))
            ->test(ViewAssetDisposal::class, ['record' => $disposal->getKey()])
            ->callAction('completeAssetDisposal')
            ->assertNotified('Disposal not completed');

        $this->assertSame(AssetDisposalStatus::Approved, $disposal->refresh()->status);
        $this->assertSame(AssetStatus::Retired, $asset->refresh()->status);
    }

    public function test_rejecting_needs_a_reason_on_the_form(): void
    {
        $disposal = AssetDisposal::factory()->create();

        Livewire::actingAs($this->userWithRole('asset_manager'))
            ->test(ViewAssetDisposal::class, ['record' => $disposal->getKey()])
            ->callAction('rejectAssetDisposal', ['reason' => null])
            ->assertHasFormErrors(['reason' => 'required']);

        $this->assertSame(AssetDisposalStatus::Proposed, $disposal->refresh()->status);
    }

    public function test_a_disposal_can_no_longer_be_edited_once_approved(): void
    {
        $disposal = AssetDisposal::factory()->approved()->create();

        $this->actingAs($this->userWithRole('super_admin'))
            ->get(AssetDisposalResource::getUrl('edit', ['record' => $disposal]))
            ->assertForbidden();
    }

    public function test_the_disposal_record_prints_as_a_pdf_named_after_its_number(): void
    {
        $disposal = AssetDisposal::factory()->create(['number' => 'DSP/2609/0007']);
        AssetDisposalLine::factory()->for($disposal)->for($this->retiredAsset())->sold('500000')->create();

        $generator = app(DisposalDocumentGenerator::class);

        $this->assertStringStartsWith('%PDF-', $generator->render($disposal));
        $this->assertSame('DSP-2609-0007.pdf', $generator->filename($disposal));
    }

    public function test_the_disposal_page_renders_for_an_auditor(): void
    {
        $disposal = AssetDisposal::factory()->completed()->create(['reason' => 'Flood damage beyond repair']);
        AssetDisposalLine::factory()->for($disposal)->for($this->retiredAsset())->create([
            'commercial_book_value' => '250000',
            'commercial_gain_loss' => '-250000',
        ]);

        $this->actingAs($this->userWithRole('auditor'))
            ->get(AssetDisposalResource::getUrl('view', ['record' => $disposal]))
            ->assertSuccessful()
            ->assertSee('Flood damage beyond repair');
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function retiredAsset(array $attributes = []): Asset
    {
        return Asset::factory()
            ->inWarehouse(Location::factory()->warehouse()->create())
            ->status(AssetStatus::Retired)
            ->create(['is_depreciable' => false, 'acquisition_cost' => 3_000_000, ...$attributes]);
    }

    private function userWithRole(string $role): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
