<?php

namespace App\Filament\Resources\AssetAudits\Schemas;

use App\Models\AssetCategory;
use App\Models\Location;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AssetAuditForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Audit')
                    ->description('Saving lists every asset in service within the scope, as the register stands now. The scope cannot change afterwards.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('number')
                                ->label('Number')
                                ->placeholder('Automatic')
                                ->disabled()
                                ->dehydrated(false),
                            TextInput::make('title')
                                ->label('Title')
                                ->placeholder('Year-end stock take, Head Office')
                                ->required()
                                ->maxLength(255),
                            Select::make('location_id')
                                ->label('Location')
                                ->helperText('Includes every room under it.')
                                ->options(fn (): array => Location::query()->active()->defaultOrder()->get()
                                    ->mapWithKeys(fn (Location $location): array => [$location->id => $location->full_name])
                                    ->all())
                                ->searchable()
                                ->requiredWithout('department_id'),
                            Select::make('department_id')
                                ->label('Department')
                                ->helperText('Includes assets held by its employees.')
                                ->relationship('department', 'name')
                                ->searchable()
                                ->preload()
                                ->requiredWithout('location_id'),
                            Select::make('asset_category_id')
                                ->label('Category')
                                ->helperText('Optional. Includes every subcategory.')
                                ->options(fn (): array => AssetCategory::query()->defaultOrder()->get()
                                    ->mapWithKeys(fn (AssetCategory $category): array => [$category->id => $category->full_name])
                                    ->all())
                                ->searchable(),
                        ]),
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2),
                    ]),
            ]);
    }
}
