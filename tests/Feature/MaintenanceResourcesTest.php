<?php

namespace Tests\Feature;

use App\Enums\MaintenanceIntervalUnit;
use App\Enums\WorkOrderResult;
use App\Enums\WorkOrderStatus;
use App\Filament\Resources\Assets\Pages\ViewAsset;
use App\Filament\Resources\Assets\RelationManagers\WorkOrdersRelationManager;
use App\Filament\Resources\MaintenancePlans\Pages\CreateMaintenancePlan;
use App\Filament\Resources\MaintenancePlans\Pages\EditMaintenancePlan;
use App\Filament\Resources\MaintenancePlans\Pages\ListMaintenancePlans;
use App\Filament\Resources\MaintenancePlans\Schemas\MaintenancePlanForm;
use App\Filament\Resources\WorkOrders\Pages\CreateWorkOrder;
use App\Filament\Resources\WorkOrders\Pages\ViewWorkOrder;
use App\Filament\Resources\WorkOrders\RelationManagers\ChecklistItemsRelationManager;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\MaintenancePlan;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChecklistItem;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MaintenanceResourcesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_plan_for_one_asset_is_created_from_the_form(): void
    {
        $manager = $this->userWithRole('asset_manager');
        $asset = Asset::factory()->create();

        Livewire::actingAs($manager)
            ->test(CreateMaintenancePlan::class)
            ->fillForm([
                'name' => 'Monthly UPS check',
                'target' => MaintenancePlanForm::TARGET_ASSET,
                'asset_id' => $asset->id,
                'interval_value' => 1,
                'interval_unit' => MaintenanceIntervalUnit::Month->value,
                'start_date' => '2026-10-01',
                'lead_days' => 5,
                // A simple repeater holds each item under its field name until it is saved.
                'checklist' => [['task' => 'Check the battery'], ['task' => 'Test the failover']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $plan = MaintenancePlan::query()->sole();
        $this->assertSame($asset->id, $plan->asset_id);
        $this->assertNull($plan->asset_category_id);
        $this->assertSame(MaintenanceIntervalUnit::Month, $plan->interval_unit);
        $this->assertSame(['Check the battery', 'Test the failover'], $plan->checklist);
        $this->assertSame($manager->id, $plan->created_by);
    }

    public function test_a_category_plan_needs_a_category(): void
    {
        Livewire::actingAs($this->userWithRole('asset_manager'))
            ->test(CreateMaintenancePlan::class)
            ->fillForm([
                'name' => 'Yearly vehicle service',
                'target' => MaintenancePlanForm::TARGET_CATEGORY,
                'interval_value' => 1,
                'interval_unit' => MaintenanceIntervalUnit::Year->value,
                'start_date' => '2026-10-01',
                'lead_days' => 14,
            ])
            ->call('create')
            ->assertHasFormErrors(['asset_category_id' => 'required']);
    }

    public function test_switching_a_plan_to_a_category_clears_its_asset(): void
    {
        $plan = MaintenancePlan::factory()->create();
        $category = AssetCategory::factory()->create();

        Livewire::actingAs($this->userWithRole('asset_manager'))
            ->test(EditMaintenancePlan::class, ['record' => $plan->getKey()])
            ->assertSchemaStateSet(['target' => MaintenancePlanForm::TARGET_ASSET])
            ->fillForm([
                'target' => MaintenancePlanForm::TARGET_CATEGORY,
                'asset_category_id' => $category->id,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $plan->refresh();
        $this->assertNull($plan->asset_id);
        $this->assertSame($category->id, $plan->asset_category_id);
        $this->assertSame(['Clean the unit', 'Check for damage'], $plan->checklist);
    }

    public function test_due_work_orders_can_be_opened_from_the_plans_list(): void
    {
        MaintenancePlan::factory()->create(['start_date' => now()->toDateString()]);

        Livewire::actingAs($this->userWithRole('asset_manager'))
            ->test(ListMaintenancePlans::class)
            ->callAction('generateWorkOrders')
            ->assertNotified('1 work order(s) opened');

        $this->assertSame(1, WorkOrder::query()->count());
    }

    public function test_a_work_order_created_by_hand_gets_a_number(): void
    {
        $staff = $this->userWithRole('asset_staff');
        $asset = Asset::factory()->create();

        Livewire::actingAs($staff)
            ->test(CreateWorkOrder::class)
            ->fillForm([
                'asset_id' => $asset->id,
                'title' => 'Replace the projector lamp',
                'due_date' => '2026-09-20',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $workOrder = WorkOrder::query()->sole();
        $this->assertStringStartsWith('WO/', $workOrder->number);
        $this->assertNull($workOrder->maintenance_plan_id);
        $this->assertSame($staff->id, $workOrder->created_by);
        $this->assertSame(WorkOrderStatus::Open, $workOrder->status);
    }

    public function test_staff_start_tick_and_complete_a_work_order(): void
    {
        $staff = $this->userWithRole('asset_staff');
        $workOrder = WorkOrder::factory()->create(['estimated_cost' => 100_000]);
        $item = WorkOrderChecklistItem::factory()->for($workOrder)->create();

        Livewire::actingAs($staff)
            ->test(ViewWorkOrder::class, ['record' => $workOrder->getKey()])
            ->callAction('startWorkOrder')
            ->assertDispatched(ChecklistItemsRelationManager::STATUS_CHANGED_EVENT);

        $this->assertSame(WorkOrderStatus::InProgress, $workOrder->refresh()->status);

        Livewire::actingAs($staff)
            ->test(ChecklistItemsRelationManager::class, ['ownerRecord' => $workOrder, 'pageClass' => ViewWorkOrder::class])
            ->call('updateTableColumnState', 'is_done', (string) $item->getKey(), true);

        $item->refresh();
        $this->assertTrue($item->is_done);
        $this->assertSame($staff->id, $item->done_by);

        Livewire::actingAs($staff)
            ->test(ViewWorkOrder::class, ['record' => $workOrder->getKey()])
            ->callAction('completeWorkOrder', [
                'result' => WorkOrderResult::Ok->value,
                'actual_cost' => '150000',
                'labor_minutes' => 45,
            ])
            ->assertHasNoFormErrors();

        $workOrder->refresh();
        $this->assertSame(WorkOrderStatus::Completed, $workOrder->status);
        $this->assertSame('150000.00', $workOrder->actual_cost);
        $this->assertSame(45, $workOrder->labor_minutes);
        $this->assertSame($staff->id, $workOrder->completed_by);
    }

    public function test_completing_as_ok_with_unticked_tasks_keeps_the_work_in_progress(): void
    {
        $workOrder = WorkOrder::factory()->inProgress()->create();
        WorkOrderChecklistItem::factory()->for($workOrder)->create();

        Livewire::actingAs($this->userWithRole('asset_staff'))
            ->test(ViewWorkOrder::class, ['record' => $workOrder->getKey()])
            ->callAction('completeWorkOrder', ['result' => WorkOrderResult::Ok->value])
            ->assertNotified('Work order not completed');

        $this->assertSame(WorkOrderStatus::InProgress, $workOrder->refresh()->status);
    }

    public function test_follow_up_needs_findings(): void
    {
        $workOrder = WorkOrder::factory()->inProgress()->create();

        Livewire::actingAs($this->userWithRole('asset_staff'))
            ->test(ViewWorkOrder::class, ['record' => $workOrder->getKey()])
            ->callAction('completeWorkOrder', ['result' => WorkOrderResult::NeedsFollowUp->value])
            ->assertHasFormErrors(['findings' => 'required']);

        $this->assertSame(WorkOrderStatus::InProgress, $workOrder->refresh()->status);
    }

    public function test_the_checklist_cannot_be_ticked_before_work_starts(): void
    {
        $workOrder = WorkOrder::factory()->create();
        $item = WorkOrderChecklistItem::factory()->for($workOrder)->create();

        Livewire::actingAs($this->userWithRole('asset_staff'))
            ->test(ChecklistItemsRelationManager::class, ['ownerRecord' => $workOrder, 'pageClass' => ViewWorkOrder::class])
            ->call('updateTableColumnState', 'is_done', (string) $item->getKey(), true);

        $this->assertFalse($item->refresh()->is_done);
    }

    public function test_cancelling_keeps_the_reason(): void
    {
        $workOrder = WorkOrder::factory()->create();

        Livewire::actingAs($this->userWithRole('asset_staff'))
            ->test(ViewWorkOrder::class, ['record' => $workOrder->getKey()])
            ->callAction('cancelWorkOrder', ['reason' => 'Asset replaced'])
            ->assertDispatched(ChecklistItemsRelationManager::STATUS_CHANGED_EVENT);

        $workOrder->refresh();
        $this->assertSame(WorkOrderStatus::Cancelled, $workOrder->status);
        $this->assertSame('Asset replaced', $workOrder->cancellation_reason);
    }

    public function test_an_auditor_sees_maintenance_but_cannot_act_on_it(): void
    {
        $auditor = $this->userWithRole('auditor');
        $workOrder = WorkOrder::factory()->create();

        Livewire::actingAs($auditor)
            ->test(ViewWorkOrder::class, ['record' => $workOrder->getKey()])
            ->assertActionHidden('startWorkOrder')
            ->assertActionHidden('cancelWorkOrder')
            ->assertActionHidden('edit');

        Livewire::actingAs($auditor)
            ->test(ListMaintenancePlans::class)
            ->assertActionHidden('generateWorkOrders');
    }

    public function test_a_work_order_can_no_longer_be_edited_once_started(): void
    {
        $workOrder = WorkOrder::factory()->inProgress()->create();

        $this->actingAs($this->userWithRole('super_admin'))
            ->get(WorkOrderResource::getUrl('edit', ['record' => $workOrder]))
            ->assertForbidden();
    }

    public function test_the_asset_page_lists_its_work_orders(): void
    {
        $workOrder = WorkOrder::factory()->completed()->create(['number' => 'WO/2609/0042']);

        Livewire::actingAs($this->userWithRole('auditor'))
            ->test(WorkOrdersRelationManager::class, ['ownerRecord' => $workOrder->asset, 'pageClass' => ViewAsset::class])
            ->assertSee('WO/2609/0042');
    }

    public function test_the_work_order_page_renders(): void
    {
        $workOrder = WorkOrder::factory()->completed()->create(['findings' => 'All good']);
        WorkOrderChecklistItem::factory()->done()->for($workOrder)->create(['task' => 'Clean the filter']);

        $this->actingAs($this->userWithRole('auditor'))
            ->get(WorkOrderResource::getUrl('view', ['record' => $workOrder]))
            ->assertSuccessful()
            ->assertSee('All good');
    }

    private function userWithRole(string $role): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
