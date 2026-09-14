<?php

namespace App\Filament\Resources\AssetCategories\Schemas;

use App\Enums\DepreciationMethod;
use App\Models\AssetCategory;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
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
