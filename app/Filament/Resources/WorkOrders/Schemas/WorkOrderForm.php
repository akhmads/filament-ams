<?php

namespace App\Filament\Resources\WorkOrders\Schemas;

use App\Support\AssetOptions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkOrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Work Order')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('number')
                                ->label('Number')
                                ->placeholder('Automatic')
                                ->disabled()
                                ->dehydrated(false),
                            Select::make('asset_id')
                                ->label('Asset')
                                ->searchable()
                                ->getSearchResultsUsing(fn (string $search): array => AssetOptions::search($search))
                                ->getOptionLabelUsing(fn (mixed $value): ?string => AssetOptions::label($value))
                                ->required(),
                            TextInput::make('title')
                                ->label('Title')
                                ->required()
                                ->maxLength(255),
                            DatePicker::make('due_date')
                                ->label('Due Date')
                                ->default(now()->toDateString())
                                ->required(),
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
                        Textarea::make('instructions')
                            ->label('Instructions')
                            ->rows(3),
                    ]),
                Section::make('Checklist')
                    ->schema([
                        Repeater::make('checklistItems')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('sort_order')
                            ->schema([
                                TextInput::make('task')
                                    ->hiddenLabel()
                                    ->required()
                                    ->maxLength(255),
                            ])
                            ->addActionLabel('Add Task')
                            ->defaultItems(0),
                    ]),
            ]);
    }
}
