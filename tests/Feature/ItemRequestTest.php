<?php

namespace Tests\Feature;

use App\Enums\ItemRequestStatus;
use App\Enums\StockDocumentStatus;
use App\Enums\StockDocumentType;
use App\Exceptions\ItemRequestException;
use App\Filament\Resources\ItemRequests\ItemRequestResource;
use App\Filament\Resources\ItemRequests\Pages\CreateItemRequest;
use App\Filament\Resources\ItemRequests\Pages\ViewItemRequest;
use App\Models\Employee;
use App\Models\ItemRequest;
use App\Models\ItemRequestLine;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\StockDocument;
use App\Models\StockDocumentLine;
use App\Models\StockItem;
use App\Models\User;
use App\Notifications\ItemRequestDecided;
use App\Notifications\ItemRequestSubmitted;
use App\Services\ItemRequestService;
use App\Services\StockLedger;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class ItemRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_request_recorded_by_staff_is_numbered_and_sent_to_approvers(): void
    {
        Notification::fake();
        $staff = $this->userWithRole('asset_staff');
        $manager = $this->userWithRole('asset_manager');
        $employee = Employee::factory()->create();
        $item = StockItem::factory()->create();

        Livewire::actingAs($staff)
            ->test(CreateItemRequest::class)
            ->fillForm([
                'employee_id' => $employee->id,
                'request_date' => '2026-09-15',
                'lines' => [['stock_item_id' => $item->id, 'quantity' => '2']],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $request = ItemRequest::query()->sole();
        $this->assertStringStartsWith('REQ/', $request->number);
        $this->assertSame(ItemRequestStatus::Submitted, $request->status);
        $this->assertSame($staff->id, $request->created_by);
        $this->assertSame('2.00', $request->lines()->sole()->quantity);

        Notification::assertSentTo($manager, ItemRequestSubmitted::class);
    }

    public function test_staff_cannot_approve_a_request_but_a_manager_can(): void
    {
        $request = ItemRequest::factory()->create();
        ItemRequestLine::factory()->for($request)->create();

        Livewire::actingAs($this->userWithRole('asset_staff'))
            ->test(ViewItemRequest::class, ['record' => $request->getKey()])
            ->assertActionHidden('approveItemRequest')
            ->assertActionHidden('rejectItemRequest');

        Livewire::actingAs($this->userWithRole('asset_manager'))
            ->test(ViewItemRequest::class, ['record' => $request->getKey()])
            ->callAction('approveItemRequest');

        $this->assertSame(ItemRequestStatus::Approved, $request->refresh()->status);
    }

    public function test_whoever_recorded_the_request_hears_the_decision(): void
    {
        Notification::fake();
        $recorder = User::factory()->create();
        $request = ItemRequest::factory()->create(['created_by' => $recorder->id]);
        ItemRequestLine::factory()->for($request)->create();

        $this->service()->approve($request, User::factory()->create());

        Notification::assertSentTo($recorder, ItemRequestDecided::class);
    }

    public function test_rejecting_needs_a_reason_on_the_form(): void
    {
        $request = ItemRequest::factory()->create();

        Livewire::actingAs($this->userWithRole('asset_manager'))
            ->test(ViewItemRequest::class, ['record' => $request->getKey()])
            ->callAction('rejectItemRequest', ['reason' => null])
            ->assertHasFormErrors(['reason' => 'required']);

        $this->assertSame(ItemRequestStatus::Submitted, $request->refresh()->status);
    }

    public function test_only_a_request_waiting_for_approval_can_be_rejected(): void
    {
        $request = ItemRequest::factory()->approved()->create();

        $this->expectException(ItemRequestException::class);

        $this->service()->reject($request, 'Too late', User::factory()->create());
    }

    public function test_issuing_an_approved_request_posts_a_goods_issue_to_the_employee(): void
    {
        $warehouse = Location::factory()->warehouse()->create();
        $item = StockItem::factory()->create();
        $this->stock($warehouse, $item, '10');
        $request = ItemRequest::factory()->approved()->create();
        ItemRequestLine::factory()->for($request)->for($item)->create(['quantity' => '3']);

        Livewire::actingAs($this->userWithRole('asset_staff'))
            ->test(ViewItemRequest::class, ['record' => $request->getKey()])
            ->callAction('issueItemRequest', ['location_id' => $warehouse->id])
            ->assertNotified('Items issued');

        $request->refresh();
        $this->assertSame(ItemRequestStatus::Fulfilled, $request->status);

        $issue = $request->stockDocument;
        $this->assertSame(StockDocumentType::Issue, $issue->type);
        $this->assertSame(StockDocumentStatus::Posted, $issue->status);
        $this->assertSame($request->employee_id, $issue->employee_id);
        $this->assertTrue($issue->source->is($request));
        $this->assertSame('7.00', StockBalance::query()->where('stock_item_id', $item->id)->value('quantity'));
    }

    public function test_a_request_the_warehouse_cannot_cover_is_not_issued_at_all(): void
    {
        $warehouse = Location::factory()->warehouse()->create();
        $covered = StockItem::factory()->create();
        $this->stock($warehouse, $covered, '10');
        $request = ItemRequest::factory()->approved()->create();
        ItemRequestLine::factory()->for($request)->for($covered)->create(['quantity' => '1']);
        ItemRequestLine::factory()->for($request)->create(['quantity' => '1']);

        try {
            $this->service()->fulfill($request, $warehouse, User::factory()->create());
            $this->fail('A request the warehouse cannot cover should not be issued.');
        } catch (ItemRequestException) {
            // Expected.
        }

        $this->assertSame(ItemRequestStatus::Approved, $request->refresh()->status);
        $this->assertSame(0, StockDocument::query()->where('type', StockDocumentType::Issue->value)->count());
        $this->assertSame('10.00', StockBalance::query()->where('stock_item_id', $covered->id)->value('quantity'));
    }

    public function test_a_request_can_no_longer_be_edited_once_approved(): void
    {
        $request = ItemRequest::factory()->approved()->create();

        $this->actingAs($this->userWithRole('super_admin'))
            ->get(ItemRequestResource::getUrl('edit', ['record' => $request]))
            ->assertForbidden();
    }

    public function test_the_item_request_page_renders(): void
    {
        $request = ItemRequest::factory()->rejected()->create(['rejection_reason' => 'Budget frozen this month']);
        ItemRequestLine::factory()->for($request)->create();

        $this->actingAs($this->userWithRole('auditor'))
            ->get(ItemRequestResource::getUrl('view', ['record' => $request]))
            ->assertSuccessful()
            ->assertSee('Budget frozen this month');
    }

    private function stock(Location $warehouse, StockItem $item, string $quantity): void
    {
        $receipt = StockDocument::factory()->at($warehouse)->create();
        StockDocumentLine::factory()->for($receipt)->for($item)->costing('100')->create(['quantity' => $quantity]);

        app(StockLedger::class)->post($receipt);
    }

    private function service(): ItemRequestService
    {
        return app(ItemRequestService::class);
    }

    private function userWithRole(string $role): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
