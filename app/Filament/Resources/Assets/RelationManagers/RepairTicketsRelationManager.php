<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Filament\Resources\RepairTickets\RepairTicketResource;
use App\Models\RepairTicket;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Repair history for an asset. Tickets are handled from their own resource, so
 * this list only links there.
 */
class RepairTicketsRelationManager extends RelationManager
{
    protected static string $relationship = 'repairTickets';

    protected static ?string $title = 'Repairs';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('reported_at', 'desc')
            ->emptyStateHeading('No repairs recorded')
            ->columns([
                TextColumn::make('number')->label('Number')->badge()->color('gray'),
                TextColumn::make('title')->label('Problem')->wrap(),
                TextColumn::make('reported_at')->label('Reported')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('repair_type')->label('Repaired By')->badge()->placeholder('—'),
                TextColumn::make('actual_cost')->label('Actual Cost')->money('IDR')->placeholder('—')->alignEnd(),
                TextColumn::make('completed_at')->label('Finished')->dateTime('d M Y H:i')->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(fn (RepairTicket $record): string => RepairTicketResource::getUrl('view', ['record' => $record]))
            ->paginated([10, 25, 50]);
    }
}
