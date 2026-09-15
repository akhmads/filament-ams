<?php

namespace Tests\Feature;

use App\Enums\RepairType;
use App\Filament\Resources\WorkOrders\Pages\EditWorkOrder;
use App\Models\Asset;
use App\Models\MaintenancePlan;
use App\Models\RepairTicket;
use App\Models\User;
use App\Models\WorkOrder;
use App\Notifications\MaintenanceReminder;
use App\Notifications\RepairTicketAwaitingApproval;
use App\Notifications\RepairTicketReported;
use App\Notifications\WorkOrderAssigned;
use App\Services\MaintenanceNotifier;
use App\Services\MaintenanceScheduler;
use App\Services\RepairTicketService;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class MaintenanceNotifierTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_technician_is_told_when_a_plan_opens_a_work_order_for_them(): void
    {
        $technician = User::factory()->create(['is_active' => true]);
        MaintenancePlan::factory()->create(['assigned_to' => $technician->id, 'start_date' => today()->toDateString()]);
        Notification::fake();

        app(MaintenanceScheduler::class)->generate(CarbonImmutable::today());

        Notification::assertSentTo($technician, WorkOrderAssigned::class);
    }

    public function test_reassigning_a_work_order_tells_the_new_technician(): void
    {
        $staff = $this->userWithRole('asset_staff');
        $technician = User::factory()->create(['is_active' => true]);
        $workOrder = WorkOrder::factory()->create();
        Notification::fake();

        Livewire::actingAs($staff)
            ->test(EditWorkOrder::class, ['record' => $workOrder->getKey()])
            ->fillForm(['assigned_to' => $technician->id])
            ->call('save')
            ->assertHasNoFormErrors();

        Notification::assertSentTo($technician, WorkOrderAssigned::class);
    }

    public function test_assigning_a_work_order_to_yourself_sends_nothing(): void
    {
        $staff = $this->userWithRole('asset_staff');
        $workOrder = WorkOrder::factory()->create();
        Notification::fake();

        Livewire::actingAs($staff)
            ->test(EditWorkOrder::class, ['record' => $workOrder->getKey()])
            ->fillForm(['assigned_to' => $staff->id])
            ->call('save')
            ->assertHasNoFormErrors();

        Notification::assertNothingSentTo($staff);
    }

    public function test_an_inactive_technician_is_not_notified(): void
    {
        $technician = User::factory()->create(['is_active' => false]);
        $workOrder = WorkOrder::factory()->create(['assigned_to' => $technician->id]);
        Notification::fake();

        app(MaintenanceNotifier::class)->workOrderAssigned($workOrder);

        Notification::assertNothingSentTo($technician);
    }

    public function test_reported_damage_reaches_repair_staff_but_not_the_reporter_or_auditors(): void
    {
        $reporter = $this->userWithRole('asset_staff');
        $colleague = $this->userWithRole('asset_staff');
        $manager = $this->userWithRole('asset_manager');
        $auditor = $this->userWithRole('auditor');
        $asset = Asset::factory()->create();
        Notification::fake();

        app(RepairTicketService::class)->report($asset, ['title' => 'Cracked casing'], $reporter);

        Notification::assertSentTo([$colleague, $manager], RepairTicketReported::class);
        Notification::assertNotSentTo([$reporter, $auditor], RepairTicketReported::class);
    }

    public function test_a_verified_repair_waits_on_managers_only(): void
    {
        $staff = $this->userWithRole('asset_staff');
        $colleague = $this->userWithRole('asset_staff');
        $manager = $this->userWithRole('asset_manager');
        $ticket = RepairTicket::factory()->create();
        Notification::fake();

        app(RepairTicketService::class)->verify($ticket, RepairType::Internal, $staff);

        Notification::assertSentTo($manager, RepairTicketAwaitingApproval::class);
        Notification::assertNotSentTo([$staff, $colleague], RepairTicketAwaitingApproval::class);
    }

    public function test_the_daily_reminder_counts_each_technicians_overdue_work_and_work_due_today(): void
    {
        $this->travelTo('2026-09-15 07:00:00');
        $technician = User::factory()->create(['is_active' => true]);
        $assigned = WorkOrder::factory()->state(['assigned_to' => $technician->id]);
        $assigned->create(['due_date' => '2026-09-10']);
        $assigned->create(['due_date' => '2026-09-14']);
        $assigned->create(['due_date' => '2026-09-15']);
        $assigned->create(['due_date' => '2026-09-16']);
        $assigned->completed()->create(['due_date' => '2026-09-01']);
        Notification::fake();

        $this->artisan('maintenance:send-reminders')->assertSuccessful();

        Notification::assertSentTo(
            $technician,
            MaintenanceReminder::class,
            fn (MaintenanceReminder $reminder): bool => $reminder->overdue === 2 && $reminder->dueToday === 1,
        );
    }

    public function test_work_nobody_is_assigned_to_is_reminded_to_planners(): void
    {
        $this->travelTo('2026-09-15 07:00:00');
        $manager = $this->userWithRole('asset_manager');
        $staff = $this->userWithRole('asset_staff');
        WorkOrder::factory()->create(['due_date' => '2026-09-12']);
        Notification::fake();

        $this->artisan('maintenance:send-reminders')->assertSuccessful();

        Notification::assertSentTo($manager, MaintenanceReminder::class, fn (MaintenanceReminder $reminder): bool => $reminder->overdue === 1);
        Notification::assertNotSentTo($staff, MaintenanceReminder::class);
    }

    public function test_a_user_is_reminded_at_most_once_a_day(): void
    {
        $this->travelTo('2026-09-15 07:00:00');
        $technician = User::factory()->create(['is_active' => true]);
        WorkOrder::factory()->create(['due_date' => '2026-09-14', 'assigned_to' => $technician->id]);

        $this->artisan('maintenance:send-reminders')->assertSuccessful();
        $this->artisan('maintenance:send-reminders')->assertSuccessful();

        $this->assertSame(1, $technician->notifications()->count());
        $this->assertSame('Work orders need attention', $technician->notifications()->sole()->data['title']);

        $this->travelTo('2026-09-16 07:00:00');
        $this->artisan('maintenance:send-reminders')->assertSuccessful();

        $this->assertSame(2, $technician->notifications()->count());
    }

    private function userWithRole(string $role): User
    {
        $this->seed(RoleSeeder::class);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole($role);

        return $user;
    }
}
