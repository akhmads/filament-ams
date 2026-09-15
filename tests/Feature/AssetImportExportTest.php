<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\DepreciationMethod;
use App\Enums\LocationType;
use App\Enums\MovementType;
use App\Enums\PlacementType;
use App\Filament\Exports\AssetExporter;
use App\Filament\Imports\AssetImporter;
use App\Filament\Resources\Assets\Pages\ListAssets;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetMovement;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\User;
use App\Support\ImportPreflight;
use App\Support\Spreadsheet;
use Database\Seeders\RoleSeeder;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Imports\Models\Import;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AssetImportExportTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;

    private AssetCategory $laptops;

    private Location $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('admin');
        $this->travelTo('2026-09-15 10:00:00');
    }

    public function test_a_row_registers_a_new_asset_with_a_generated_code_and_an_opening_movement(): void
    {
        $this->masters();

        $this->importRow($this->row());

        $asset = Asset::query()->sole();
        $this->assertStringStartsWith('LAP-HO-2601-', $asset->code);
        $this->assertSame('Laptop Dell Latitude 5440', $asset->name);
        $this->assertSame($this->laptops->id, $asset->asset_category_id);
        $this->assertSame(PlacementType::Warehouse, $asset->placement_type);
        $this->assertSame($this->warehouse->id, $asset->current_location_id);
        $this->assertSame(AssetStatus::Available, $asset->status);
        $this->assertSame(AssetCondition::Good, $asset->condition);
        $this->assertSame('18500000.00', $asset->acquisition_cost);

        // Depreciation settings left blank follow the category.
        $this->assertSame(DepreciationMethod::StraightLine, $asset->depreciation_method);
        $this->assertSame(48, $asset->useful_life_months);
        $this->assertSame('2026-01-15', $asset->depreciation_start_date->toDateString());

        $movement = AssetMovement::query()->sole();
        $this->assertSame(MovementType::Initial, $movement->movement_type);
        $this->assertSame($asset->id, $movement->asset_id);
    }

    public function test_an_asset_held_by_an_employee_is_in_use_in_their_department(): void
    {
        $this->masters();
        $finance = Department::factory()->create();
        $employee = Employee::factory()->for($this->branch)->create(['employee_number' => 'EMP-0042', 'department_id' => $finance->id]);

        $this->importRow($this->row(['location' => '', 'employee' => 'emp-0042']));

        $asset = Asset::query()->sole();
        $this->assertSame($employee->id, $asset->current_employee_id);
        $this->assertSame(AssetStatus::InUse, $asset->status);
        $this->assertSame($finance->id, $asset->department_id);
    }

    /**
     * @return array<string, array{array<string, string>, string}>
     */
    public static function invalidRows(): array
    {
        return [
            'an asset code' => [['code' => 'LAP-HO-2601-0001'], 'code'],
            'an unknown category' => [['category' => 'Spaceships'], 'category'],
            'no position at all' => [['location' => ''], 'location'],
            'both a room and an employee' => [['employee' => 'EMP-0001'], 'location'],
            'a location that cannot hold assets' => [['location' => 'FLOOR-1'], 'location'],
            'a slashed date' => [['acquisition_date' => '15/01/2026'], 'acquisition_date'],
            'a future acquisition' => [['acquisition_date' => '2026-12-01'], 'acquisition_date'],
            'a negative cost' => [['acquisition_cost' => '-5'], 'acquisition_cost'],
            'a status that only work can produce' => [['status' => 'under_repair'], 'status'],
            'a model without its brand' => [['brand' => '', 'model' => 'Latitude 5440'], 'model'],
        ];
    }

    /**
     * @param  array<string, string>  $overrides
     */
    #[DataProvider('invalidRows')]
    public function test_a_row_that_does_not_describe_a_new_asset_fails(array $overrides, string $column): void
    {
        $this->masters();
        Employee::factory()->for($this->branch)->create(['employee_number' => 'EMP-0001']);
        Location::factory()->floor()->for($this->branch)->create(['code' => 'FLOOR-1']);

        try {
            $this->importRow($this->row($overrides));
            $this->fail('The row should have failed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($column, $exception->errors());
        }

        $this->assertSame(0, Asset::query()->count());
    }

    public function test_an_exported_asset_reads_back_into_the_importer_once_its_code_is_cleared(): void
    {
        $this->masters();
        $this->importRow($this->row(['serial_number' => 'SN-001']));

        $exported = $this->exportedRow(Asset::query()->sole());
        $this->assertSame('LAP', $exported['category']);
        $this->assertSame('GDG-01', $exported['location']);
        $this->assertSame('2026-01-15', $exported['acquisition_date']);
        $this->assertSame('18500000.00', $exported['acquisition_cost']);

        $this->importRow([...$exported, 'code' => '', 'serial_number' => 'SN-002']);

        $this->assertSame(2, Asset::query()->count());
        $this->assertSame($this->warehouse->id, Asset::query()->where('serial_number', 'SN-002')->value('current_location_id'));
    }

    public function test_the_up_front_check_names_the_failing_rows_and_writes_nothing(): void
    {
        $this->masters();

        $failures = (new ImportPreflight($this->importer()))->run([
            $this->row(),
            $this->row(['category' => 'Spaceships']),
        ]);

        $this->assertSame([3], array_keys($failures));
        $this->assertStringContainsString('Spaceships', $failures[3]);
        $this->assertSame(0, Asset::query()->count());
    }

    public function test_a_workbook_is_read_without_changing_its_numbers_or_dates(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'assets-').'.xlsx';
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues(['name', 'acquisition_date', 'acquisition_cost']));
        $writer->addRow(new Row([
            Cell::fromValue('Laptop'),
            Cell::fromValue(new \DateTimeImmutable('2026-01-15'), (new Style)->setFormat('yyyy-mm-dd')),
            Cell::fromValue(18500000.0),
        ]));
        $writer->close();

        $stream = Spreadsheet::toCsvStream($path, 'xlsx');
        $lines = array_values(array_filter(explode("\n", (string) stream_get_contents($stream))));
        unlink($path);

        $this->assertSame('Laptop,2026-01-15,18500000', trim($lines[1]));
    }

    public function test_staff_may_import_assets_while_an_auditor_may_only_export_them(): void
    {
        $this->seed(RoleSeeder::class);

        Livewire::actingAs(User::factory()->create()->assignRole('asset_staff'))
            ->test(ListAssets::class)
            ->assertActionVisible('import')
            ->assertActionVisible('export');

        Livewire::actingAs(User::factory()->create()->assignRole('auditor'))
            ->test(ListAssets::class)
            ->assertActionHidden('import')
            ->assertActionVisible('export');
    }

    private function masters(): void
    {
        $this->branch = Branch::factory()->create(['code' => 'HO', 'name' => 'Head Office']);
        $this->laptops = AssetCategory::factory()->create([
            'code' => 'LAP',
            'prefix' => 'LAP',
            'name' => 'Laptop',
            'is_depreciable' => true,
            'depreciation_method' => DepreciationMethod::StraightLine,
            'useful_life_months' => 48,
            'fiscal_method' => DepreciationMethod::StraightLine,
            'is_active' => true,
        ]);
        $this->warehouse = Location::factory()->for($this->branch)->create([
            'code' => 'GDG-01',
            'name' => 'Gudang Pusat',
            'type' => LocationType::Warehouse,
        ]);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function row(array $overrides = []): array
    {
        return array_merge([
            'code' => '',
            'name' => 'Laptop Dell Latitude 5440',
            'category' => 'lap',
            'branch' => 'HO',
            'location' => 'GDG-01',
            'employee' => '',
            'department' => '',
            'brand' => '',
            'model' => '',
            'serial_number' => '',
            'status' => '',
            'condition' => '',
            'acquisition_date' => '2026-01-15',
            'acquisition_cost' => '18500000',
            'is_depreciable' => '',
            'depreciation_method' => '',
            'useful_life_months' => '',
        ], $overrides);
    }

    /**
     * @param  array<string, string>  $row
     */
    private function importRow(array $row): void
    {
        ($this->importer(array_keys($row)))($row);
    }

    /**
     * @param  list<string>|null  $columns
     */
    private function importer(?array $columns = null): AssetImporter
    {
        $columns ??= array_keys($this->row());

        $import = Import::create([
            'file_name' => 'assets.csv',
            'file_path' => 'assets.csv',
            'importer' => AssetImporter::class,
            'total_rows' => 1,
            'user_id' => User::factory()->create()->id,
        ]);

        return new AssetImporter($import, array_combine($columns, $columns), []);
    }

    /**
     * @return array<string, string|null>
     */
    private function exportedRow(Asset $asset): array
    {
        $columnMap = [];

        foreach (AssetExporter::getColumns() as $column) {
            if ($column->isEnabledByDefault()) {
                $columnMap[$column->getName()] = $column->getLabel();
            }
        }

        $export = Export::create([
            'file_disk' => 'local',
            'file_name' => 'assets.csv',
            'exporter' => AssetExporter::class,
            'total_rows' => 1,
            'user_id' => User::factory()->create()->id,
        ]);

        $asset = AssetExporter::modifyQuery(Asset::query())->findOrFail($asset->id);

        return array_combine(array_values($columnMap), (new AssetExporter($export, $columnMap, []))($asset));
    }
}
