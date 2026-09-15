<?php

namespace Tests\Feature;

use App\Enums\StockDocumentStatus;
use App\Enums\StockDocumentType;
use App\Enums\StockMovementType;
use App\Exceptions\StockException;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\StockDocument;
use App\Models\StockDocumentLine;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\User;
use App\Notifications\StockBelowMinimum;
use App\Services\StockLedger;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StockLedgerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_receipt_brings_stock_in_at_its_purchase_price(): void
    {
        $warehouse = Location::factory()->warehouse()->create();
        $item = StockItem::factory()->create();
        $keeper = User::factory()->create();

        $document = StockDocument::factory()->at($warehouse)->create();
        $line = StockDocumentLine::factory()->for($document)->for($item)->costing('1500')->create(['quantity' => '10']);

        $this->ledger()->post($document, $keeper);

        $this->assertBalance($item, $warehouse, '10.00', '15000.00');

        $document->refresh();
        $this->assertSame(StockDocumentStatus::Posted, $document->status);
        $this->assertSame($keeper->id, $document->posted_by);
        $this->assertSame('15000.00', $line->refresh()->posted_value);

        $movement = StockMovement::query()->sole();
        $this->assertSame(StockMovementType::Receipt, $movement->movement_type);
        $this->assertSame('10.00', $movement->quantity);
        $this->assertSame('15000.00', $movement->balance_value);
    }

    public function test_goods_leave_at_the_moving_average_cost(): void
    {
        $warehouse = Location::factory()->warehouse()->create();
        $item = StockItem::factory()->create();
        $this->receive($warehouse, $item, '10', '1000');
        $this->receive($warehouse, $item, '10', '1300');

        $issue = $this->issue($warehouse, $item, '5');

        $this->assertSame('5750.00', $issue->lines()->sole()->posted_value);
        $this->assertBalance($item, $warehouse, '15.00', '17250.00');
    }

    public function test_the_last_units_out_take_whatever_value_remains(): void
    {
        $warehouse = Location::factory()->warehouse()->create();
        $item = StockItem::factory()->create();
        $this->receive($warehouse, $item, '3', '333.33');

        $this->assertSame('333.33', $this->issue($warehouse, $item, '1')->lines()->sole()->posted_value);
        $this->assertSame('666.66', $this->issue($warehouse, $item, '2')->lines()->sole()->posted_value);

        $this->assertBalance($item, $warehouse, '0.00', '0.00');
    }

    public function test_issuing_more_than_is_on_hand_posts_none_of_the_document(): void
    {
        $warehouse = Location::factory()->warehouse()->create();
        $stocked = StockItem::factory()->create();
        $empty = StockItem::factory()->create(['code' => 'STK-EMPTY']);
        $this->receive($warehouse, $stocked, '2', '100');

        $document = StockDocument::factory()->type(StockDocumentType::Issue)->at($warehouse)->create();
        StockDocumentLine::factory()->for($document)->for($stocked)->create(['quantity' => '1']);
        StockDocumentLine::factory()->for($document)->for($empty)->create(['quantity' => '5']);

        try {
            $this->ledger()->post($document);
            $this->fail('An issue larger than the stock on hand should not post.');
        } catch (StockException $exception) {
            $this->assertStringContainsString('STK-EMPTY', $exception->getMessage());
        }

        $this->assertSame(StockDocumentStatus::Draft, $document->refresh()->status);
        $this->assertBalance($stocked, $warehouse, '2.00', '200.00');
        $this->assertSame(1, StockMovement::query()->count());
    }

    public function test_a_transfer_moves_quantity_and_value_between_warehouses(): void
    {
        $from = Location::factory()->warehouse()->create();
        $to = Location::factory()->warehouse()->create();
        $item = StockItem::factory()->create();
        $this->receive($from, $item, '4', '2500');

        $transfer = StockDocument::factory()->at($from)->transferTo($to)->create();
        StockDocumentLine::factory()->for($transfer)->for($item)->create(['quantity' => '1']);
        $this->ledger()->post($transfer);

        $this->assertBalance($item, $from, '3.00', '7500.00');
        $this->assertBalance($item, $to, '1.00', '2500.00');
        $this->assertSame(
            [StockMovementType::TransferOut, StockMovementType::TransferIn],
            $transfer->movements()->orderBy('id')->get()->pluck('movement_type')->all(),
        );
    }

    public function test_a_transfer_into_the_warehouse_it_comes_from_is_rejected(): void
    {
        $warehouse = Location::factory()->warehouse()->create();
        $transfer = StockDocument::factory()->at($warehouse)->transferTo($warehouse)->create();
        StockDocumentLine::factory()->for($transfer)->create();

        $this->expectException(StockException::class);

        $this->ledger()->post($transfer);
    }

    public function test_stock_can_only_be_kept_in_a_warehouse(): void
    {
        $room = Location::factory()->create();
        $document = StockDocument::factory()->at($room)->create();
        StockDocumentLine::factory()->for($document)->costing('100')->create();

        $this->expectException(StockException::class);

        $this->ledger()->post($document);
    }

    public function test_a_receipt_needs_a_unit_cost(): void
    {
        $document = StockDocument::factory()->create();
        StockDocumentLine::factory()->for($document)->create(['unit_cost' => null]);

        $this->expectException(StockException::class);

        $this->ledger()->post($document);
    }

    public function test_a_posted_document_cannot_be_posted_again(): void
    {
        $warehouse = Location::factory()->warehouse()->create();
        $receipt = $this->receive($warehouse, StockItem::factory()->create(), '1', '100');

        $this->expectException(StockException::class);

        $this->ledger()->post($receipt);
    }

    public function test_a_count_posts_the_difference_from_the_stock_on_hand_when_posted(): void
    {
        $warehouse = Location::factory()->warehouse()->create();
        $item = StockItem::factory()->create();
        $count = StockDocument::factory()->type(StockDocumentType::Count)->at($warehouse)->create();
        $line = StockDocumentLine::factory()->for($count)->for($item)->create(['quantity' => '7']);

        // Stock arrives after the count was entered but before it is posted.
        $this->receive($warehouse, $item, '10', '100');
        $this->ledger()->post($count);

        $this->assertBalance($item, $warehouse, '7.00', '700.00');

        $line->refresh();
        $this->assertSame('10.00', $line->system_quantity);
        $this->assertSame('-300.00', $line->posted_value);
        $this->assertSame('-3.00', $count->movements()->sole()->quantity);
    }

    public function test_a_count_that_matches_the_stock_records_no_movement(): void
    {
        $warehouse = Location::factory()->warehouse()->create();
        $item = StockItem::factory()->create();
        $this->receive($warehouse, $item, '10', '100');

        $count = StockDocument::factory()->type(StockDocumentType::Count)->at($warehouse)->create();
        StockDocumentLine::factory()->for($count)->for($item)->create(['quantity' => '10']);
        $this->ledger()->post($count);

        $this->assertSame(0, $count->movements()->count());
        $this->assertSame(StockDocumentStatus::Posted, $count->refresh()->status);
    }

    public function test_stock_written_up_is_valued_at_the_average_cost(): void
    {
        $warehouse = Location::factory()->warehouse()->create();
        $item = StockItem::factory()->create();
        $this->receive($warehouse, $item, '4', '250');

        $adjustment = StockDocument::factory()->type(StockDocumentType::Adjustment)->at($warehouse)->create();
        StockDocumentLine::factory()->for($adjustment)->for($item)->create(['quantity' => '2']);
        $this->ledger()->post($adjustment);

        $this->assertBalance($item, $warehouse, '6.00', '1500.00');
    }

    public function test_stock_found_in_an_empty_warehouse_takes_the_last_purchase_price(): void
    {
        $main = Location::factory()->warehouse()->create();
        $branch = Location::factory()->warehouse()->create();
        $item = StockItem::factory()->create();
        $this->receive($main, $item, '1', '800');

        $count = StockDocument::factory()->type(StockDocumentType::Count)->at($branch)->create();
        StockDocumentLine::factory()->for($count)->for($item)->create(['quantity' => '2']);
        $this->ledger()->post($count);

        $this->assertBalance($item, $branch, '2.00', '1600.00');
    }

    public function test_stock_keepers_are_told_once_when_an_item_falls_below_its_minimum(): void
    {
        Notification::fake();
        $this->seed(RoleSeeder::class);
        $keeper = User::factory()->create()->assignRole('asset_staff');
        $auditor = User::factory()->create()->assignRole('auditor');
        $warehouse = Location::factory()->warehouse()->create();
        $item = StockItem::factory()->minimum('5')->create();

        $this->receive($warehouse, $item, '6', '100');
        $this->issue($warehouse, $item, '2');
        $this->issue($warehouse, $item, '1');

        Notification::assertSentToTimes($keeper, StockBelowMinimum::class, 1);
        Notification::assertNotSentTo($auditor, StockBelowMinimum::class);
    }

    private function receive(Location $warehouse, StockItem $item, string $quantity, string $unitCost): StockDocument
    {
        $document = StockDocument::factory()->at($warehouse)->create();
        StockDocumentLine::factory()->for($document)->for($item)->costing($unitCost)->create(['quantity' => $quantity]);

        return $this->ledger()->post($document);
    }

    private function issue(Location $warehouse, StockItem $item, string $quantity): StockDocument
    {
        $document = StockDocument::factory()->type(StockDocumentType::Issue)->at($warehouse)->create();
        StockDocumentLine::factory()->for($document)->for($item)->create(['quantity' => $quantity]);

        return $this->ledger()->post($document);
    }

    private function assertBalance(StockItem $item, Location $warehouse, string $quantity, string $value): void
    {
        $balance = StockBalance::query()->where('stock_item_id', $item->id)->where('location_id', $warehouse->id)->sole();

        $this->assertSame([$quantity, $value], [$balance->quantity, $balance->total_value]);
    }

    private function ledger(): StockLedger
    {
        return app(StockLedger::class);
    }
}
