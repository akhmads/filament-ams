<?php

namespace Tests\Feature;

use App\Enums\StockDocumentStatus;
use App\Enums\StockDocumentType;
use App\Filament\Resources\StockDocuments\Pages\CreateStockDocument;
use App\Filament\Resources\StockDocuments\Pages\ViewStockDocument;
use App\Filament\Resources\StockDocuments\StockDocumentResource;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\StockDocument;
use App\Models\StockDocumentLine;
use App\Models\StockItem;
use App\Models\User;
use App\Services\StockLedger;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StockDocumentResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_receipt_saved_from_the_form_is_a_numbered_draft_that_changes_no_stock(): void
    {
        $staff = $this->userWithRole('asset_staff');
        $warehouse = Location::factory()->warehouse()->create();
        $item = StockItem::factory()->create();

        Livewire::actingAs($staff)
            ->test(CreateStockDocument::class)
            ->fillForm([
                'type' => StockDocumentType::Receipt->value,
                'document_date' => '2026-09-15',
                'location_id' => $warehouse->id,
                'lines' => [['stock_item_id' => $item->id, 'quantity' => '5', 'unit_cost' => '1200']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $document = StockDocument::query()->sole();
        $this->assertStringStartsWith('RCV/', $document->number);
        $this->assertSame(StockDocumentStatus::Draft, $document->status);
        $this->assertSame($staff->id, $document->created_by);
        $this->assertSame('1200.00', $document->lines()->sole()->unit_cost);
        $this->assertSame(0, StockBalance::query()->count());
    }

    public function test_a_transfer_needs_a_different_destination_on_the_form(): void
    {
        $warehouse = Location::factory()->warehouse()->create();

        Livewire::actingAs($this->userWithRole('asset_staff'))
            ->test(CreateStockDocument::class)
            ->fillForm([
                'type' => StockDocumentType::Transfer->value,
                'location_id' => $warehouse->id,
                'destination_location_id' => $warehouse->id,
                'lines' => [['stock_item_id' => StockItem::factory()->create()->id, 'quantity' => '1']],
            ])
            ->call('create')
            ->assertHasFormErrors(['destination_location_id']);

        $this->assertSame(0, StockDocument::query()->count());
    }

    public function test_staff_post_a_receipt_from_its_page(): void
    {
        $document = StockDocument::factory()->create();
        StockDocumentLine::factory()->for($document)->costing('1000')->create(['quantity' => '5']);

        Livewire::actingAs($this->userWithRole('asset_staff'))
            ->test(ViewStockDocument::class, ['record' => $document->getKey()])
            ->callAction('postStockDocument')
            ->assertNotified('Stock document posted');

        $this->assertSame(StockDocumentStatus::Posted, $document->refresh()->status);
        $this->assertSame('5.00', StockBalance::query()->sole()->quantity);
    }

    public function test_a_posting_that_fails_is_reported_and_leaves_the_draft(): void
    {
        $document = StockDocument::factory()->type(StockDocumentType::Issue)->create();
        StockDocumentLine::factory()->for($document)->create(['quantity' => '5']);

        Livewire::actingAs($this->userWithRole('asset_staff'))
            ->test(ViewStockDocument::class, ['record' => $document->getKey()])
            ->callAction('postStockDocument')
            ->assertNotified('Stock document not posted');

        $this->assertSame(StockDocumentStatus::Draft, $document->refresh()->status);
    }

    public function test_only_a_holder_of_the_adjustment_permission_can_post_a_stock_count(): void
    {
        $count = StockDocument::factory()->type(StockDocumentType::Count)->create();

        Livewire::actingAs($this->userWithRole('asset_staff'))
            ->test(ViewStockDocument::class, ['record' => $count->getKey()])
            ->assertActionHidden('postStockDocument');

        Livewire::actingAs($this->userWithRole('asset_manager'))
            ->test(ViewStockDocument::class, ['record' => $count->getKey()])
            ->assertActionVisible('postStockDocument');
    }

    public function test_a_posted_document_can_be_neither_edited_nor_deleted(): void
    {
        $manager = $this->userWithRole('asset_manager');
        $document = StockDocument::factory()->create();
        StockDocumentLine::factory()->for($document)->costing('100')->create();
        app(StockLedger::class)->post($document);

        $this->actingAs($manager)
            ->get(StockDocumentResource::getUrl('edit', ['record' => $document]))
            ->assertForbidden();

        Livewire::actingAs($manager)
            ->test(ViewStockDocument::class, ['record' => $document->getKey()])
            ->assertActionHidden('delete');
    }

    public function test_the_stock_document_page_renders(): void
    {
        $item = StockItem::factory()->create(['name' => 'Printer toner']);
        $document = StockDocument::factory()->create();
        StockDocumentLine::factory()->for($document)->for($item)->costing('350000')->create();
        app(StockLedger::class)->post($document);

        $this->actingAs($this->userWithRole('auditor'))
            ->get(StockDocumentResource::getUrl('view', ['record' => $document]))
            ->assertSuccessful()
            ->assertSee($document->number);
    }

    private function userWithRole(string $role): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
