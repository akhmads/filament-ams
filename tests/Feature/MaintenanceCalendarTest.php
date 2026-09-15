<?php

namespace Tests\Feature;

use App\Enums\CalendarEntryKind;
use App\Enums\MaintenanceIntervalUnit;
use App\Filament\Pages\MaintenanceCalendar;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\MaintenancePlan;
use App\Models\RepairTicket;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\MaintenanceCalendarFeed;
use App\Support\CalendarEntry;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MaintenanceCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_work_orders_and_repairs_appear_on_their_day_within_the_month(): void
    {
        $this->travelTo('2026-09-15 09:00:00');
        WorkOrder::factory()->create(['due_date' => '2026-09-20', 'title' => 'Service the generator']);
        WorkOrder::factory()->create(['due_date' => '2026-09-30', 'title' => 'Last day of the month']);
        WorkOrder::factory()->cancelled()->create(['due_date' => '2026-09-21']);
        WorkOrder::factory()->create(['due_date' => '2026-10-01']);
        RepairTicket::factory()->create(['reported_at' => '2026-09-03 10:00:00', 'title' => 'Printer jams']);
        RepairTicket::factory()->rejected()->create(['reported_at' => '2026-09-04 10:00:00']);

        $entries = $this->feed('2026-09-01');

        $this->assertSame(['2026-09-03', '2026-09-20', '2026-09-30'], array_keys($entries));
        $this->assertSame(CalendarEntryKind::Repair, $entries['2026-09-03'][0]->kind);
        $this->assertSame('Printer jams', $entries['2026-09-03'][0]->title);
        $this->assertSame('Service the generator', $entries['2026-09-20'][0]->title);
    }

    public function test_an_open_work_order_past_its_due_date_is_shown_as_overdue(): void
    {
        $this->travelTo('2026-09-15 09:00:00');
        WorkOrder::factory()->create(['due_date' => '2026-09-10']);
        WorkOrder::factory()->completed()->create(['due_date' => '2026-09-11']);

        $entries = $this->feed('2026-09-01');

        $this->assertSame(['danger', 'Overdue'], [$entries['2026-09-10'][0]->color, $entries['2026-09-10'][0]->statusLabel]);
        $this->assertSame(['success', 'Completed'], [$entries['2026-09-11'][0]->color, $entries['2026-09-11'][0]->statusLabel]);
    }

    public function test_planned_maintenance_shows_the_dates_after_the_latest_work_order(): void
    {
        $this->travelTo('2026-09-15 09:00:00');
        $plan = MaintenancePlan::factory()->every(1, MaintenanceIntervalUnit::Month)->create(['start_date' => '2026-08-10', 'name' => 'Monthly UPS check']);
        WorkOrder::factory()->create(['maintenance_plan_id' => $plan->id, 'asset_id' => $plan->asset_id, 'due_date' => '2026-09-10']);

        $september = $this->feed('2026-09-01');
        $october = $this->feed('2026-10-01');

        $this->assertSame([CalendarEntryKind::WorkOrder], $this->kindsOn($september['2026-09-10']));
        $this->assertSame(['2026-10-10'], array_keys($october));
        $this->assertSame(CalendarEntryKind::Planned, $october['2026-10-10'][0]->kind);
        $this->assertSame('Monthly UPS check', $october['2026-10-10'][0]->title);
    }

    public function test_a_category_plan_groups_its_assets_into_one_entry_per_day(): void
    {
        $this->travelTo('2026-09-15 09:00:00');
        $category = AssetCategory::factory()->create();
        Asset::factory()->count(2)->for($category, 'category')->create();
        MaintenancePlan::factory()->forCategory($category)->every(1, MaintenanceIntervalUnit::Month)->create(['start_date' => '2026-10-05']);

        $entries = $this->feed('2026-10-01');

        $this->assertSame(['2026-10-05'], array_keys($entries));
        $this->assertCount(1, $entries['2026-10-05']);
        $this->assertSame('2 assets', $entries['2026-10-05'][0]->detail);
    }

    public function test_planned_maintenance_is_not_projected_into_the_past(): void
    {
        $this->travelTo('2026-09-15 09:00:00');
        MaintenancePlan::factory()->every(1, MaintenanceIntervalUnit::Week)->create(['start_date' => '2026-01-05']);

        $this->assertSame([], $this->feed('2026-08-01'));
    }

    public function test_the_calendar_page_shows_the_requested_month(): void
    {
        $this->travelTo('2026-09-15 09:00:00');
        WorkOrder::factory()->create(['due_date' => '2026-10-07', 'title' => 'Replace the air filter']);

        $this->actingAs($this->userWithRole('asset_staff'))
            ->get(MaintenanceCalendar::getUrl(['month' => '2026-10']))
            ->assertSuccessful()
            ->assertSee('October 2026')
            ->assertSee('Replace the air filter');
    }

    public function test_the_next_month_button_moves_the_calendar_forward(): void
    {
        $this->travelTo('2026-09-15 09:00:00');

        Livewire::actingAs($this->userWithRole('asset_staff'))
            ->test(MaintenanceCalendar::class)
            ->callAction('nextMonth')
            ->assertSet('month', '2026-10')
            ->assertSee('October 2026');
    }

    public function test_a_malformed_month_falls_back_to_the_current_month(): void
    {
        $this->travelTo('2026-09-15 09:00:00');

        Livewire::withQueryParams(['month' => '2026-13'])
            ->actingAs($this->userWithRole('asset_staff'))
            ->test(MaintenanceCalendar::class)
            ->assertSee('September 2026');
    }

    public function test_a_user_without_work_order_access_cannot_open_the_calendar(): void
    {
        $this->seed(RoleSeeder::class);
        $user = User::factory()->create(['is_active' => true]);

        $this->actingAs($user)
            ->get(MaintenanceCalendar::getUrl())
            ->assertForbidden();
    }

    /**
     * @return array<string, list<CalendarEntry>>
     */
    private function feed(string $month): array
    {
        return app(MaintenanceCalendarFeed::class)->forMonth(CarbonImmutable::parse($month), CarbonImmutable::today(), CalendarEntryKind::cases());
    }

    /**
     * @param  list<CalendarEntry>  $entries
     * @return list<CalendarEntryKind>
     */
    private function kindsOn(array $entries): array
    {
        return array_map(fn (CalendarEntry $entry): CalendarEntryKind => $entry->kind, $entries);
    }

    private function userWithRole(string $role): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
