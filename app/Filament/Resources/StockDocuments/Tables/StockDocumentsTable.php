<?php

namespace App\Filament\Resources\StockDocuments\Tables;

use App\Enums\StockDocumentStatus;
use App\Enums\StockDocumentType;
use App\Filament\Actions\PostStockDocumentAction;
use App\Models\StockDocument;
use App\Support\WarehouseOptions;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockDocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(fn ($query) => $query->orderByDesc('document_date')->orderByDesc('id'))
            ->emptyStateHeading('No stock documents')
            ->emptyStateDescription('Receive goods into a warehouse to start keeping stock.')
            ->columns([
                TextColumn::make('number')
                    ->label('Number')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('document_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('location.name')
                    ->label('Warehouse')
                    ->description(fn (StockDocument $record): ?string => $record->destinationLocation === null
                        ? null
                        : "→ {$record->destinationLocation->name}"),
                TextColumn::make('lines_count')
                    ->label('Items')
                    ->alignEnd(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('posted_at')
                    ->label('Posted')
                    ->dateTime('d M Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(StockDocumentType::class),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(StockDocumentStatus::class),
                SelectFilter::make('location_id')
                    ->label('Warehouse')
                    ->options(fn (): array => WarehouseOptions::all()),
            ])
            ->recordActions([
                ViewAction::make(),
                PostStockDocumentAction::make(),
                EditAction::make()
                    ->visible(fn (StockDocument $record): bool => $record->isEditable()),
            ]);
    }
}
