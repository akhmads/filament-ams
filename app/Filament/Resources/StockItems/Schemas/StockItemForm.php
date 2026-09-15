<?php

namespace App\Filament\Resources\StockItems\Schemas;

use App\Enums\StockItemType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StockItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Stock Item')
                    ->description('Stock itself changes only by posting stock documents.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('code')
                                ->label('Code')
                                ->required()
                                ->maxLength(30)
                                ->unique(ignoreRecord: true),
                            TextInput::make('name')
                                ->label('Name')
                                ->required()
                                ->maxLength(255),
                            Select::make('item_type')
                                ->label('Type')
                                ->options(StockItemType::class)
                                ->default(StockItemType::Consumable)
                                ->selectablePlaceholder(false)
                                ->required(),
                            TextInput::make('unit')
                                ->label('Unit')
                                ->placeholder('pcs')
                                ->datalist(['pcs', 'unit', 'set', 'box', 'pack', 'ream', 'roll', 'litre', 'kg', 'metre'])
                                ->required()
                                ->maxLength(20),
                            TextInput::make('minimum_quantity')
                                ->label('Minimum Stock')
                                ->helperText('Across all warehouses. Leave at 0 for no alert.')
                                ->numeric()
                                ->minValue(0)
                                ->rule('decimal:0,2')
                                ->default(0)
                                ->required(),
                            TextInput::make('reorder_quantity')
                                ->label('Reorder Quantity')
                                ->helperText('The usual quantity to buy when restocking.')
                                ->numeric()
                                ->minValue(0)
                                ->rule('decimal:0,2'),
                            Toggle::make('is_active')
                                ->label('Active')
                                ->helperText('Inactive items can still be issued and counted, but not received.')
                                ->default(true),
                        ]),
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3),
                    ]),
            ]);
    }
}
