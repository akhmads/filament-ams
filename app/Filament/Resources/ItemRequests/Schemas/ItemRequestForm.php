<?php

namespace App\Filament\Resources\ItemRequests\Schemas;

use App\Models\Employee;
use App\Support\StockItemOptions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ItemRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request')
                    ->description('Recorded on behalf of the employee. Goods are issued once the request is approved.')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('number')
                                ->label('Number')
                                ->placeholder('Automatic')
                                ->disabled()
                                ->dehydrated(false),
                            Select::make('employee_id')
                                ->label('Requested By')
                                ->options(fn (): array => Employee::query()->active()->orderBy('name')->get()
                                    ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $employee->display_name])
                                    ->all())
                                ->searchable()
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn (Set $set, mixed $state) => $set(
                                    'department_id',
                                    blank($state) ? null : Employee::query()->whereKey($state)->value('department_id'),
                                )),
                            Select::make('department_id')
                                ->label('Department')
                                ->relationship('department', 'name')
                                ->searchable()
                                ->preload(),
                            DatePicker::make('request_date')
                                ->label('Request Date')
                                ->default(now())
                                ->required(),
                            DatePicker::make('needed_by')
                                ->label('Needed By')
                                ->afterOrEqual('request_date'),
                        ]),
                        Textarea::make('purpose')
                            ->label('Purpose')
                            ->rows(2),
                    ]),
                Section::make('Items')
                    ->schema([
                        Repeater::make('lines')
                            ->hiddenLabel()
                            ->relationship()
                            ->schema([
                                Grid::make(4)->schema([
                                    Select::make('stock_item_id')
                                        ->label('Item')
                                        ->searchable()
                                        ->getSearchResultsUsing(fn (string $search): array => StockItemOptions::search($search))
                                        ->getOptionLabelUsing(fn (mixed $value): ?string => StockItemOptions::label($value))
                                        ->required()
                                        ->distinct()
                                        ->columnSpan(2),
                                    TextInput::make('quantity')
                                        ->label('Quantity')
                                        ->numeric()
                                        ->rule('decimal:0,2')
                                        ->minValue(0.01)
                                        ->required(),
                                    TextInput::make('notes')
                                        ->label('Notes')
                                        ->maxLength(255),
                                ]),
                            ])
                            ->addActionLabel('Add Item')
                            ->defaultItems(1)
                            ->minItems(1)
                            ->reorderable(false),
                    ]),
            ]);
    }
}
