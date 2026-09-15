<?php

namespace Tests\Feature;

use App\Enums\StockDocumentStatus;
use App\Enums\StockDocumentType;
use App\Exceptions\StockException;
use App\Filament\Resources\RepairTickets\Pages\ViewRepairTicket;
use App\Filament\Resources\WorkOrders\RelationManagers\SparePartsRelationManager;
use App\Models\Location;
use App\Models\RepairTicket;
use App\Models\StockBalance;
use App\Models\StockDocument;
use App\Models\StockDocumentLine;
use App\Models\StockItem;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\StockDocumentService;
use App\Services\StockLedger;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SparePartsTest extends TestCase
{
    use RefreshDatabase;

    public function test_parts_used_on_a_work_order_leave_stock_and_count_towards_its_cost(): void
    {
        $warehouse = Location::factory()->warehouse()->create();
        $filter = StockItem::factory()->sparePart()->create();
        $this->stock($warehouse, $filter, '5', '1000');
        $workOrder = WorkOrder::factory()->inProgress()->create();

        $issue = $this->service()->issueSpareParts($workOrder, $warehouse, [['stock_item_id' => $filter->id, 'quantity' => '2']], User::factory()->create());

        $this->assertSame(StockDocumentType::Issue, $issue->type);
        $this->assertSame(StockDocumentStatus::Posted, $issue->status);
        $this->assertTrue($issue->source->is($workOrder));
        $this->assertSame('3.00', StockBalance::query()->where('stock_item_id', $filter->id)->value('quantity'));
        $this->assertSame('2000.00', $workOrder->sparePartsCost());
    }

    public function test_parts_cannot_be_used_before_the_work_starts(): void
    {
        $warehouse = Location::factory()->warehouse()->create();
        $filter = StockItem::factory()->sparePart()->create();
        $this->stock($warehouse, $filter, '5', '1000');
        $workOrder = WorkOrder::factory()->create();

        try {
            $this->service()->issueSpareParts($workOrder, $warehouse, [['stock_item_id' => $filter->id, 'quantity' => '1']], User::factory()->create());
            $this->fail('Parts should not be issued to work that has not started.');
        } catch (StockException) {
            // Expected.
        }

        $this->assertSame(0, StockDocument::query()->where('type', StockDocumentType::Issue->value)->count());
    }

    public function test_staff_record_parts_used_on_a_repair_from_its_page(): void
    {
        $warehouse = Location::factory()->warehouse()->create();
        $fan = StockItem::factory()->sparePart()->create();
        $this->stock($warehouse, $fan, '2', '75000');
        $ticket = RepairTicket::factory()->inRepair()->create();

        Livewire::actingAs($this->userWithRole('asset_staff'))
            ->test(SparePartsRelationManager::class, ['ownerRecord' => $ticket, 'pageClass' => ViewRepairTicket::class])
            ->callTableAction('useSpareParts', data: [
                'location_id' => $warehouse->id,
                'parts' => [['stock_item_id' => $fan->id, 'quantity' => '1']],
            ])
            ->assertHasNoTableActionErrors()
            ->assertNotified('Spare parts recorded');

        $this->assertSame('75000.00', $ticket->sparePartsCost());
    }

    public function test_an_auditor_sees_the_parts_used_but_cannot_take_any(): void
    {
        $warehouse = Location::factory()->warehouse()->create();
        $fan = StockItem::factory()->sparePart()->create();
        $this->stock($warehouse, $fan, '2', '75000');
        $ticket = RepairTicket::factory()->inRepair()->create();
        $issue = $this->service()->issueSpareParts($ticket, $warehouse, [['stock_item_id' => $fan->id, 'quantity' => '1']], User::factory()->create());

        Livewire::actingAs($this->userWithRole('auditor'))
            ->test(SparePartsRelationManager::class, ['ownerRecord' => $ticket, 'pageClass' => ViewRepairTicket::class])
            ->assertSee($issue->number)
            ->assertTableActionHidden('useSpareParts');
    }

    private function stock(Location $warehouse, StockItem $item, string $quantity, string $unitCost): void
    {
        $receipt = StockDocument::factory()->at($warehouse)->create();
        StockDocumentLine::factory()->for($receipt)->for($item)->costing($unitCost)->create(['quantity' => $quantity]);

        app(StockLedger::class)->post($receipt);
    }

    private function service(): StockDocumentService
    {
        return app(StockDocumentService::class);
    }

    private function userWithRole(string $role): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
