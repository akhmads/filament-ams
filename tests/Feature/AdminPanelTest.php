<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\AssetAssignments\AssetAssignmentResource;
use App\Filament\Resources\AssetAudits\AssetAuditResource;
use App\Filament\Resources\AssetCategories\AssetCategoryResource;
use App\Filament\Resources\AssetDisposals\AssetDisposalResource;
use App\Filament\Resources\AssetModels\AssetModelResource;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\Brands\BrandResource;
use App\Filament\Resources\Departments\DepartmentResource;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Filament\Resources\ItemRequests\ItemRequestResource;
use App\Filament\Resources\LabelTemplates\LabelTemplateResource;
use App\Filament\Resources\Locations\LocationResource;
use App\Filament\Resources\MaintenancePlans\MaintenancePlanResource;
use App\Filament\Resources\RepairTickets\RepairTicketResource;
use App\Filament\Resources\StockDocuments\StockDocumentResource;
use App\Filament\Resources\StockItems\StockItemResource;
use App\Filament\Resources\Suppliers\SupplierResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\Location;
use App\Models\User;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Database\Seeders\RoleSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->admin = User::factory()->create(['is_active' => true]);
        $this->admin->assignRole('super_admin');
    }

    /**
     * @return array<string, array{class-string}>
     */
    public static function resourceProvider(): array
    {
        return [
            'assets' => [AssetResource::class],
            'handovers' => [AssetAssignmentResource::class],
            'asset disposals' => [AssetDisposalResource::class],
            'asset audits' => [AssetAuditResource::class],
            'asset categories' => [AssetCategoryResource::class],
            'locations' => [LocationResource::class],
            'employees' => [EmployeeResource::class],
            'branches' => [BranchResource::class],
            'departments' => [DepartmentResource::class],
            'brands' => [BrandResource::class],
            'asset models' => [AssetModelResource::class],
            'suppliers' => [SupplierResource::class],
            'label templates' => [LabelTemplateResource::class],
            'maintenance plans' => [MaintenancePlanResource::class],
            'work orders' => [WorkOrderResource::class],
            'repair tickets' => [RepairTicketResource::class],
            'stock items' => [StockItemResource::class],
            'stock documents' => [StockDocumentResource::class],
            'item requests' => [ItemRequestResource::class],
            'users' => [UserResource::class],
        ];
    }

    #[DataProvider('resourceProvider')]
    public function test_the_resource_list_page_renders(string $resource): void
    {
        $this->actingAs($this->admin)
            ->get($resource::getUrl('index'))
            ->assertSuccessful();
    }

    public function test_the_dashboard_renders_with_its_widgets(): void
    {
        Asset::factory()->count(3)->create();

        $this->actingAs($this->admin)
            ->get('/admin')
            ->assertSuccessful();
    }

    public function test_the_asset_create_page_renders(): void
    {
        $this->actingAs($this->admin)
            ->get(AssetResource::getUrl('create'))
            ->assertSuccessful();
    }

    public function test_the_asset_detail_page_renders(): void
    {
        $asset = Asset::factory()->create();

        $this->actingAs($this->admin)
            ->get(AssetResource::getUrl('view', ['record' => $asset]))
            ->assertSuccessful();
    }

    public function test_the_assignment_create_page_renders(): void
    {
        $this->actingAs($this->admin)
            ->get(AssetAssignmentResource::getUrl('create'))
            ->assertSuccessful();
    }

    public function test_the_assignment_detail_page_renders(): void
    {
        $branch = Branch::factory()->create();
        $assignment = AssetAssignment::factory()->for($branch)->create([
            'to_employee_id' => Employee::factory()->for($branch)->create()->id,
        ]);

        $this->actingAs($this->admin)
            ->get(AssetAssignmentResource::getUrl('view', ['record' => $assignment]))
            ->assertSuccessful();
    }

    public function test_the_asset_edit_page_renders(): void
    {
        $asset = Asset::factory()->for($this->nestedCategory(), 'category')->create();

        $this->actingAs($this->admin)
            ->get(AssetResource::getUrl('edit', ['record' => $asset]))
            ->assertSuccessful();
    }

    public function test_the_asset_create_page_renders_with_nested_categories(): void
    {
        $this->nestedCategory();

        $this->actingAs($this->admin)
            ->get(AssetResource::getUrl('create'))
            ->assertSuccessful();
    }

    public function test_the_asset_detail_page_renders_a_nested_category(): void
    {
        $asset = Asset::factory()->for($this->nestedCategory(), 'category')->create();

        $this->actingAs($this->admin)
            ->get(AssetResource::getUrl('view', ['record' => $asset]))
            ->assertSuccessful();
    }

    public function test_tree_pages_render_with_nested_records(): void
    {
        $category = $this->nestedCategory();
        $room = $this->nestedLocation();

        $this->actingAs($this->admin)
            ->get(AssetCategoryResource::getUrl('index'))
            ->assertSuccessful();

        $this->actingAs($this->admin)
            ->get(AssetCategoryResource::getUrl('view', ['record' => $category]))
            ->assertSuccessful();

        $this->actingAs($this->admin)
            ->get(AssetCategoryResource::getUrl('edit', ['record' => $category]))
            ->assertSuccessful();

        $this->actingAs($this->admin)
            ->get(LocationResource::getUrl('index'))
            ->assertSuccessful();

        $this->actingAs($this->admin)
            ->get(LocationResource::getUrl('view', ['record' => $room]))
            ->assertSuccessful();

        $this->actingAs($this->admin)
            ->get(LocationResource::getUrl('edit', ['record' => $room]))
            ->assertSuccessful();
    }

    public function test_the_handover_create_page_renders_with_nested_locations(): void
    {
        $this->nestedLocation();

        $this->actingAs($this->admin)
            ->get(AssetAssignmentResource::getUrl('create'))
            ->assertSuccessful();
    }

    /**
     * A category two levels deep, so anything rendering its full path has to
     * resolve ancestors.
     */
    private function nestedCategory(): AssetCategory
    {
        $parent = AssetCategory::factory()->create(['name' => 'IT Equipment']);

        return AssetCategory::factory()->create([
            'name' => 'Laptop',
            'parent_id' => $parent->id,
        ]);
    }

    private function nestedLocation(): Location
    {
        $building = Location::factory()->create(['name' => 'Main Building']);

        return Location::factory()->create([
            'branch_id' => $building->branch_id,
            'parent_id' => $building->id,
            'name' => 'IT Room',
        ]);
    }

    public function test_roles_are_listed_under_settings_right_after_users(): void
    {
        Filament::setCurrentPanel('admin');

        $this->assertSame('Settings', RoleResource::getNavigationGroup());
        $this->assertGreaterThan(UserResource::getNavigationSort(), RoleResource::getNavigationSort());
        $this->assertLessThan(LabelTemplateResource::getNavigationSort(), RoleResource::getNavigationSort());
    }

    public function test_the_settings_page_renders(): void
    {
        $this->actingAs($this->admin)
            ->get(ManageSettings::getUrl())
            ->assertSuccessful()
            ->assertSee('Save Settings');
    }

    public function test_an_inactive_user_cannot_reach_the_panel(): void
    {
        $user = User::factory()->create(['is_active' => false]);
        $user->assignRole('super_admin');

        $this->actingAs($user)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }
}
