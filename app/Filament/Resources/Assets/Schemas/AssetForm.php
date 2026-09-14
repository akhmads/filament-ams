<?php

namespace App\Filament\Resources\Assets\Schemas;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\DepreciationMethod;
use App\Enums\DepreciationPeriodStatus;
use App\Enums\FiscalAssetGroup;
use App\Enums\PlacementType;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Employee;
use App\Models\Location;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class AssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Asset')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('General')
                            ->icon('heroicon-o-information-circle')
                            ->schema(self::generalSection()),
                        Tab::make('Acquisition')
                            ->icon('heroicon-o-banknotes')
                            ->schema(self::acquisitionSection()),
                        Tab::make('Depreciation & Warranty')
                            ->icon('heroicon-o-calculator')
                            ->schema(self::depreciationSection()),
                        Tab::make('Placement')
                            ->icon('heroicon-o-map-pin')
                            ->schema(self::placementSection()),
                        Tab::make('Specifications')
                            ->icon('heroicon-o-list-bullet')
                            ->schema(self::specSection()),
                        Tab::make('Attachments')
                            ->icon('heroicon-o-paper-clip')
                            ->schema(self::attachmentSection()),
                    ]),
            ]);
    }

    /**
     * @return array<int, mixed>
     */
    private static function generalSection(): array
    {
        return [
            Grid::make(2)->schema([
                TextInput::make('code')
                    ->label('Asset Code')
                    ->helperText('Generated automatically when the asset is saved and cannot be changed afterwards.')
                    ->disabled()
                    ->dehydrated(false)
                    ->placeholder('Automatic'),
                TextInput::make('name')
                    ->label('Asset Name')
                    ->required()
                    ->maxLength(255),
                Select::make('asset_category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->getOptionLabelFromRecordUsing(fn (AssetCategory $record): string => $record->full_name)
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, mixed $state): void {
                        // Inherit the depreciation defaults from the chosen category.
                        $category = AssetCategory::find($state);

                        if ($category === null) {
                            return;
                        }

                        $set('is_depreciable', $category->is_depreciable);
                        $set('depreciation_method', $category->depreciation_method->value);
                        $set('useful_life_months', $category->useful_life_months);
                        $set('fiscal_method', $category->fiscal_method->value);
                    }),
                Select::make('brand_id')
                    ->label('Brand')
                    ->relationship('brand', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->createOptionForm([
                        TextInput::make('name')->label('Brand Name')->required()->maxLength(255),
                    ]),
                Select::make('asset_model_id')
                    ->label('Model')
                    ->relationship(
                        name: 'assetModel',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query, Get $get) => $query
                            ->when($get('brand_id'), fn (Builder $q, $brandId) => $q->where('brand_id', $brandId)),
                    )
                    ->searchable()
                    ->preload()
                    ->helperText('Pick a brand first to narrow down the model list.'),
                TextInput::make('serial_number')
                    ->label('Serial Number')
                    ->maxLength(255),
                TextInput::make('manufacture_year')
                    ->label('Year of Manufacture')
                    ->numeric()
                    ->minValue(1950)
                    ->maxValue((int) date('Y') + 1),
                Select::make('status')
                    ->label('Status')
                    ->options(AssetStatus::class)
                    ->default(AssetStatus::Available)
                    ->required()
                    ->disabledOn('edit')
                    ->helperText(fn (string $operation): ?string => $operation === 'edit'
                        ? 'Status changes through handovers, transfers or repairs — not edited by hand here.'
                        : null),
                Select::make('condition')
                    ->label('Condition')
                    ->options(AssetCondition::class)
                    ->default(AssetCondition::Good)
                    ->required(),
                Select::make('parent_id')
                    ->label('Parent Asset')
                    ->relationship(
                        name: 'parent',
                        titleAttribute: 'code',
                        modifyQueryUsing: fn (Builder $query, ?Asset $record) => $query
                            ->when($record?->exists, fn (Builder $q) => $q->whereNot('id', $record->id)),
                    )
                    ->getOptionLabelFromRecordUsing(fn ($record): string => "{$record->code} — {$record->name}")
                    ->searchable()
                    ->helperText('Fill in if this asset is a component of another, e.g. a monitor on a PC.'),
            ]),
            Textarea::make('notes')
                ->label('Notes')
                ->rows(3)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function acquisitionSection(): array
    {
        return [
            Grid::make(2)->schema([
                DatePicker::make('acquisition_date')
                    ->label('Acquisition Date')
                    ->disabled(fn (?Asset $record): bool => self::hasPostedDepreciation($record))
                    ->required()
                    ->default(now())
                    ->maxDate(now())
                    ->live()
                    ->afterStateUpdated(fn (Set $set, mixed $state) => $set('depreciation_start_date', $state)),
                TextInput::make('acquisition_cost')
                    ->label('Acquisition Cost')
                    ->disabled(fn (?Asset $record): bool => self::hasPostedDepreciation($record))
                    ->helperText(fn (?Asset $record): ?string => self::hasPostedDepreciation($record)
                        ? 'Locked: depreciation has already been posted from this cost and date.'
                        : null)
                    ->numeric()
                    ->prefix('Rp')
                    ->required()
                    ->minValue(0)
                    ->default(0),
                Select::make('supplier_id')
                    ->label('Supplier')
                    ->relationship('supplier', 'name')
                    ->searchable()
                    ->preload(),
                TextInput::make('funding_source')
                    ->label('Funding Source')
                    ->maxLength(50),
                TextInput::make('po_number')
                    ->label('PO Number')
                    ->maxLength(50),
                TextInput::make('invoice_number')
                    ->label('Invoice Number')
                    ->maxLength(50),
            ]),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function depreciationSection(): array
    {
        return [
            Section::make('Depreciation')
                ->description('Initial values follow the category. Locked once depreciation has been posted for this asset.')
                ->disabled(fn (?Asset $record): bool => self::hasPostedDepreciation($record))
                ->schema([
                    Toggle::make('is_depreciable')
                        ->label('Depreciable')
                        ->default(true)
                        ->live(),
                    Grid::make(2)
                        ->visible(fn (Get $get): bool => (bool) $get('is_depreciable'))
                        ->schema([
                            Select::make('depreciation_method')
                                ->label('Method')
                                ->options(DepreciationMethod::class)
                                ->default(DepreciationMethod::StraightLine)
                                ->required(),
                            TextInput::make('useful_life_months')
                                ->label('Useful Life (months)')
                                ->numeric()
                                ->minValue(1)
                                ->default(48)
                                ->required(),
                            TextInput::make('residual_value')
                                ->label('Residual Value')
                                ->numeric()
                                ->prefix('Rp')
                                ->minValue(0)
                                ->default(0),
                            DatePicker::make('depreciation_start_date')
                                ->label('Depreciation Start'),
                        ]),
                ]),
            Section::make('Tax Depreciation')
                ->description('Useful life and rate follow the tax group. Leave the group empty to use the category\'s.')
                ->disabled(fn (?Asset $record): bool => self::hasPostedDepreciation($record))
                ->schema([
                    Grid::make(2)->schema([
                        Select::make('fiscal_group')
                            ->label('Tax Asset Group')
                            ->options(FiscalAssetGroup::class)
                            ->placeholder('Use the category\'s group')
                            ->live(),
                        Select::make('fiscal_method')
                            ->label('Tax Method')
                            ->options(collect(DepreciationMethod::cases())
                                ->filter(fn (DepreciationMethod $method): bool => $method->isAllowedForFiscal())
                                ->mapWithKeys(fn (DepreciationMethod $method): array => [$method->value => $method->getLabel()])
                                ->all())
                            ->default(DepreciationMethod::StraightLine->value)
                            ->required()
                            ->disableOptionWhen(fn (string $value, Get $get): bool => $value === DepreciationMethod::DoubleDeclining->value
                                && (bool) FiscalAssetGroup::tryFrom((string) ($get('fiscal_group') instanceof FiscalAssetGroup ? $get('fiscal_group')->value : $get('fiscal_group')))?->isBuilding()),
                    ]),
                ]),
            Section::make('Warranty')
                ->schema([
                    Grid::make(3)->schema([
                        DatePicker::make('warranty_start')
                            ->label('Warranty Starts'),
                        DatePicker::make('warranty_end')
                            ->label('Warranty Ends')
                            ->afterOrEqual('warranty_start'),
                        TextInput::make('warranty_vendor')
                            ->label('Warranty Vendor')
                            ->maxLength(255),
                    ]),
                ]),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function placementSection(): array
    {
        return [
            Grid::make(2)->schema([
                Select::make('branch_id')
                    ->label('Branch')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live(),
                Select::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),
                Select::make('placement_type')
                    ->label('Placement Type')
                    ->options(PlacementType::class)
                    ->default(PlacementType::Warehouse)
                    ->required()
                    ->live()
                    ->disabledOn('edit')
                    ->helperText(fn (string $operation): ?string => $operation === 'edit'
                        ? 'An asset only moves through a handover or transfer document.'
                        : 'Where the asset sits when it is first recorded.'),
                Select::make('current_location_id')
                    ->label('Room / Warehouse')
                    ->options(fn (Get $get): array => self::locationOptions($get('branch_id')))
                    ->searchable()
                    ->disabledOn('edit')
                    ->required(fn (Get $get): bool => in_array($get('placement_type'), [
                        PlacementType::Location->value,
                        PlacementType::Warehouse->value,
                    ], strict: true))
                    ->visible(fn (Get $get): bool => in_array($get('placement_type'), [
                        PlacementType::Location->value,
                        PlacementType::Warehouse->value,
                    ], strict: true)),
                Select::make('current_employee_id')
                    ->label('Assigned Employee')
                    ->options(fn (Get $get): array => self::employeeOptions($get('branch_id')))
                    ->searchable()
                    ->disabledOn('edit')
                    ->required(fn (Get $get): bool => $get('placement_type') === PlacementType::Employee->value)
                    ->visible(fn (Get $get): bool => $get('placement_type') === PlacementType::Employee->value),
            ]),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function specSection(): array
    {
        return [
            Grid::make(2)
                ->schema(fn (Get $get): array => self::specFields($get('asset_category_id'))),
        ];
    }

    /**
     * @return array<int, mixed>
     */
    private static function attachmentSection(): array
    {
        return [
            SpatieMediaLibraryFileUpload::make('photos')
                ->label('Asset Photos')
                ->collection('photos')
                ->image()
                ->multiple()
                ->reorderable()
                ->maxFiles(8)
                ->columnSpanFull(),
            SpatieMediaLibraryFileUpload::make('documents')
                ->label('Documents')
                ->helperText('Invoice, warranty card, manual, and other supporting documents.')
                ->collection('documents')
                ->multiple()
                ->maxFiles(10)
                ->columnSpanFull(),
        ];
    }

    /**
     * Posted depreciation was computed from these values, so they freeze once any
     * month is posted — changing them afterwards would leave the ledger inconsistent.
     */
    private static function hasPostedDepreciation(?Asset $record): bool
    {
        if ($record === null || ! $record->exists) {
            return false;
        }

        return $record->depreciationEntries()
            ->whereHas('depreciationPeriod', fn (Builder $query) => $query->where('status', DepreciationPeriodStatus::Posted->value))
            ->exists();
    }

    /**
     * Builds the dynamic specification fields defined on the chosen category.
     *
     * @return array<int, mixed>
     */
    private static function specFields(mixed $categoryId): array
    {
        if (blank($categoryId)) {
            return [
                Text::make('Pick a category first to see its specification fields.')
                    ->columnSpanFull(),
            ];
        }

        $category = AssetCategory::find($categoryId);
        $fields = $category?->resolvedSpecFields() ?? [];

        if ($fields === []) {
            return [
                Text::make('This category has no specification fields yet. Add them under Asset Categories.')
                    ->columnSpanFull(),
            ];
        }

        return array_map(function (array $field) {
            $input = TextInput::make("specs.{$field['key']}")->label($field['label']);

            return match ($field['type'] ?? 'text') {
                'number' => $input->numeric(),
                'date' => DatePicker::make("specs.{$field['key']}")->label($field['label']),
                default => $input->maxLength(255),
            };
        }, $fields);
    }

    /**
     * @return array<int, string>
     */
    private static function locationOptions(mixed $branchId): array
    {
        if (blank($branchId)) {
            return [];
        }

        return Location::query()
            ->where('branch_id', $branchId)
            ->active()
            ->holdsAssets()
            ->defaultOrder()
            ->get()
            ->mapWithKeys(fn (Location $location): array => [$location->id => $location->full_name])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function employeeOptions(mixed $branchId): array
    {
        if (blank($branchId)) {
            return [];
        }

        return Employee::query()
            ->where('branch_id', $branchId)
            ->active()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $employee->display_name])
            ->all();
    }
}
