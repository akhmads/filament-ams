<?php

namespace Database\Seeders;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\AssignmentType;
use App\Enums\PlacementType;
use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetAssignmentItem;
use App\Models\AssetCategory;
use App\Models\AssetModel;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Supplier;
use App\Services\AssetCodeGenerator;
use App\Services\AssetMovementRecorder;
use App\Services\AssignmentService;
use App\Services\DocumentNumberGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Sample assets so the dashboard and asset list are not empty on first open.
 */
class DemoAssetSeeder extends Seeder
{
    public function run(): void
    {
        $codes = app(AssetCodeGenerator::class);
        $recorder = app(AssetMovementRecorder::class);

        $head = Branch::where('code', 'HO')->firstOrFail();
        $bandung = Branch::where('code', 'BDG')->firstOrFail();

        $blueprints = [
            [
                'name' => 'Laptop Lenovo ThinkPad T14',
                'category' => 'IT-LAP', 'model' => 'ThinkPad T14', 'branch' => $head,
                'cost' => 18_500_000, 'months_ago' => 8, 'warranty_months' => 36,
                'placement' => PlacementType::Employee, 'employee' => 'EMP-0001', 'department' => 'IT',
                'specs' => ['processor' => 'Intel Core i7-1355U', 'ram_gb' => 16, 'storage' => '512 GB SSD', 'screen_size' => '14 inci'],
            ],
            [
                'name' => 'Laptop Dell Latitude 5440',
                'category' => 'IT-LAP', 'model' => 'Latitude 5440', 'branch' => $head,
                'cost' => 16_200_000, 'months_ago' => 14, 'warranty_months' => 24,
                'placement' => PlacementType::Employee, 'employee' => 'EMP-0002', 'department' => 'FIN',
                'specs' => ['processor' => 'Intel Core i5-1345U', 'ram_gb' => 16, 'storage' => '512 GB SSD', 'screen_size' => '14 inci'],
            ],
            [
                'name' => 'Laptop Lenovo ThinkPad E14',
                'category' => 'IT-LAP', 'model' => 'ThinkPad E14', 'branch' => $bandung,
                'cost' => 12_900_000, 'months_ago' => 3, 'warranty_months' => 12,
                'placement' => PlacementType::Warehouse, 'location' => 'BDG-GDG',
                'specs' => ['processor' => 'AMD Ryzen 5 7530U', 'ram_gb' => 8, 'storage' => '256 GB SSD', 'screen_size' => '14 inci'],
            ],
            [
                'name' => 'Printer HP LaserJet Pro M404dn',
                'category' => 'IT-PRN', 'model' => 'LaserJet Pro M404dn', 'branch' => $head,
                'cost' => 4_750_000, 'months_ago' => 20, 'warranty_months' => 24,
                'placement' => PlacementType::Location, 'location' => 'HO-R-IT', 'department' => 'IT',
            ],
            [
                'name' => 'Monitor Dell P2422H',
                'category' => 'IT-MON', 'model' => 'P2422H', 'branch' => $head,
                'cost' => 2_450_000, 'months_ago' => 6, 'warranty_months' => 36,
                'placement' => PlacementType::Location, 'location' => 'HO-R-KEU', 'department' => 'FIN',
            ],
            [
                'name' => 'Staff Desk 120×60',
                'category' => 'FUR-MJA', 'branch' => $head,
                'cost' => 1_850_000, 'months_ago' => 30,
                'placement' => PlacementType::Location, 'location' => 'HO-R-KEU',
            ],
            [
                'name' => 'Ergonomic Office Chair',
                'category' => 'FUR-KRS', 'branch' => $head,
                'cost' => 1_250_000, 'months_ago' => 30,
                'placement' => PlacementType::Warehouse, 'location' => 'HO-GDG',
            ],
            [
                'name' => 'Ergonomic Office Chair',
                'category' => 'FUR-KRS', 'branch' => $bandung,
                'cost' => 1_250_000, 'months_ago' => 4,
                'placement' => PlacementType::Warehouse, 'location' => 'BDG-GDG',
            ],
            [
                'name' => 'Toyota Avanza 1.5 G',
                'category' => 'KND', 'model' => 'Avanza 1.5 G', 'branch' => $head,
                'cost' => 245_000_000, 'months_ago' => 26, 'warranty_months' => 36,
                'placement' => PlacementType::Employee, 'employee' => 'EMP-0003', 'department' => 'OPS',
                'condition' => AssetCondition::MinorDamage,
                'specs' => ['plate_number' => 'B 1234 XYZ', 'chassis_number' => 'MHKM1BA3JKJ000123', 'engine_number' => '3NR-F0012345'],
            ],
            [
                'name' => 'Laptop Dell Latitude 5440',
                'category' => 'IT-LAP', 'model' => 'Latitude 5440', 'branch' => $bandung,
                'cost' => 16_200_000, 'months_ago' => 2, 'warranty_months' => 24,
                'placement' => PlacementType::Employee, 'employee' => 'EMP-0004', 'department' => 'HRD',
                'specs' => ['processor' => 'Intel Core i5-1345U', 'ram_gb' => 8, 'storage' => '256 GB SSD', 'screen_size' => '14 inci'],
            ],
        ];

        $supplier = Supplier::where('code', 'SUP-001')->value('id');

        foreach ($blueprints as $blueprint) {
            $category = AssetCategory::where('code', $blueprint['category'])->firstOrFail();
            $branch = $blueprint['branch'];
            $acquiredAt = Carbon::now()->subMonths($blueprint['months_ago'])->startOfDay();

            $placement = $blueprint['placement'];
            $employeeId = isset($blueprint['employee'])
                ? Employee::where('employee_number', $blueprint['employee'])->value('id')
                : null;
            $locationId = isset($blueprint['location'])
                ? Location::where('code', $blueprint['location'])->value('id')
                : null;

            $asset = Asset::create([
                'code' => $codes->generate($category, $branch, $acquiredAt),
                'name' => $blueprint['name'],
                'asset_category_id' => $category->id,
                'asset_model_id' => isset($blueprint['model'])
                    ? AssetModel::where('name', $blueprint['model'])->value('id')
                    : null,
                'brand_id' => isset($blueprint['model'])
                    ? AssetModel::where('name', $blueprint['model'])->value('brand_id')
                    : null,
                'supplier_id' => $supplier,
                'serial_number' => strtoupper(fake()->bothify('??######')),
                'acquisition_date' => $acquiredAt,
                'acquisition_cost' => $blueprint['cost'],
                'invoice_number' => 'INV/'.$acquiredAt->format('Y/m').'/'.fake()->numerify('####'),
                'is_depreciable' => $category->is_depreciable,
                'depreciation_method' => $category->depreciation_method,
                'useful_life_months' => $category->useful_life_months,
                'depreciation_start_date' => $acquiredAt,
                'warranty_start' => isset($blueprint['warranty_months']) ? $acquiredAt : null,
                'warranty_end' => isset($blueprint['warranty_months'])
                    ? $acquiredAt->copy()->addMonths($blueprint['warranty_months'])
                    : null,
                'branch_id' => $branch->id,
                'department_id' => isset($blueprint['department'])
                    ? Department::where('code', $blueprint['department'])->value('id')
                    : null,
                'placement_type' => $placement,
                'current_location_id' => $locationId,
                'current_employee_id' => $employeeId,
                'status' => $placement === PlacementType::Warehouse ? AssetStatus::Available : AssetStatus::InUse,
                'condition' => $blueprint['condition'] ?? AssetCondition::Good,
                'specs' => $blueprint['specs'] ?? null,
            ]);

            $recorder->recordInitial($asset);
        }

        $this->seedHandover($head);
    }

    /**
     * One completed handover, so the BAST flow is visible without having to
     * key in data first.
     */
    private function seedHandover(Branch $branch): void
    {
        $recipient = Employee::where('employee_number', 'EMP-0002')->first();
        $handler = Employee::where('employee_number', 'EMP-0001')->first();
        $assets = Asset::assignable()->where('branch_id', $branch->id)->limit(2)->get();

        if ($recipient === null || $assets->isEmpty()) {
            return;
        }

        $assignment = AssetAssignment::create([
            'number' => app(DocumentNumberGenerator::class)->document('assignment', 'BAST'),
            'type' => AssignmentType::Checkout,
            'assignment_date' => now()->subDays(5),
            'expected_return_date' => now()->addMonths(6),
            'branch_id' => $branch->id,
            'to_placement_type' => PlacementType::Employee,
            'to_employee_id' => $recipient->id,
            'to_department_id' => $recipient->department_id,
            'handed_over_by' => $handler?->id,
            'purpose' => 'For day-to-day work',
        ]);

        foreach ($assets as $asset) {
            AssetAssignmentItem::create([
                'asset_assignment_id' => $assignment->id,
                'asset_id' => $asset->id,
                'condition' => AssetCondition::Good,
            ]);
        }

        app(AssignmentService::class)->complete($assignment);
    }
}
