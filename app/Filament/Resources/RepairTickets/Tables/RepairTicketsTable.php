<?php

namespace App\Filament\Resources\RepairTickets\Tables;

use App\Enums\RepairPriority;
use App\Enums\RepairTicketStatus;
use App\Enums\RepairType;
use App\Models\RepairTicket;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class RepairTicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('reported_at', 'desc')
            ->emptyStateHeading('No repair tickets')
            ->emptyStateDescription('Report damage from here or from the asset\'s page.')
            ->columns([
                TextColumn::make('number')
                    ->label('Number')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('asset.code')
                    ->label('Asset')
                    ->description(fn (RepairTicket $record): ?string => $record->asset?->name)
                    ->searchable(['code', 'name']),
                TextColumn::make('title')
                    ->label('Problem')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('priority')
                    ->label('Priority')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('reported_at')
                    ->label('Reported')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('assignedTo.name')
                    ->label('Technician')
                    ->placeholder('Unassigned')
                    ->toggleable(),
                TextColumn::make('repair_type')
                    ->label('Repaired By')
                    ->badge()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_under_warranty')
                    ->label('Warranty')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(RepairTicketStatus::class),
                SelectFilter::make('priority')
                    ->label('Priority')
                    ->options(RepairPriority::class),
                SelectFilter::make('repair_type')
                    ->label('Repaired By')
                    ->options(RepairType::class),
                SelectFilter::make('assigned_to')
                    ->label('Technician')
                    ->relationship('assignedTo', 'name')
                    ->preload(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (RepairTicket $record): bool => $record->isEditable()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
