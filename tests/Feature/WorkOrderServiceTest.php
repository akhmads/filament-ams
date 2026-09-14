<?php

namespace Tests\Feature;

use App\Enums\WorkOrderResult;
use App\Enums\WorkOrderStatus;
use App\Exceptions\WorkOrderException;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderChecklistItem;
use App\Services\WorkOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkOrderServiceTest extends TestCase
{
    use RefreshDatabase;

    private WorkOrderService $service;

    private User $technician;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(WorkOrderService::class);
        $this->technician = User::factory()->create();
    }

    public function test_starting_an_open_work_order_puts_it_in_progress(): void
    {
        $workOrder = WorkOrder::factory()->create();

        $this->service->start($workOrder);

        $workOrder->refresh();
        $this->assertSame(WorkOrderStatus::InProgress, $workOrder->status);
        $this->assertNotNull($workOrder->started_at);
    }

    public function test_only_an_open_work_order_can_be_started(): void
    {
        $workOrder = WorkOrder::factory()->completed()->create();

        $this->expectException(WorkOrderException::class);

        $this->service->start($workOrder);
    }

    public function test_ticking_a_checklist_item_records_who_and_when_and_unticking_clears_it(): void
    {
        $item = WorkOrderChecklistItem::factory()->for(WorkOrder::factory()->inProgress())->create();

        $this->service->markChecklistItem($item, true, $this->technician);

        $item->refresh();
        $this->assertTrue($item->is_done);
        $this->assertSame($this->technician->id, $item->done_by);
        $this->assertNotNull($item->done_at);

        $this->service->markChecklistItem($item, false, $this->technician);

        $item->refresh();
        $this->assertFalse($item->is_done);
        $this->assertNull($item->done_by);
        $this->assertNull($item->done_at);
    }

    public function test_the_checklist_cannot_be_ticked_before_work_starts(): void
    {
        $item = WorkOrderChecklistItem::factory()->for(WorkOrder::factory())->create();

        $this->expectException(WorkOrderException::class);

        $this->service->markChecklistItem($item, true, $this->technician);
    }

    public function test_completing_as_ok_records_the_result_cost_and_time(): void
    {
        $workOrder = WorkOrder::factory()->inProgress()->create();
        WorkOrderChecklistItem::factory()->done()->for($workOrder)->create();

        $this->service->complete($workOrder, WorkOrderResult::Ok, $this->technician, '175000.50', 90, 'Filter replaced');

        $workOrder->refresh();
        $this->assertSame(WorkOrderStatus::Completed, $workOrder->status);
        $this->assertSame(WorkOrderResult::Ok, $workOrder->result);
        $this->assertSame($this->technician->id, $workOrder->completed_by);
        $this->assertSame('175000.50', $workOrder->actual_cost);
        $this->assertSame(90, $workOrder->labor_minutes);
        $this->assertSame('Filter replaced', $workOrder->findings);
        $this->assertNotNull($workOrder->completed_at);
    }

    public function test_an_ok_result_needs_every_checklist_item_ticked(): void
    {
        $workOrder = WorkOrder::factory()->inProgress()->create();
        WorkOrderChecklistItem::factory()->done()->for($workOrder)->create();
        WorkOrderChecklistItem::factory()->for($workOrder)->create(['sort_order' => 2]);

        try {
            $this->service->complete($workOrder, WorkOrderResult::Ok, $this->technician);
            $this->fail('Completing as OK with an unticked item should fail.');
        } catch (WorkOrderException $exception) {
            $this->assertStringContainsString('1 unticked checklist item', $exception->getMessage());
        }

        $this->assertSame(WorkOrderStatus::InProgress, $workOrder->refresh()->status);
    }

    public function test_unfinished_work_can_be_completed_as_needing_follow_up(): void
    {
        $workOrder = WorkOrder::factory()->inProgress()->create();
        WorkOrderChecklistItem::factory()->for($workOrder)->create();

        $this->service->complete($workOrder, WorkOrderResult::NeedsFollowUp, $this->technician, findings: 'Spare part not in stock');

        $workOrder->refresh();
        $this->assertSame(WorkOrderStatus::Completed, $workOrder->status);
        $this->assertSame(WorkOrderResult::NeedsFollowUp, $workOrder->result);
    }

    public function test_a_work_order_that_has_not_started_cannot_be_completed(): void
    {
        $workOrder = WorkOrder::factory()->create();

        $this->expectException(WorkOrderException::class);

        $this->service->complete($workOrder, WorkOrderResult::Ok, $this->technician);
    }

    public function test_open_and_in_progress_work_orders_can_be_cancelled_with_a_reason(): void
    {
        $open = WorkOrder::factory()->create();
        $inProgress = WorkOrder::factory()->inProgress()->create();

        $this->service->cancel($open, 'Asset sent back to the vendor');
        $this->service->cancel($inProgress, 'Duplicate');

        $this->assertSame(WorkOrderStatus::Cancelled, $open->refresh()->status);
        $this->assertSame('Asset sent back to the vendor', $open->cancellation_reason);
        $this->assertNotNull($open->cancelled_at);
        $this->assertSame(WorkOrderStatus::Cancelled, $inProgress->refresh()->status);
    }

    public function test_a_completed_work_order_cannot_be_cancelled(): void
    {
        $workOrder = WorkOrder::factory()->completed()->create();

        $this->expectException(WorkOrderException::class);

        $this->service->cancel($workOrder, 'Too late');
    }
}
