<?php

namespace App\Filament\Resources\DepreciationPeriods\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The asset-by-asset breakdown of a period. Read only: entries change only by
 * recalculating a draft.
 */
class EntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'entries';

    protected static ?string $title = 'Entries';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            // Soft-deleted assets keep their history visible.
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['asset' => fn ($asset) => $asset->withTrashed()]))
            ->defaultSort('asset_id')
            ->columns([
                TextColumn::make('asset.code')->label('Asset Code')->badge()->color('gray')->searchable(),
                TextColumn::make('asset.name')->label('Asset')->searchable()->wrap(),
                TextColumn::make('opening_book_value')->label('Opening')->money('IDR')->alignEnd(),
                TextColumn::make('amount')->label('Depreciation')->money('IDR')->alignEnd()
                    ->summarize(Sum::make()->money('IDR')->label('Total')),
                TextColumn::make('accumulated')->label('Accumulated')->money('IDR')->alignEnd(),
                TextColumn::make('closing_book_value')->label('Book Value')->money('IDR')->alignEnd(),
                IconColumn::make('is_final')->label('Final Month')->boolean(),
            ])
            ->paginated([25, 50, 100]);
    }
}
