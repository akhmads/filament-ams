<?php

namespace App\Filament\Resources\AssetCategories\Schemas;

use App\Enums\DepreciationMethod;
use App\Enums\FiscalAssetGroup;
use App\Models\AssetCategory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class AssetCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Category Identity')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('parent_id')
                                ->label('Parent Category')
                                ->options(fn (?AssetCategory $record): array => self::parentOptions($record))
                                ->searchable()
                                ->helperText('Leave empty for a top-level category.'),
                            TextInput::make('code')
                                ->label('Category Code')
                                ->required()
                                ->maxLength(20)
                                ->unique(ignoreRecord: true),
                            TextInput::make('name')
                                ->label('Category Name')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('prefix')
                                ->label('Asset Code Prefix')
                                ->helperText('Appears at the start of the asset code, e.g. LAP for laptops.')
                                ->required()
                                ->maxLength(10)
                                ->alphaNum()
                                ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper($state) : null),
                        ]),
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
                Section::make('Depreciation Defaults')
                    ->description('These values pre-fill the form for new assets in this category and can still be changed per asset.')
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('is_depreciable')
                                ->label('Depreciable')
                                ->default(true)
                                ->live(),
                            Select::make('depreciation_method')
                                ->label('Depreciation Method')
                                ->options(DepreciationMethod::class)
                                ->default(DepreciationMethod::StraightLine)
                                ->required()
                                ->visible(fn (Get $get): bool => (bool) $get('is_depreciable')),
                            TextInput::make('useful_life_months')
                                ->label('Useful Life (months)')
                                ->numeric()
                                ->minValue(1)
                                ->default(48)
                                ->required()
                                ->visible(fn (Get $get): bool => (bool) $get('is_depreciable')),
                            TextInput::make('residual_percent')
                                ->label('Residual Value (%)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(100)
                                ->default(0)
                                ->visible(fn (Get $get): bool => (bool) $get('is_depreciable')),
                        ]),
                        Toggle::make('requires_maintenance')
                            ->label('Requires Scheduled Maintenance')
                            ->helperText('Marks categories whose maintenance will be scheduled in a later phase.'),
                    ]),
                Section::make('Tax Depreciation')
                    ->description('Used for the fiscal book. Useful life and rates are set by UU PPh Pasal 11; asset types not listed in PMK 72/2023 use Group 3.')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('fiscal_group')
                                ->label('Tax Asset Group')
                                ->options(FiscalAssetGroup::class)
                                ->placeholder('Not set')
                                ->live()
                                ->afterStateUpdated(function (Set $set, mixed $state): void {
                                    if (self::fiscalGroup($state)?->isBuilding()) {
                                        $set('fiscal_method', DepreciationMethod::StraightLine->value);
                                    }
                                })
                                ->helperText(fn (Get $get): string => self::fiscalRateHint($get('fiscal_group'))),
                            Select::make('fiscal_method')
                                ->label('Tax Method')
                                ->options(self::fiscalMethodOptions())
                                ->default(DepreciationMethod::StraightLine->value)
                                ->required()
                                ->disableOptionWhen(fn (string $value, Get $get): bool => $value === DepreciationMethod::DoubleDeclining->value
                                    && (bool) self::fiscalGroup($get('fiscal_group'))?->isBuilding())
                                ->helperText('Buildings may only use straight line.'),
                        ]),
                    ]),
                Section::make('Journal Accounts')
                    ->description('Accounts printed on the depreciation journal for assets in this category.')
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('expense_account_code')
                                ->label('Expense Account Code')
                                ->maxLength(30),
                            TextInput::make('expense_account_name')
                                ->label('Expense Account Name')
                                ->maxLength(255),
                            TextInput::make('accumulated_account_code')
                                ->label('Accumulated Depreciation Account Code')
                                ->maxLength(30),
                            TextInput::make('accumulated_account_name')
                                ->label('Accumulated Depreciation Account Name')
                                ->maxLength(255),
                        ]),
                    ]),
                Section::make('Specification Fields')
                    ->description('Extra fields specific to this category, e.g. RAM and CPU for laptops. Leave empty to inherit from the parent category.')
                    ->collapsed()
                    ->schema([
                        Repeater::make('spec_fields')
                            ->label('Fields')
                            ->hiddenLabel()
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('key')
                                        ->label('Key')
                                        ->required()
                                        ->regex('/^[a-z0-9_]+$/')
                                        ->helperText('lowercase and underscores'),
                                    TextInput::make('label')
                                        ->label('Label')
                                        ->required(),
                                    Select::make('type')
                                        ->label('Type')
                                        ->options([
                                            'text' => 'Text',
                                            'number' => 'Number',
                                            'date' => 'Date',
                                        ])
                                        ->default('text')
                                        ->required(),
                                ]),
                            ])
                            ->addActionLabel('Add Field')
                            ->reorderable()
                            ->defaultItems(0),
                    ]),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
            ]);
    }

    /**
     * The methods Pasal 11 recognises for tax: straight line and declining balance.
     *
     * @return array<string, string>
     */
    private static function fiscalMethodOptions(): array
    {
        return collect(DepreciationMethod::cases())
            ->filter(fn (DepreciationMethod $method): bool => $method->isAllowedForFiscal())
            ->mapWithKeys(fn (DepreciationMethod $method): array => [$method->value => $method->getLabel()])
            ->all();
    }

    private static function fiscalGroup(mixed $state): ?FiscalAssetGroup
    {
        return $state instanceof FiscalAssetGroup ? $state : FiscalAssetGroup::tryFrom((string) $state);
    }

    private static function fiscalRateHint(mixed $state): string
    {
        $group = self::fiscalGroup($state);

        if ($group === null) {
            return 'Assets in this category stay out of the fiscal book until a group is set, unless the asset sets its own.';
        }

        return $group->decliningBalanceRate() === null
            ? "Straight line {$group->straightLineRate()} a year."
            : "Straight line {$group->straightLineRate()} or declining balance {$group->decliningBalanceRate()} a year.";
    }

    /**
     * @return array<int, string>
     */
    private static function parentOptions(?AssetCategory $exclude): array
    {
        return AssetCategory::query()
            ->when($exclude?->exists, fn (Builder $query) => $query
                ->whereNot('id', $exclude->id)
                ->whereNotDescendantOf($exclude))
            ->defaultOrder()
            ->get()
            ->mapWithKeys(fn (AssetCategory $category): array => [$category->id => $category->full_name])
            ->all();
    }
}
