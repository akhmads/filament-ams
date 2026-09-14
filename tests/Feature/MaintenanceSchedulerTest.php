<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\MaintenanceIntervalUnit;
use App\Enums\WorkOrderStatus;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\MaintenancePlan;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\MaintenanceScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaintenanceSchedulerTest extends TestCase
{
    use RefreshDatabase;

    private MaintenanceScheduler $scheduler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scheduler = app(MaintenanceScheduler::class);
    }

    public function test_an_asset_plan_opens_its_first_work_order_on_the_start_date_within_the_lead_time(): void
    {
        $technician = User::factory()->create();
        $plan = MaintenancePlan::factory()->create([
            'asset_id' => $this->asset()->id,
            'name' => 'Quarterly AC service',
            'start_date' => '2026-09-20',
            'lead_days' => 7,
            'assigned_to' => $technician->id,
            'estimated_cost' => 250_000,
            'checklist' => ['Clean the filter', 'Check the refrigerant'],
        ]);

        $opened = $this->scheduler->generate(CarbonImmutable::parse('2026-09-14'));

        $this->assertSame(1, $opened);
        $workOrder = WorkOrder::query()->sole();
        $this->assertSame($plan->id, $workOrder->maintenance_plan_id);
        $this->assertSame('2026-09-20', $workOrder->due_date->toDateString());
        $this->assertSame(WorkOrderStatus::Open, $workOrder->status);
        $this->assertSame('Quarterly AC service', $workOrder->title);
        $this->assertSame($technician->id, $workOrder->assigned_to);
        $this->assertSame('250000.00', $workOrder->estimated_cost);
        $this->assertStringStartsWith('WO/', $workOrder->number);
        $this->assertSame(
            ['Clean the filter', 'Check the refrigerant'],
            $workOrder->checklistItems()->pluck('task')->all(),
        );
    }

    public function test_nothing_opens_before_the_lead_time(): void
    {
        MaintenancePlan::factory()->create([
            'asset_id' => $this->asset()->id,
            'start_date' => '2026-09-30',
            'lead_days' => 7,
        ]);

        $this->assertSame(0, $this->scheduler->generate(CarbonImmutable::parse('2026-09-14')));
        $this->assertSame(0, WorkOrder::query()->count());
    }

    public function test_running_twice_opens_only_one_work_order(): void
    {
        MaintenancePlan::factory()->create(['asset_id' => $this->asset()->id, 'start_date' => '2026-09-14']);

        $this->scheduler->generate(CarbonImmutable::parse('2026-09-14'));
        $this->scheduler->generate(CarbonImmutable::parse('2026-09-14'));

        $this->assertSame(1, WorkOrder::query()->count());
    }

    public function test_the_next_due_date_follows_the_previous_due_date_rather_than_the_completion(): void
    {
        $asset = $this->asset();
        $plan = MaintenancePlan::factory()->every(3, MaintenanceIntervalUnit::Month)->create([
            'asset_id' => $asset->id,
            'start_date' => '2026-03-20',
        ]);
        WorkOrder::factory()->completed()->create([
            'maintenance_plan_id' => $plan->id,
            'asset_id' => $asset->id,
            'due_date' => '2026-06-20',
            'completed_at' => '2026-07-15 10:00:00',
        ]);

        $this->scheduler->generate(CarbonImmutable::parse('2026-09-14'));

        $this->assertSame('2026-09-20', WorkOrder::query()->open()->sole()->due_date->toDateString());
    }

    public function test_missed_occurrences_collapse_into_the_latest_one(): void
    {
        MaintenancePlan::factory()->every(1, MaintenanceIntervalUnit::Month)->create([
            'asset_id' => $this->asset()->id,
            'start_date' => '2026-01-10',
        ]);

        $this->scheduler->generate(CarbonImmutable::parse('2026-09-14'));

        $workOrder = WorkOrder::query()->sole();
        $this->assertSame('2026-09-10', $workOrder->due_date->toDateString());
    }

    public function test_an_asset_acquired_after_the_start_date_is_first_due_one_interval_after_acquisition(): void
    {
        MaintenancePlan::factory()->every(3, MaintenanceIntervalUnit::Month)->create([
            'asset_id' => $this->asset(['acquisition_date' => '2026-07-01'])->id,
            'start_date' => '2026-01-01',
            'lead_days' => 7,
        ]);

        $this->assertSame(0, $this->scheduler->generate(CarbonImmutable::parse('2026-09-14')));
        $this->assertSame(1, $this->scheduler->generate(CarbonImmutable::parse('2026-09-25')));
        $this->assertSame('2026-10-01', WorkOrder::query()->sole()->due_date->toDateString());
    }

    public function test_an_open_work_order_blocks_a_new_one_even_when_it_is_overdue(): void
    {
        $asset = $this->asset();
        $plan = MaintenancePlan::factory()->every(1, MaintenanceIntervalUnit::Month)->create([
            'asset_id' => $asset->id,
            'start_date' => '2026-06-20',
        ]);
        WorkOrder::factory()->inProgress()->create([
            'maintenance_plan_id' => $plan->id,
            'asset_id' => $asset->id,
            'due_date' => '2026-06-20',
        ]);

        $this->assertSame(0, $this->scheduler->generate(CarbonImmutable::parse('2026-09-14')));
    }

    public function test_a_deleted_work_order_does_not_reopen_its_due_date(): void
    {
        MaintenancePlan::factory()->create(['asset_id' => $this->asset()->id, 'start_date' => '2026-09-14']);
        $this->scheduler->generate(CarbonImmutable::parse('2026-09-14'));

        WorkOrder::query()->sole()->delete();

        $this->assertSame(0, $this->scheduler->generate(CarbonImmutable::parse('2026-09-14')));
    }

    public function test_a_category_plan_covers_assets_in_use_across_its_subcategories(): void
    {
        $electronics = AssetCategory::factory()->create();
        $laptops = AssetCategory::factory()->create(['parent_id' => $electronics->id]);
        $furniture = AssetCategory::factory()->create();

        $inParent = $this->asset(['asset_category_id' => $electronics->id]);
        $inChild = $this->asset(['asset_category_id' => $laptops->id, 'status' => AssetStatus::InUse]);
        $this->asset(['asset_category_id' => $laptops->id, 'status' => AssetStatus::Disposed]);
        $this->asset(['asset_category_id' => $electronics->id, 'status' => AssetStatus::Retired]);
        $this->asset(['asset_category_id' => $furniture->id]);

        MaintenancePlan::factory()->forCategory($electronics)->create(['start_date' => '2026-09-14']);

        $this->assertSame(2, $this->scheduler->generate(CarbonImmutable::parse('2026-09-14')));
        $this->assertEqualsCanonicalizing(
            [$inParent->id, $inChild->id],
            WorkOrder::query()->pluck('asset_id')->all(),
        );
    }

    public function test_a_category_plan_without_subcategories_covers_only_its_own_assets(): void
    {
        $electronics = AssetCategory::factory()->create();
        $laptops = AssetCategory::factory()->create(['parent_id' => $electronics->id]);
        $inParent = $this->asset(['asset_category_id' => $electronics->id]);
        $this->asset(['asset_category_id' => $laptops->id]);

        MaintenancePlan::factory()->forCategory($electronics)->create([
            'start_date' => '2026-09-14',
            'include_subcategories' => false,
        ]);

        $this->scheduler->generate(CarbonImmutable::parse('2026-09-14'));

        $this->assertSame([$inParent->id], WorkOrder::query()->pluck('asset_id')->all());
    }

    public function test_inactive_plans_are_skipped(): void
    {
        MaintenancePlan::factory()->inactive()->create(['asset_id' => $this->asset()->id, 'start_date' => '2026-09-14']);

        $this->assertSame(0, $this->scheduler->generate(CarbonImmutable::parse('2026-09-14')));
    }

    public function test_the_command_opens_work_orders_for_the_given_date(): void
    {
        MaintenancePlan::factory()->create(['asset_id' => $this->asset()->id, 'start_date' => '2026-09-20']);

        $this->artisan('maintenance:generate-work-orders', ['--date' => '2026-09-14'])
            ->expectsOutputToContain('1 work order(s) opened for 14 Sep 2026.')
            ->assertSuccessful();

        $this->assertSame(1, WorkOrder::query()->count());
    }

    public function test_the_command_rejects_a_malformed_date(): void
    {
        $this->artisan('maintenance:generate-work-orders', ['--date' => '14-09-2026'])
            ->assertExitCode(2);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function asset(array $attributes = []): Asset
    {
        return Asset::factory()->create([
            'acquisition_date' => '2025-01-01',
            'status' => AssetStatus::Available,
            ...$attributes,
        ]);
    }
}
