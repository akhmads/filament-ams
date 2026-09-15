<?php

namespace App\Filament\Resources\AssetDisposals\Schemas;

use App\Enums\DisposalMethod;
use App\Support\DisposalAssetOptions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Livewire\Component;

class AssetDisposalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Proposal')
                    ->description('Assets stay on the books until the approved disposal is completed.')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('number')
                                ->label('Number')
                                ->placeholder('Automatic')
                                ->disabled()
                                ->dehydrated(false),
                            DatePicker::make('disposal_date')
                                ->label('Disposal Date')
                                ->helperText('The month of disposal is not depreciated.')
                                ->default(now())
                                ->required(),
                            TextInput::make('recipient_name')
                                ->label('Buyer / Recipient')
                                ->maxLength(255),
                            TextInput::make('reference_number')
                                ->label('Auction / Invoice No.')
                                ->maxLength(100),
                        ]),
                        Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->rows(2),
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2),
                    ]),
                Section::make('Assets')
                    ->schema([
                        Repeater::make('lines')
                            ->hiddenLabel()
                            ->relationship()
                            ->schema([
                                Grid::make(4)->schema([
                                    Select::make('asset_id')
                                        ->label('Asset')
                                        ->helperText('Only assets that are available, in storage, retired or lost, and held by nobody.')
                                        ->searchable()
                                        ->getSearchResultsUsing(fn (string $search, Component $livewire): array => DisposalAssetOptions::search(
                                            $search,
                                            $livewire instanceof EditRecord ? (int) $livewire->getRecord()->getKey() : null,
                                        ))
                                        ->getOptionLabelUsing(fn (mixed $value): ?string => DisposalAssetOptions::label($value))
                                        ->required()
                                        ->distinct()
                                        ->columnSpan(2),
                                    Select::make('method')
                                        ->label('Method')
                                        ->options(DisposalMethod::class)
                                        ->default(DisposalMethod::Scrapped)
                                        ->selectablePlaceholder(false)
                                        ->required()
                                        ->live(),
                                    TextInput::make('proceeds')
                                        ->label('Proceeds')
                                        ->numeric()
                                        ->rule('decimal:0,2')
                                        ->minValue(0)
                                        ->prefix('Rp')
                                        ->default(0)
                                        ->visible(fn (Get $get): bool => self::method($get('method'))->hasProceeds())
                                        ->required(fn (Get $get): bool => self::method($get('method'))->hasProceeds()),
                                ]),
                                TextInput::make('notes')
                                    ->label('Notes')
                                    ->maxLength(255),
                                // Kept when the proposal starts from a repair that could not be done.
                                Hidden::make('repair_ticket_id'),
                            ])
                            ->addActionLabel('Add Asset')
                            ->defaultItems(1)
                            ->minItems(1)
                            ->reorderable(false),
                    ]),
            ]);
    }

    private static function method(mixed $state): DisposalMethod
    {
        return $state instanceof DisposalMethod
            ? $state
            : (DisposalMethod::tryFrom((string) $state) ?? DisposalMethod::Scrapped);
    }
}
