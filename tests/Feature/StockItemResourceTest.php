<?php

namespace Tests\Feature;

use App\Enums\StockItemType;
use App\Filament\Resources\StockItems\Pages\CreateStockItem;
use App\Filament\Resources\StockItems\Pages\ListStockItems;
use App\Filament\Resources\StockItems\Pages\ViewStockItem;
use App\Filament\Resources\StockItems\RelationManagers\BalancesRelationManager;
use App\Filament\Resources\StockItems\RelationManagers\MovementsRelationManager;
use App\Filament\Resources\StockItems\StockItemResource;
use App\Filament\Widgets\LowStockTable;
use App\Models\Location;
use App\Models\StockDocument;
use App\Models\StockDocumentLine;
use App\Models\StockItem;
use App\Models\User;
use App\Services\StockLedger;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StockItemResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_stock_item_is_created_from_the_form(): void
    {
        Livewire::actingAs($this->userWithRole('asset_staff'))
            ->test(CreateStockItem::class)
            ->fillForm([
                'code' => 'OIL-10W40',
                'name' => 'Engine oil 10W-40',
                'item_type' => StockItemType::SparePart->value,
                'unit' => 'litre',
                'minimum_quantity' => '12.5',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $item = StockItem::query()->sole();
        $this->assertSame(StockItemType::SparePart, $item->item_type);
        $this->assertSame('12.50', $item->minimum_quantity);
    }

    public function test_a_minimum_with_more_than_two_decimals_is_rejected(): void
    {
        Livewire::actingAs($this->userWithRole('asset_staff'))
            ->test(CreateStockItem::class)
            ->fillForm([
                'code' => 'CBL-UTP',
                'name' => 'UTP cable',
                'unit' => 'metre',
                'minimum_quantity' => '1.255',
            ])
            ->call('create')
            ->assertHasFormErrors(['minimum_quantity']);
    }

    public function test_the_below_minimum_tab_lists_only_items_that_are_short(): void
    {
        $warehouse = Location::factory()->warehouse()->create();
        $short = StockItem::factory()->minimum('5')->create();
        $stocked = StockItem::factory()->minimum('5')->create();
        $withoutMinimum = StockItem::factory()->create();
        $this->stock($warehouse, $short, '2');
        $this->stock($warehouse, $stocked, '5');

        Livewire::actingAs($this->userWithRole('auditor'))
            ->test(ListStockItems::class)
            ->set('activeTab', 'below_minimum')
            ->assertCanSeeTableRecords([$short])
            ->assertCanNotSeeTableRecords([$stocked, $withoutMinimum]);
    }

    public function test_the_dashboard_suggests_what_to_buy_for_short_items(): void
    {
        $warehouse = Location::factory()->warehouse()->create();
        $short = StockItem::factory()->minimum('10')->create(['unit' => 'ream']);
        $this->stock($warehouse, $short, '4');

        Livewire::actingAs($this->userWithRole('asset_staff'))
            ->test(LowStockTable::class)
            ->assertCanSeeTableRecords([$short])
            ->assertSee('6 ream');
    }

    public function test_the_stock_item_page_shows_each_warehouse_and_the_stock_card(): void
    {
        $auditor = $this->userWithRole('auditor');
        $warehouse = Location::factory()->warehouse()->create(['name' => 'Gudang Sparepart']);
        $item = StockItem::factory()->create();
        $receipt = $this->stock($warehouse, $item, '3');

        $this->actingAs($auditor)
            ->get(StockItemResource::getUrl('view', ['record' => $item]))
            ->assertSuccessful();

        Livewire::actingAs($auditor)
            ->test(BalancesRelationManager::class, ['ownerRecord' => $item, 'pageClass' => ViewStockItem::class])
            ->assertSee('Gudang Sparepart');

        Livewire::actingAs($auditor)
            ->test(MovementsRelationManager::class, ['ownerRecord' => $item, 'pageClass' => ViewStockItem::class])
            ->assertSee($receipt->number);
    }

    private function stock(Location $warehouse, StockItem $item, string $quantity): StockDocument
    {
        $receipt = StockDocument::factory()->at($warehouse)->create();
        StockDocumentLine::factory()->for($receipt)->for($item)->costing('100')->create(['quantity' => $quantity]);

        return app(StockLedger::class)->post($receipt);
    }

    private function userWithRole(string $role): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
