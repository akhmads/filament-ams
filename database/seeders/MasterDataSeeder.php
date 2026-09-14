<?php

namespace Database\Seeders;

use App\Enums\DepreciationMethod;
use App\Enums\EmployeeStatus;
use App\Enums\LocationType;
use App\Models\AssetCategory;
use App\Models\AssetModel;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedBranchesAndLocations();
        $this->seedDepartmentsAndEmployees();
        $this->seedCategories();
        $this->seedBrandsAndSuppliers();
    }

    private function seedBranchesAndLocations(): void
    {
        $branches = [
            ['code' => 'HO', 'name' => 'Head Office', 'city' => 'Jakarta', 'address' => 'Jl. Merdeka No. 1, Jakarta Pusat'],
            ['code' => 'BDG', 'name' => 'Bandung Branch', 'city' => 'Bandung', 'address' => 'Jl. Asia Afrika No. 20, Bandung'],
        ];

        foreach ($branches as $data) {
            $branch = Branch::firstOrCreate(['code' => $data['code']], $data);

            $building = Location::firstOrCreate(
                ['branch_id' => $branch->id, 'code' => $data['code'].'-GD1'],
                ['name' => 'Main Building', 'type' => LocationType::Building],
            );

            $warehouse = Location::firstOrCreate(
                ['branch_id' => $branch->id, 'code' => $data['code'].'-GDG'],
                ['name' => 'Asset Warehouse', 'type' => LocationType::Warehouse, 'parent_id' => $building->id],
            );

            foreach (['IT Room' => 'R-IT', 'Meeting Room' => 'R-RPT', 'Finance Room' => 'R-KEU'] as $name => $suffix) {
                Location::firstOrCreate(
                    ['branch_id' => $branch->id, 'code' => $data['code'].'-'.$suffix],
                    ['name' => $name, 'type' => LocationType::Room, 'parent_id' => $building->id],
                );
            }

            unset($warehouse);
        }
    }

    private function seedDepartmentsAndEmployees(): void
    {
        $head = Branch::where('code', 'HO')->firstOrFail();
        $bandung = Branch::where('code', 'BDG')->firstOrFail();

        $departments = [
            ['code' => 'IT', 'name' => 'Information Technology', 'cost_center' => 'CC-100'],
            ['code' => 'FIN', 'name' => 'Finance', 'cost_center' => 'CC-200'],
            ['code' => 'HRD', 'name' => 'Human Resources', 'cost_center' => 'CC-300'],
            ['code' => 'OPS', 'name' => 'Operations', 'cost_center' => 'CC-400'],
        ];

        foreach ($departments as $data) {
            Department::firstOrCreate(['code' => $data['code']], $data);
        }

        $employees = [
            ['employee_number' => 'EMP-0001', 'name' => 'Budi Santoso', 'position' => 'IT Support', 'department' => 'IT', 'branch' => $head],
            ['employee_number' => 'EMP-0002', 'name' => 'Siti Rahayu', 'position' => 'Finance Staff', 'department' => 'FIN', 'branch' => $head],
            ['employee_number' => 'EMP-0003', 'name' => 'Agus Pratama', 'position' => 'Operations Manager', 'department' => 'OPS', 'branch' => $head],
            ['employee_number' => 'EMP-0004', 'name' => 'Dewi Lestari', 'position' => 'HR Staff', 'department' => 'HRD', 'branch' => $bandung],
            ['employee_number' => 'EMP-0005', 'name' => 'Rizky Maulana', 'position' => 'Technician', 'department' => 'OPS', 'branch' => $bandung],
        ];

        foreach ($employees as $data) {
            Employee::firstOrCreate(
                ['employee_number' => $data['employee_number']],
                [
                    'name' => $data['name'],
                    'position' => $data['position'],
                    'branch_id' => $data['branch']->id,
                    'department_id' => Department::where('code', $data['department'])->value('id'),
                    'status' => EmployeeStatus::Active,
                    'joined_at' => now()->subYears(2),
                ],
            );
        }
    }

    private function seedCategories(): void
    {
        $it = AssetCategory::firstOrCreate(['code' => 'IT'], [
            'name' => 'IT Equipment',
            'prefix' => 'IT',
            'useful_life_months' => 48,
            'depreciation_method' => DepreciationMethod::StraightLine,
        ]);

        $laptop = AssetCategory::firstOrCreate(['code' => 'IT-LAP'], [
            'parent_id' => $it->id,
            'name' => 'Laptop',
            'prefix' => 'LAP',
            'useful_life_months' => 48,
            'depreciation_method' => DepreciationMethod::StraightLine,
            'requires_maintenance' => true,
            'spec_fields' => [
                ['key' => 'processor', 'label' => 'Processor', 'type' => 'text'],
                ['key' => 'ram_gb', 'label' => 'RAM (GB)', 'type' => 'number'],
                ['key' => 'storage', 'label' => 'Storage', 'type' => 'text'],
                ['key' => 'screen_size', 'label' => 'Screen Size', 'type' => 'text'],
            ],
        ]);

        AssetCategory::firstOrCreate(['code' => 'IT-PRN'], [
            'parent_id' => $it->id,
            'name' => 'Printer',
            'prefix' => 'PRN',
            'useful_life_months' => 36,
            'requires_maintenance' => true,
        ]);

        AssetCategory::firstOrCreate(['code' => 'IT-MON'], [
            'parent_id' => $it->id,
            'name' => 'Monitor',
            'prefix' => 'MON',
            'useful_life_months' => 48,
        ]);

        $furniture = AssetCategory::firstOrCreate(['code' => 'FUR'], [
            'name' => 'Furniture',
            'prefix' => 'FUR',
            'useful_life_months' => 96,
        ]);

        AssetCategory::firstOrCreate(['code' => 'FUR-MJA'], [
            'parent_id' => $furniture->id,
            'name' => 'Desk',
            'prefix' => 'MJA',
            'useful_life_months' => 96,
        ]);

        AssetCategory::firstOrCreate(['code' => 'FUR-KRS'], [
            'parent_id' => $furniture->id,
            'name' => 'Office Chair',
            'prefix' => 'KRS',
            'useful_life_months' => 60,
        ]);

        $vehicle = AssetCategory::firstOrCreate(['code' => 'KND'], [
            'name' => 'Vehicle',
            'prefix' => 'KND',
            'useful_life_months' => 96,
            'depreciation_method' => DepreciationMethod::DoubleDeclining,
            'requires_maintenance' => true,
            'spec_fields' => [
                ['key' => 'plate_number', 'label' => 'Plate Number', 'type' => 'text'],
                ['key' => 'chassis_number', 'label' => 'Chassis Number', 'type' => 'text'],
                ['key' => 'engine_number', 'label' => 'Engine Number', 'type' => 'text'],
            ],
        ]);

        AssetCategory::firstOrCreate(['code' => 'TNH'], [
            'name' => 'Land',
            'prefix' => 'TNH',
            'is_depreciable' => false,
            'depreciation_method' => DepreciationMethod::None,
        ]);

        unset($laptop, $vehicle);
    }

    private function seedBrandsAndSuppliers(): void
    {
        $models = [
            'Lenovo' => [['name' => 'ThinkPad T14', 'category' => 'IT-LAP'], ['name' => 'ThinkPad E14', 'category' => 'IT-LAP']],
            'Dell' => [['name' => 'Latitude 5440', 'category' => 'IT-LAP'], ['name' => 'P2422H', 'category' => 'IT-MON']],
            'HP' => [['name' => 'LaserJet Pro M404dn', 'category' => 'IT-PRN']],
            'Toyota' => [['name' => 'Avanza 1.5 G', 'category' => 'KND']],
        ];

        foreach ($models as $brandName => $brandModels) {
            $brand = Brand::firstOrCreate(['name' => $brandName]);

            foreach ($brandModels as $model) {
                AssetModel::firstOrCreate(
                    ['brand_id' => $brand->id, 'name' => $model['name']],
                    ['asset_category_id' => AssetCategory::where('code', $model['category'])->value('id')],
                );
            }
        }

        $suppliers = [
            ['code' => 'SUP-001', 'name' => 'CV Mitra Komputer', 'contact_person' => 'Andi', 'phone' => '021-5551234'],
            ['code' => 'SUP-002', 'name' => 'PT Furnitur Nusantara', 'contact_person' => 'Rina', 'phone' => '022-5559876'],
            ['code' => 'SUP-003', 'name' => 'PT Auto Prima', 'contact_person' => 'Hendra', 'phone' => '021-5554321'],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::firstOrCreate(['code' => $supplier['code']], $supplier);
        }
    }
}
