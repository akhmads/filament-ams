<?php

namespace App\Filament\Resources\ItemRequests\Tables;

use App\Enums\ItemRequestStatus;
use App\Models\ItemRequest;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ItemRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(fn ($query) => $query->orderByDesc('request_date')->orderByDesc('id'))
            ->emptyStateHeading('No item requests')
            ->emptyStateDescription('Record an employee\'s request for consumables to have it approved and issued.')
            ->columns([
                TextColumn::make('number')
                    ->label('Number')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('employee.name')
                    ->label('Requested By')
                    ->searchable(),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('request_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('needed_by')
                    ->label('Needed By')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('lines_count')
                    ->label('Items')
                    ->alignEnd(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(ItemRequestStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (ItemRequest $record): bool => $record->isEditable()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
