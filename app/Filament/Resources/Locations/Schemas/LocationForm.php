<?php

namespace App\Filament\Resources\Locations\Schemas;

use App\Enums\LocationType;
use App\Models\Location;
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

class LocationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Location Identity')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('branch_id')
                                ->label('Branch')
                                ->relationship('branch', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('parent_id', null)),
                            Select::make('parent_id')
                                ->label('Parent')
                                ->helperText('Leave empty for a top-level location, e.g. a site.')
                                ->options(fn (Get $get, ?Location $record): array => self::parentOptions(
                                    branchId: $get('branch_id'),
                                    exclude: $record,
                                ))
                                ->searchable(),
                            TextInput::make('code')
                                ->label('Location Code')
                                ->required()
                                ->maxLength(30)
                                ->extraInputAttributes(['style' => 'text-transform:uppercase'])
                                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? strtoupper($state) : null)
                                ->unique(
                                    modifyRuleUsing: fn ($rule, Get $get) => $rule->where('branch_id', $get('branch_id')),
                                    ignoreRecord: true,
                                ),
                            TextInput::make('name')
                                ->label('Location Name')
                                ->required()
                                ->maxLength(255),
                            Select::make('type')
                                ->label('Type')
                                ->options(LocationType::class)
                                ->default(LocationType::Room)
                                ->required()
                                ->helperText('Only Rooms and Warehouses can hold assets.'),
                            TextInput::make('pic_name')
                                ->label('Person in Charge')
                                ->maxLength(255),
                        ]),
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(2)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                    ]),
            ]);
    }

    /**
     * Parent choices stay within the same branch and must not create a cycle
     * with the location's own descendants.
     *
     * @return array<int, string>
     */
    private static function parentOptions(mixed $branchId, ?Location $exclude): array
    {
        if (blank($branchId)) {
            return [];
        }

        return Location::query()
            ->where('branch_id', $branchId)
            ->when($exclude?->exists, fn (Builder $query) => $query
                ->whereNot('id', $exclude->id)
                ->whereNotDescendantOf($exclude))
            ->defaultOrder()
            ->get()
            ->mapWithKeys(fn (Location $location): array => [$location->id => $location->full_name])
            ->all();
    }
}
