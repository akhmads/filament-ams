<?php

namespace App\Filament\Resources\StockDocuments\Schemas;

use App\Enums\StockDocumentType;
use App\Models\Employee;
use App\Support\StockItemOptions;
use App\Support\WarehouseOptions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class StockDocumentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Document')
                    ->description('Saving keeps a draft. Stock changes only when the document is posted.')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('number')
                                ->label('Number')
                                ->placeholder('Automatic')
                                ->disabled()
                                ->dehydrated(false),
                            Select::make('type')
                                ->label('Type')
                                ->options(StockDocumentType::class)
                                ->default(StockDocumentType::Receipt)
                                ->selectablePlaceholder(false)
                                ->required()
                                // The number prefix follows the type, so it is fixed once saved.
                                ->disabledOn('edit')
                                ->live(),
                            DatePicker::make('document_date')
                                ->label('Date')
                                ->default(now())
                                ->required(),
                            Select::make('location_id')
                                ->label(fn (Get $get): string => self::type($get('type')) === StockDocumentType::Transfer ? 'From Warehouse' : 'Warehouse')
                                ->options(fn (): array => WarehouseOptions::all())
                                ->searchable()
                                ->required()
                                ->live(),
                            Select::make('destination_location_id')
                                ->label('To Warehouse')
                                ->options(fn (): array => WarehouseOptions::all())
                                ->searchable()
                                ->different('location_id')
                                ->visible(fn (Get $get): bool => self::type($get('type')) === StockDocumentType::Transfer)
                                ->required(fn (Get $get): bool => self::type($get('type')) === StockDocumentType::Transfer),
                            Select::make('supplier_id')
                                ->label('Supplier')
                                ->relationship('supplier', 'name')
                                ->searchable()
                                ->preload()
                                ->visible(fn (Get $get): bool => self::type($get('type')) === StockDocumentType::Receipt),
                            TextInput::make('reference_number')
                                ->label('Delivery Note / Invoice No.')
                                ->maxLength(100)
                                ->visible(fn (Get $get): bool => self::type($get('type')) === StockDocumentType::Receipt),
                            Select::make('employee_id')
                                ->label('Issued To')
                                ->options(fn (): array => Employee::query()->active()->orderBy('name')->get()
                                    ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $employee->display_name])
                                    ->all())
                                ->searchable()
                                ->visible(fn (Get $get): bool => self::type($get('type')) === StockDocumentType::Issue),
                            Select::make('department_id')
                                ->label('Department')
                                ->relationship('department', 'name')
                                ->searchable()
                                ->preload()
                                ->visible(fn (Get $get): bool => self::type($get('type')) === StockDocumentType::Issue),
                        ]),
                        Textarea::make('notes')
                            ->label('Notes')
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
                                        ->live()
                                        ->columnSpan(2),
                                    TextInput::make('quantity')
                                        ->label(fn (Get $get): string => self::type($get('../../type'))->quantityLabel())
                                        ->helperText(fn (Get $get): ?string => StockItemOptions::onHandHint($get('stock_item_id'), $get('../../location_id')))
                                        ->numeric()
                                        ->rule('decimal:0,2')
                                        ->minValue(fn (Get $get): ?float => match (self::type($get('../../type'))) {
                                            StockDocumentType::Adjustment => null,
                                            StockDocumentType::Count => 0,
                                            default => 0.01,
                                        })
                                        ->required(),
                                    TextInput::make('unit_cost')
                                        ->label('Unit Cost')
                                        ->numeric()
                                        ->rule('decimal:0,2')
                                        ->minValue(0)
                                        ->prefix('Rp')
                                        ->visible(fn (Get $get): bool => self::type($get('../../type')) === StockDocumentType::Receipt)
                                        ->required(fn (Get $get): bool => self::type($get('../../type')) === StockDocumentType::Receipt),
                                ]),
                                TextInput::make('notes')
                                    ->label('Notes')
                                    ->maxLength(255),
                            ])
                            ->addActionLabel('Add Item')
                            ->defaultItems(1)
                            ->minItems(1)
                            ->reorderable(false),
                    ]),
            ]);
    }

    /**
     * The form holds the enum on edit and its value while creating.
     */
    private static function type(mixed $state): StockDocumentType
    {
        return $state instanceof StockDocumentType
            ? $state
            : (StockDocumentType::tryFrom((string) $state) ?? StockDocumentType::Receipt);
    }
}
