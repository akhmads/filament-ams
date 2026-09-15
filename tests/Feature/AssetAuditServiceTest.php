<?php

namespace Tests\Feature;

use App\Enums\AssetAuditStatus;
use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\AuditFollowUp;
use App\Enums\AuditResult;
use App\Enums\LocationType;
use App\Enums\MovementType;
use App\Enums\PlacementType;
use App\Exceptions\AssetAuditException;
use App\Models\Asset;
use App\Models\AssetAudit;
use App\Models\AssetAuditLine;
use App\Models\AssetMovement;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\User;
use App\Services\AssetAuditService;
use App\Services\AssetMovementRecorder;
use App\Support\Placement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetAuditServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_audit_lists_assets_in_service_in_the_location_and_every_room_under_it(): void
    {
        $building = Location::factory()->create(['type' => LocationType::Building]);
        $meetingRoom = $this->roomIn($building);
        $storeRoom = $this->roomIn($building);
        $elsewhere = Location::factory()->create();

        $inMeetingRoom = $this->assetIn($meetingRoom);
        $inStoreRoom = $this->assetIn($storeRoom);
        $this->assetIn($elsewhere);
        $this->assetIn($meetingRoom)->forceFill(['status' => AssetStatus::Disposed])->save();

        $audit = AssetAudit::factory()->create(['location_id' => $building->id]);

        $this->assertSame(2, $this->service()->listExpectedAssets($audit));
        $this->assertEqualsCanonicalizing([$inMeetingRoom->id, $inStoreRoom->id], $audit->lines()->pluck('asset_id')->all());
        $this->assertSame($meetingRoom->id, $audit->lines()->where('asset_id', $inMeetingRoom->id)->value('expected_location_id'));
    }

    public function test_a_department_audit_includes_assets_held_by_its_employees(): void
    {
        $finance = Department::factory()->create();
        $held = Asset::factory()->heldBy(Employee::factory()->create())->create(['department_id' => $finance->id]);
        Asset::factory()->create(['department_id' => Department::factory()->create()->id]);

        $audit = AssetAudit::factory()->create(['department_id' => $finance->id]);
        $this->service()->listExpectedAssets($audit);

        $this->assertSame([$held->id], $audit->lines()->pluck('asset_id')->all());
    }

    public function test_an_asset_scanned_where_it_is_recorded_is_found(): void
    {
        $room = Location::factory()->create();
        $asset = $this->assetIn($room);
        $audit = $this->auditOf($room);
        $scanner = User::factory()->create();

        $line = $this->service()->scan($audit, $asset->code, $room, $scanner);

        $this->assertSame(AuditResult::Found, $line->result);
        $this->assertSame($scanner->id, $line->scanned_by);
        $this->assertSame(AssetCondition::Good, $line->observed_condition);
        $this->assertFalse($line->hasConditionChanged());
    }

    public function test_an_asset_scanned_in_another_room_is_in_the_wrong_location(): void
    {
        $building = Location::factory()->create(['type' => LocationType::Building]);
        $recordedIn = $this->roomIn($building);
        $foundIn = $this->roomIn($building);
        $asset = $this->assetIn($recordedIn);
        $audit = $this->auditOf($building);

        $line = $this->service()->scan($audit, $asset->code, $foundIn, User::factory()->create(), AssetCondition::MinorDamage);

        $this->assertSame(AuditResult::Misplaced, $line->result);
        $this->assertSame($foundIn->id, $line->scanned_location_id);
        $this->assertTrue($line->hasConditionChanged());
    }

    public function test_an_asset_not_on_the_list_is_added_when_scanned(): void
    {
        $room = Location::factory()->create();
        $audit = $this->auditOf($room);
        $stranger = $this->assetIn(Location::factory()->create());

        $line = $this->service()->scan($audit, $stranger->code, $room, User::factory()->create());

        $this->assertFalse($line->is_expected);
        $this->assertSame(AuditResult::Misplaced, $line->result);
    }

    public function test_an_unknown_code_is_not_recorded(): void
    {
        $room = Location::factory()->create();
        $audit = $this->auditOf($room);

        $this->expectException(AssetAuditException::class);

        $this->service()->scan($audit, 'NOPE-0001', $room, User::factory()->create());
    }

    public function test_nothing_can_be_scanned_once_counting_has_finished(): void
    {
        $room = Location::factory()->create();
        $asset = $this->assetIn($room);
        $audit = AssetAudit::factory()->underReview()->create(['location_id' => $room->id]);

        $this->expectException(AssetAuditException::class);

        $this->service()->scan($audit, $asset->code, $room, User::factory()->create());
    }

    public function test_finishing_the_count_marks_unscanned_assets_missing_and_proposes_corrections(): void
    {
        $building = Location::factory()->create(['type' => LocationType::Building]);
        $recordedIn = $this->roomIn($building);
        $foundIn = $this->roomIn($building);
        $unseen = $this->assetIn($recordedIn);
        $moved = $this->assetIn($recordedIn);
        $audit = $this->auditOf($building);
        $this->service()->scan($audit, $moved->code, $foundIn, User::factory()->create(), AssetCondition::Broken);

        $this->service()->finishCounting($audit, User::factory()->create());

        $this->assertSame(AssetAuditStatus::UnderReview, $audit->refresh()->status);

        $missing = $this->lineFor($audit, $unseen);
        $this->assertSame(AuditResult::Missing, $missing->result);
        $this->assertFalse($missing->mark_lost);

        $misplaced = $this->lineFor($audit, $moved);
        $this->assertTrue($misplaced->apply_relocation);
        $this->assertTrue($misplaced->apply_condition);
    }

    public function test_closing_moves_misplaced_assets_and_updates_their_condition_through_the_ledger(): void
    {
        $building = Location::factory()->create(['type' => LocationType::Building]);
        $recordedIn = $this->roomIn($building);
        $foundIn = $this->roomIn($building);
        $asset = $this->assetIn($recordedIn);
        $audit = $this->auditOf($building);
        $this->service()->scan($audit, $asset->code, $foundIn, User::factory()->create(), AssetCondition::MinorDamage);
        $this->service()->finishCounting($audit, User::factory()->create());

        $this->service()->close($audit, User::factory()->create());

        $asset->refresh();
        $this->assertSame($foundIn->id, $asset->current_location_id);
        $this->assertSame(AssetCondition::MinorDamage, $asset->condition);
        $this->assertSame(AssetStatus::InUse, $asset->status);

        $line = $this->lineFor($audit, $asset);
        $movement = AssetMovement::query()->whereKey($line->adjustment_movement_id)->sole();
        $this->assertSame(MovementType::AuditAdjustment, $movement->movement_type);
        $this->assertTrue($movement->reference->is($audit));
        $this->assertSame(AssetAuditStatus::Completed, $audit->refresh()->status);
    }

    public function test_a_missing_asset_marked_lost_becomes_lost(): void
    {
        $room = Location::factory()->create();
        $asset = $this->assetIn($room);
        $audit = $this->auditOf($room);
        $this->service()->finishCounting($audit, User::factory()->create());

        $this->service()->setFollowUp($this->lineFor($audit, $asset), AuditFollowUp::MarkLost, true);
        $this->service()->close($audit, User::factory()->create());

        $this->assertSame(AssetStatus::Lost, $asset->refresh()->status);
    }

    public function test_a_finding_whose_correction_is_cleared_changes_nothing(): void
    {
        $building = Location::factory()->create(['type' => LocationType::Building]);
        $recordedIn = $this->roomIn($building);
        $asset = $this->assetIn($recordedIn);
        $audit = $this->auditOf($building);
        $this->service()->scan($audit, $asset->code, $this->roomIn($building), User::factory()->create());
        $this->service()->finishCounting($audit, User::factory()->create());

        $this->service()->setFollowUp($this->lineFor($audit, $asset), AuditFollowUp::Relocate, false);
        $this->service()->close($audit, User::factory()->create());

        $this->assertSame($recordedIn->id, $asset->refresh()->current_location_id);
        $this->assertSame(0, AssetMovement::query()->where('asset_id', $asset->id)->count());
    }

    public function test_an_asset_moved_since_it_was_scanned_is_not_corrected(): void
    {
        $building = Location::factory()->create(['type' => LocationType::Building]);
        $asset = $this->assetIn($this->roomIn($building));
        $audit = $this->auditOf($building);
        $this->service()->scan($audit, $asset->code, $this->roomIn($building), User::factory()->create());
        $this->service()->finishCounting($audit, User::factory()->create());

        $this->travel(5)->minutes();
        $newHome = $this->roomIn($building);
        app(AssetMovementRecorder::class)->record($asset, Placement::toLocation($newHome), MovementType::Transfer);

        try {
            $this->service()->close($audit, User::factory()->create());
            $this->fail('A correction based on a stale scan should not be applied.');
        } catch (AssetAuditException $exception) {
            $this->assertStringContainsString($asset->code, $exception->getMessage());
        }

        $this->assertSame(AssetAuditStatus::UnderReview, $audit->refresh()->status);
        $this->assertSame($newHome->id, $asset->refresh()->current_location_id);
    }

    public function test_a_correction_that_does_not_fit_the_finding_is_refused(): void
    {
        $room = Location::factory()->create();
        $asset = $this->assetIn($room);
        $audit = $this->auditOf($room);
        $this->service()->scan($audit, $asset->code, $room, User::factory()->create());
        $this->service()->finishCounting($audit, User::factory()->create());

        $this->expectException(AssetAuditException::class);

        $this->service()->setFollowUp($this->lineFor($audit, $asset), AuditFollowUp::MarkLost, true);
    }

    private function roomIn(Location $parent): Location
    {
        return Location::factory()->create(['branch_id' => $parent->branch_id, 'parent_id' => $parent->id]);
    }

    private function assetIn(Location $room): Asset
    {
        return Asset::factory()->create([
            'branch_id' => $room->branch_id,
            'placement_type' => PlacementType::Location,
            'current_location_id' => $room->id,
            'status' => AssetStatus::InUse,
            'condition' => AssetCondition::Good,
        ]);
    }

    private function auditOf(Location $location): AssetAudit
    {
        $audit = AssetAudit::factory()->create(['location_id' => $location->id]);
        $this->service()->listExpectedAssets($audit);

        return $audit;
    }

    private function lineFor(AssetAudit $audit, Asset $asset): AssetAuditLine
    {
        return $audit->lines()->where('asset_id', $asset->id)->sole();
    }

    private function service(): AssetAuditService
    {
        return app(AssetAuditService::class);
    }
}
