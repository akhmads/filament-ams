<?php

namespace App\Filament\Imports;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\DepreciationMethod;
use App\Enums\FiscalAssetGroup;
use App\Enums\PlacementType;
use App\Filament\Exports\AssetExporter;
use App\Filament\Imports\Concerns\ReadsSheetValues;
use App\Filament\Imports\Concerns\ResolvesNamedMasters;
use App\Filament\Imports\Concerns\ValidatesRowsUpFront;
use App\Filament\Imports\Contracts\ValidatesFileUpFront;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetModel;
use App\Models\Branch;
use App\Models\Brand;
use App\Models\Department;
use App\Models\Employee;
use App\Models\Location;
use App\Models\Supplier;
use App\Services\AssetCodeGenerator;
use App\Services\AssetMovementRecorder;
use App\Support\Placement;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Registers new assets from a file — the first migration of an existing asset
 * list, or a delivery of many units at once.
 *
 * Every row becomes a new asset: the code is generated as on the form, and the
 * opening position is written to the movement ledger. A row carrying an asset code
 * is refused, because moving, handing over or re-valuing an existing asset goes
 * through its own documents, never through a file. Headers match
 * {@see AssetExporter}, so an export is a ready-made template.
 */
class AssetImporter extends Importer implements ValidatesFileUpFront
{
    use ReadsSheetValues;
    use ResolvesNamedMasters;
    use ValidatesRowsUpFront;

    protected static ?string $model = Asset::class;

    /**
     * What an asset can be registered as. Anything else — under repair, in transit,
     * lost, disposed — is the outcome of work recorded in this application.
     */
    private const OPENING_STATUSES = [
        AssetStatus::Available->value,
        AssetStatus::InStorage->value,
        AssetStatus::InUse->value,
        AssetStatus::Retired->value,
    ];

    /**
     * The master data a row names, looked up once while the record is resolved.
     *
     * @var array{category: AssetCategory, branch: Branch, location: ?Location, employee: ?Employee, department: ?Department, brand: ?Brand, model: ?AssetModel, supplier: ?Supplier}|null
     */
    private ?array $resolved = null;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('code')
                ->label('Asset Code (leave blank)')
                ->helperText('Kode aset dibuat otomatis. Baris yang berisi kode ditolak, karena import hanya menambah aset baru.')
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('name')
                ->label('Asset Name')
                ->requiredMapping()
                ->example('Laptop Dell Latitude 5440')
                ->rules(['required', 'max:255']),
            ImportColumn::make('category')
                ->label('Category (code / name)')
                ->requiredMapping()
                ->example('IT-LAP')
                ->rules(['required'])
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('branch')
                ->label('Branch (code / name)')
                ->requiredMapping()
                ->example('HO')
                ->rules(['required'])
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('location')
                ->label('Room / Warehouse (code / name)')
                ->helperText('Ruangan atau gudang di cabang aset. Isi ini atau kolom karyawan, tidak keduanya.')
                ->example('GDG-01')
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('employee')
                ->label('Held By (employee number)')
                ->helperText('Nomor karyawan di cabang aset, bila aset langsung dipegang karyawan.')
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('department')
                ->label('Department (code / name)')
                ->helperText('Kosong: mengikuti departemen karyawan pemegang, bila ada.')
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('brand')
                ->label('Brand')
                ->example('Dell')
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('model')
                ->label('Model')
                ->helperText('Nama model milik merek di atas.')
                ->example('Latitude 5440')
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('serial_number')
                ->label('Serial Number')
                ->castStateUsing(fn (mixed $state): ?string => blank($state) ? null : trim((string) $state))
                ->rules(['nullable', 'max:255']),
            ImportColumn::make('manufacture_year')
                ->label('Year of Manufacture')
                ->castStateUsing(fn (mixed $state): ?string => blank($state) ? null : trim((string) $state))
                ->rules(['nullable', 'integer', 'min:1950', 'max:'.((int) date('Y') + 1)]),
            ImportColumn::make('status')
                ->label('Status')
                ->helperText('available, in_storage, in_use, atau retired. Kosong: in_use bila di ruangan/karyawan, available bila di gudang.')
                ->castStateUsing(fn (mixed $state): ?string => static::sheetEnum(AssetStatus::class, $state, 'status'))
                ->rules(['nullable', Rule::in(self::OPENING_STATUSES)])
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('condition')
                ->label('Condition')
                ->helperText('good, minor_damage, major_damage, atau broken. Kosong berarti good.')
                ->castStateUsing(fn (mixed $state): ?string => static::sheetEnum(AssetCondition::class, $state, 'condition'))
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('acquisition_date')
                ->label('Acquisition Date')
                ->requiredMapping()
                ->example('2026-01-15')
                ->castStateUsing(fn (mixed $state): ?string => static::sheetDate($state, 'acquisition_date'))
                ->rules(['required', 'date', 'before_or_equal:today']),
            ImportColumn::make('acquisition_cost')
                ->label('Acquisition Cost')
                ->requiredMapping()
                ->example('18500000')
                ->castStateUsing(fn (mixed $state): ?string => blank($state) ? null : trim((string) $state))
                ->rules(['required', 'numeric', 'min:0', 'decimal:0,2']),
            ImportColumn::make('supplier')
                ->label('Supplier (code / name)')
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('po_number')->label('PO Number')->rules(['nullable', 'max:50']),
            ImportColumn::make('invoice_number')->label('Invoice Number')->rules(['nullable', 'max:50']),
            ImportColumn::make('funding_source')->label('Funding Source')->rules(['nullable', 'max:50']),
            ImportColumn::make('is_depreciable')
                ->label('Depreciable')
                ->helperText('1 atau 0. Kosong: mengikuti kategori.')
                ->castStateUsing(fn (mixed $state): ?bool => static::sheetBoolean($state, 'is_depreciable'))
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('depreciation_method')
                ->label('Depreciation Method')
                ->helperText('Kosong: mengikuti kategori.')
                ->castStateUsing(fn (mixed $state): ?string => static::sheetEnum(DepreciationMethod::class, $state, 'depreciation_method'))
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('useful_life_months')
                ->label('Useful Life (months)')
                ->helperText('Kosong: mengikuti kategori.')
                ->castStateUsing(fn (mixed $state): ?string => blank($state) ? null : trim((string) $state))
                ->rules(['nullable', 'integer', 'min:1'])
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('residual_value')
                ->label('Residual Value')
                ->castStateUsing(fn (mixed $state): ?string => blank($state) ? null : trim((string) $state))
                ->rules(['nullable', 'numeric', 'min:0', 'decimal:0,2'])
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('depreciation_start_date')
                ->label('Depreciation Start Date')
                ->helperText('Kosong: sama dengan tanggal perolehan.')
                ->castStateUsing(fn (mixed $state): ?string => static::sheetDate($state, 'depreciation_start_date'))
                ->rules(['nullable', 'date'])
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('fiscal_group')
                ->label('Tax Asset Group')
                ->helperText('group_1 … group_4, building_permanent, building_non_permanent. Kosong: mengikuti kategori.')
                ->castStateUsing(fn (mixed $state): ?string => static::sheetEnum(FiscalAssetGroup::class, $state, 'fiscal_group'))
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('fiscal_method')
                ->label('Tax Depreciation Method')
                ->castStateUsing(fn (mixed $state): ?string => static::sheetEnum(DepreciationMethod::class, $state, 'fiscal_method'))
                ->fillRecordUsing(fn () => null),
            ImportColumn::make('warranty_start')
                ->label('Warranty Start')
                ->castStateUsing(fn (mixed $state): ?string => static::sheetDate($state, 'warranty_start'))
                ->rules(['nullable', 'date']),
            ImportColumn::make('warranty_end')
                ->label('Warranty End')
                ->castStateUsing(fn (mixed $state): ?string => static::sheetDate($state, 'warranty_end'))
                ->rules(['nullable', 'date']),
            ImportColumn::make('warranty_vendor')->label('Warranty Vendor')->rules(['nullable', 'max:255']),
            ImportColumn::make('notes')->label('Notes')->rules(['nullable', 'max:5000']),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    public static function getOptionsFormComponents(): array
    {
        return [
            static::allOrNothingOption(),
        ];
    }

    /**
     * Refuses a code, and looks up every master the row names. Done here rather
     * than when the record is filled, so the up-front check catches it too.
     *
     * @throws ValidationException
     */
    public function resolveRecord(): Asset
    {
        if (filled($code = trim((string) ($this->data['code'] ?? '')))) {
            throw ValidationException::withMessages(['code' => Asset::query()->withTrashed()->where('code', $code)->exists()
                ? "Asset {$code} is already registered. The import only adds new assets."
                : 'Leave the asset code blank: codes are generated when the asset is registered.']);
        }

        /** @var AssetCategory $category */
        $category = static::namedMaster(AssetCategory::query()->where('is_active', true), ['code', 'name'], $this->data['category'] ?? null, 'category', required: true);

        /** @var Branch $branch */
        $branch = static::namedMaster(Branch::query()->where('is_active', true), ['code', 'name'], $this->data['branch'] ?? null, 'branch', required: true);

        /** @var Location|null $location */
        $location = static::namedMaster(Location::query()->active()->holdsAssets()->where('branch_id', $branch->id), ['code', 'name'], $this->data['location'] ?? null, 'location');

        /** @var Employee|null $employee */
        $employee = static::namedMaster(Employee::query()->active()->where('branch_id', $branch->id), ['employee_number'], $this->data['employee'] ?? null, 'employee');

        if ($location === null && $employee === null) {
            throw ValidationException::withMessages(['location' => "Say where the asset is: a room or warehouse code in branch {$branch->code}, or the number of the employee holding it."]);
        }

        if ($location !== null && $employee !== null) {
            throw ValidationException::withMessages(['location' => 'Give either a room or warehouse, or an employee holding the asset — not both.']);
        }

        /** @var Brand|null $brand */
        $brand = static::namedMaster(Brand::query(), ['name'], $this->data['brand'] ?? null, 'brand');

        if ($brand === null && filled($this->data['model'] ?? null)) {
            throw ValidationException::withMessages(['model' => 'A model can only be matched within its brand. Fill in the brand too.']);
        }

        $this->resolved = [
            'category' => $category,
            'branch' => $branch,
            'location' => $location,
            'employee' => $employee,
            'department' => static::namedMaster(Department::query(), ['code', 'name'], $this->data['department'] ?? null, 'department'),
            'brand' => $brand,
            'model' => $brand === null ? null : static::namedMaster(AssetModel::query()->where('brand_id', $brand->id), ['name'], $this->data['model'] ?? null, 'model'),
            'supplier' => static::namedMaster(Supplier::query(), ['code', 'name'], $this->data['supplier'] ?? null, 'supplier'),
        ];

        return new Asset;
    }

    /**
     * Fills what the columns name, then everything the form would have defaulted:
     * the placement, and the depreciation settings of the category.
     */
    public function fillRecord(): void
    {
        parent::fillRecord();

        [
            'category' => $category, 'branch' => $branch, 'location' => $location, 'employee' => $employee,
            'department' => $department, 'brand' => $brand, 'model' => $model, 'supplier' => $supplier,
        ] = $this->resolved;

        $placement = $employee !== null ? Placement::toEmployee($employee) : Placement::toLocation($location);

        $this->record->forceFill([
            'asset_category_id' => $category->id,
            'branch_id' => $branch->id,
            'placement_type' => $placement->type,
            'current_location_id' => $placement->locationId,
            'current_employee_id' => $placement->employeeId,
            'department_id' => $department?->id ?? $employee?->department_id,
            'brand_id' => $brand?->id,
            'asset_model_id' => $model?->id,
            'supplier_id' => $supplier?->id,
            'status' => AssetStatus::tryFrom((string) ($this->data['status'] ?? ''))
                ?? ($placement->type === PlacementType::Warehouse ? AssetStatus::Available : AssetStatus::InUse),
            'condition' => AssetCondition::tryFrom((string) ($this->data['condition'] ?? '')) ?? AssetCondition::Good,
            'is_depreciable' => $this->data['is_depreciable'] ?? $category->is_depreciable,
            'depreciation_method' => DepreciationMethod::tryFrom((string) ($this->data['depreciation_method'] ?? '')) ?? $category->depreciation_method,
            'useful_life_months' => filled($this->data['useful_life_months'] ?? null) ? (int) $this->data['useful_life_months'] : $category->useful_life_months,
            'residual_value' => $this->data['residual_value'] ?? 0,
            'depreciation_start_date' => $this->data['depreciation_start_date'] ?? $this->data['acquisition_date'],
            'fiscal_group' => FiscalAssetGroup::tryFrom((string) ($this->data['fiscal_group'] ?? '')),
            'fiscal_method' => DepreciationMethod::tryFrom((string) ($this->data['fiscal_method'] ?? '')) ?? $category->fiscal_method,
            'created_by' => $this->import->user_id,
            'updated_by' => $this->import->user_id,
        ]);
    }

    /**
     * The code is generated inside the same transaction as the save and the opening
     * movement, so a row that fails to save does not use up a sequence number.
     */
    public function saveRecord(): void
    {
        DB::transaction(function (): void {
            /** @var Asset $asset */
            $asset = $this->record;

            $asset->code = app(AssetCodeGenerator::class)->generate(
                category: $this->resolved['category'],
                branch: $this->resolved['branch'],
                date: Carbon::parse($asset->acquisition_date),
            );

            parent::saveRecord();

            app(AssetMovementRecorder::class)->recordInitial($asset, 'Registered by import');
        });
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Asset import finished: '.Number::format($import->successful_rows).' asset(s) registered.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' row(s) failed.';
        }

        return $body;
    }
}
