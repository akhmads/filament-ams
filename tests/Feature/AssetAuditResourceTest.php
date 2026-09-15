<?php

namespace Tests\Feature;

use App\Enums\AssetAuditStatus;
use App\Enums\AssetStatus;
use App\Enums\AuditResult;
use App\Enums\LocationType;
use App\Enums\PlacementType;
use App\Filament\Resources\AssetAudits\AssetAuditResource;
use App\Filament\Resources\AssetAudits\Pages\CreateAssetAudit;
use App\Filament\Resources\AssetAudits\Pages\ScanAssetAudit;
use App\Filament\Resources\AssetAudits\Pages\ViewAssetAudit;
use App\Filament\Resources\AssetAudits\RelationManagers\LinesRelationManager;
use App\Filament\Resources\Assets\AssetResource;
use App\Models\Asset;
use App\Models\AssetAudit;
use App\Models\AssetAuditLine;
use App\Models\Location;
use App\Models\User;
use App\Services\AssetAuditService;
use App\Services\AuditDocumentGenerator;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssetAuditResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_audit_started_from_the_form_lists_the_assets_it_expects(): void
    {
        $staff = $this->userWithRole('asset_staff');
        $room = Location::factory()->create();
        $this->assetIn($room);

        Livewire::actingAs($staff)
            ->test(CreateAssetAudit::class)
            ->fillForm(['title' => 'Year-end count', 'location_id' => $room->id])
            ->call('create')
            ->assertHasNoFormErrors();

        $audit = AssetAudit::query()->sole();
        $this->assertStringStartsWith('AUD/', $audit->number);
        $this->assertSame(AssetAuditStatus::InProgress, $audit->status);
        $this->assertSame($staff->id, $audit->created_by);
        $this->assertSame(1, $audit->lines()->count());
    }

    public function test_an_audit_needs_a_location_or_a_department(): void
    {
        Livewire::actingAs($this->userWithRole('asset_staff'))
            ->test(CreateAssetAudit::class)
            ->fillForm(['title' => 'Everything, everywhere'])
            ->call('create')
            ->assertHasFormErrors(['location_id', 'department_id']);
    }

    public function test_an_auditor_records_a_scanned_code_on_the_scan_page(): void
    {
        $room = Location::factory()->create();
        $asset = $this->assetIn($room);
        $audit = $this->auditOf($room);

        Livewire::actingAs($this->userWithRole('auditor'))
            ->test(ScanAssetAudit::class, ['record' => $audit->getKey()])
            ->fillForm(['location_id' => $room->id, 'code' => $asset->code])
            ->call('scan')
            ->assertHasNoFormErrors()
            ->assertNotified("{$asset->code} recorded")
            ->assertSchemaStateSet(['code' => null]);

        $this->assertSame(AuditResult::Found, $audit->lines()->sole()->result);
    }

    public function test_opening_a_label_qr_while_counting_a_room_records_the_asset(): void
    {
        $room = Location::factory()->create();
        $asset = $this->assetIn($room);
        $audit = $this->auditOf($room);

        $this->actingAs($this->userWithRole('asset_staff'))
            ->withSession([ScanAssetAudit::SESSION_KEY => ['audit' => $audit->id, 'location' => $room->id]])
            ->get(route('asset.lookup', ['code' => $asset->code]))
            ->assertRedirect(AssetAuditResource::getUrl('scan', ['record' => $audit]));

        $line = $audit->lines()->sole();
        $this->assertNotNull($line->scanned_at);
        $this->assertSame($room->id, $line->scanned_location_id);
    }

    public function test_a_label_qr_opens_the_asset_page_once_the_audit_stops_counting(): void
    {
        $room = Location::factory()->create();
        $asset = $this->assetIn($room);
        $audit = AssetAudit::factory()->underReview()->create(['location_id' => $room->id]);

        $this->actingAs($this->userWithRole('asset_staff'))
            ->withSession([ScanAssetAudit::SESSION_KEY => ['audit' => $audit->id, 'location' => $room->id]])
            ->get(route('asset.lookup', ['code' => $asset->code]))
            ->assertRedirect(AssetResource::getUrl('view', ['record' => $asset]));
    }

    public function test_the_scan_page_is_closed_once_counting_has_finished(): void
    {
        $audit = AssetAudit::factory()->underReview()->create(['location_id' => Location::factory()->create()->id]);

        $this->actingAs($this->userWithRole('asset_staff'))
            ->get(AssetAuditResource::getUrl('scan', ['record' => $audit]))
            ->assertForbidden();
    }

    public function test_staff_finish_counting_from_the_audit_page(): void
    {
        $room = Location::factory()->create();
        $this->assetIn($room);
        $audit = $this->auditOf($room);

        Livewire::actingAs($this->userWithRole('asset_staff'))
            ->test(ViewAssetAudit::class, ['record' => $audit->getKey()])
            ->callAction('finishCounting')
            ->assertDispatched(LinesRelationManager::STATUS_CHANGED_EVENT);

        $this->assertSame(AssetAuditStatus::UnderReview, $audit->refresh()->status);
        $this->assertSame(AuditResult::Missing, $audit->lines()->sole()->result);
    }

    public function test_only_a_manager_chooses_corrections_and_closes_the_audit(): void
    {
        $staff = $this->userWithRole('asset_staff');
        $manager = $this->userWithRole('asset_manager');
        $building = Location::factory()->create(['type' => LocationType::Building]);
        $recordedIn = Location::factory()->create(['branch_id' => $building->branch_id, 'parent_id' => $building->id]);
        $foundIn = Location::factory()->create(['branch_id' => $building->branch_id, 'parent_id' => $building->id]);
        $asset = $this->assetIn($recordedIn);
        $audit = AssetAudit::factory()->underReview()->create(['location_id' => $building->id]);
        $line = AssetAuditLine::factory()->for($audit)->for($asset)->misplaced($recordedIn, $foundIn)->create(['apply_relocation' => true]);

        Livewire::actingAs($staff)
            ->test(ViewAssetAudit::class, ['record' => $audit->getKey()])
            ->assertActionHidden('closeAssetAudit');

        Livewire::actingAs($staff)
            ->test(LinesRelationManager::class, ['ownerRecord' => $audit, 'pageClass' => ViewAssetAudit::class])
            ->call('updateTableColumnState', 'apply_relocation', (string) $line->getKey(), false);

        $this->assertTrue($line->refresh()->apply_relocation);

        Livewire::actingAs($manager)
            ->test(ViewAssetAudit::class, ['record' => $audit->getKey()])
            ->callAction('closeAssetAudit')
            ->assertNotified('Audit closed');

        $this->assertSame(AssetAuditStatus::Completed, $audit->refresh()->status);
        $this->assertSame($foundIn->id, $asset->refresh()->current_location_id);
    }

    public function test_the_audit_record_prints_as_a_pdf_named_after_its_number(): void
    {
        $room = Location::factory()->create();
        $this->assetIn($room);
        $audit = $this->auditOf($room);
        $audit->forceFill(['number' => 'AUD/2609/0003'])->save();

        $generator = app(AuditDocumentGenerator::class);

        $this->assertStringStartsWith('%PDF-', $generator->render($audit));
        $this->assertSame('AUD-2609-0003.pdf', $generator->filename($audit));
    }

    public function test_the_audit_page_renders_for_an_auditor(): void
    {
        $room = Location::factory()->create();
        $this->assetIn($room);
        $audit = $this->auditOf($room);
        $audit->forceFill(['title' => 'Head office year-end count'])->save();

        $this->actingAs($this->userWithRole('auditor'))
            ->get(AssetAuditResource::getUrl('view', ['record' => $audit]))
            ->assertSuccessful()
            ->assertSee('Head office year-end count');
    }

    private function assetIn(Location $room): Asset
    {
        return Asset::factory()->create([
            'branch_id' => $room->branch_id,
            'placement_type' => PlacementType::Location,
            'current_location_id' => $room->id,
            'status' => AssetStatus::InUse,
        ]);
    }

    private function auditOf(Location $location): AssetAudit
    {
        $audit = AssetAudit::factory()->create(['location_id' => $location->id]);
        app(AssetAuditService::class)->listExpectedAssets($audit);

        return $audit;
    }

    private function userWithRole(string $role): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
