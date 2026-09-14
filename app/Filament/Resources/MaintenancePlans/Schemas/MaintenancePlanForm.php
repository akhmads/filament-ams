<?php

namespace App\Filament\Resources\MaintenancePlans\Schemas;

use App\Enums\MaintenanceIntervalUnit;
use App\Models\AssetCategory;
use App\Models\MaintenancePlan;
use App\Support\AssetOptions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class MaintenancePlanForm
{
    public const TARGET_ASSET = 'asset';

    public const TARGET_CATEGORY = 'category';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Plan')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Plan Name')
                                ->placeholder('e.g. Quarterly AC service')
                                ->required()
                                ->maxLength(255),
                            Toggle::make('is_active')
                                ->label('Active')
                                ->helperText('Inactive plans open no new work orders.')
                                ->default(true)
                                ->inline(false),
                            ToggleButtons::make('target')
                                ->label('Applies To')
                                ->options([
                                    self::TARGET_ASSET => 'One asset',
                                    self::TARGET_CATEGORY => 'Every asset in a category',
                                ])
                                ->default(self::TARGET_ASSET)
                                ->inline()
                                ->required()
                                ->live()
                                ->afterStateHydrated(function (ToggleButtons $component, ?MaintenancePlan $record): void {
                                    if ($record !== null) {
                                        $component->state($record->isForCategory() ? self::TARGET_CATEGORY : self::TARGET_ASSET);
                                    }
                                })
                                ->columnSpanFull(),
                            Select::make('asset_id')
                                ->label('Asset')
                                ->searchable()
                                ->getSearchResultsUsing(fn (string $search): array => AssetOptions::search($search))
                                ->getOptionLabelUsing(fn (mixed $value): ?string => AssetOptions::label($value))
                                ->required(fn (Get $get): bool => $get('target') === self::TARGET_ASSET)
                                ->visible(fn (Get $get): bool => $get('target') === self::TARGET_ASSET),
                            Select::make('asset_category_id')
                                ->label('Category')
                                ->options(fn (): array => self::categoryOptions())
                                ->searchable()
                                ->required(fn (Get $get): bool => $get('target') === self::TARGET_CATEGORY)
                                ->visible(fn (Get $get): bool => $get('target') === self::TARGET_CATEGORY),
                            Toggle::make('include_subcategories')
                                ->label('Include Subcategories')
                                ->default(true)
                                ->inline(false)
                                ->visible(fn (Get $get): bool => $get('target') === self::TARGET_CATEGORY),
                        ]),
                    ]),
                Section::make('Schedule')
                    ->description('Due dates follow the calendar: each one is the previous due date plus the interval, however late the work was done.')
                    ->schema([
                        Grid::make(4)->schema([
                            TextInput::make('interval_value')
                                ->label('Every')
                                ->integer()
                                ->minValue(1)
                                ->maxValue(999)
                                ->default(3)
                                ->required(),
                            Select::make('interval_unit')
                                ->label('Unit')
                                ->options(MaintenanceIntervalUnit::class)
                                ->default(MaintenanceIntervalUnit::Month->value)
                                ->selectablePlaceholder(false)
                                ->required(),
                            DatePicker::make('start_date')
                                ->label('First Due Date')
                                ->helperText('Assets acquired later are first due one interval after acquisition.')
                                ->default(now()->toDateString())
                                ->required(),
                            TextInput::make('lead_days')
                                ->label('Open Work Order')
                                ->integer()
                                ->minValue(0)
                                ->maxValue(365)
                                ->default(7)
                                ->suffix('days before')
                                ->required(),
                        ]),
                    ]),
                Section::make('Work')
                    ->description('Copied onto each work order when it opens, so later changes do not rewrite past work.')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('assigned_to')
                                ->label('Technician')
                                ->relationship('assignedTo', 'name')
                                ->searchable()
                                ->preload(),
                            Select::make('supplier_id')
                                ->label('Service Vendor')
                                ->relationship('supplier', 'name')
                                ->searchable()
                                ->preload(),
                            TextInput::make('estimated_cost')
                                ->label('Estimated Cost')
                                ->numeric()
                                ->minValue(0)
                                ->prefix('Rp')
                                ->default(0),
                            TextInput::make('estimated_minutes')
                                ->label('Estimated Duration')
                                ->integer()
                                ->minValue(0)
                                ->suffix('minutes'),
                        ]),
                        Repeater::make('checklist')
                            ->label('Checklist')
                            ->simple(
                                TextInput::make('task')
                                    ->required()
                                    ->maxLength(255),
                            )
                            ->addActionLabel('Add Task')
                            ->defaultItems(0),
                        Textarea::make('notes')
                            ->label('Instructions')
                            ->rows(3),
                    ]),
            ]);
    }

    /**
     * Keeps exactly one target filled in: switching a plan from an asset to a
     * category must clear the asset, and the other way round.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeTarget(array $data): array
    {
        $target = $data['target'] ?? self::TARGET_ASSET;
        unset($data['target']);

        if ($target === self::TARGET_CATEGORY) {
            $data['asset_id'] = null;
        } else {
            $data['asset_category_id'] = null;
            $data['include_subcategories'] = true;
        }

        return $data;
    }

    /**
     * @return array<int, string>
     */
    private static function categoryOptions(): array
    {
        return AssetCategory::query()
            ->where('is_active', true)
            ->defaultOrder()
            ->get()
            ->mapWithKeys(fn (AssetCategory $category): array => [$category->id => $category->full_name])
            ->all();
    }
}
