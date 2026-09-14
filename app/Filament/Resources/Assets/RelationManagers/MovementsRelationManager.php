<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Movement history for an asset. Read only — the ledger is never edited.
 */
class MovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'movements';

    protected static ?string $title = 'Movement History';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Movement History')
            ->description('These records are permanent. Corrections are made by recording a new movement.')
            ->defaultSort('moved_at', 'desc')
            ->columns([
                TextColumn::make('moved_at')
                    ->label('Time')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                TextColumn::make('movement_type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('from_description')
                    ->label('From')
                    ->placeholder('—'),
                TextColumn::make('to_description')
                    ->label('To'),
                TextColumn::make('status_after')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('condition_after')
                    ->label('Condition')
                    ->badge(),
                TextColumn::make('performedBy.name')
                    ->label('Recorded By')
                    ->placeholder('System')
                    ->toggleable(),
                TextColumn::make('notes')
                    ->label('Notes')
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(null)
            ->paginated([10, 25, 50]);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
