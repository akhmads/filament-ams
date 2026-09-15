<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Filament\Resources\AssetDisposals\AssetDisposalResource;
use App\Models\AssetDisposalLine;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every disposal the asset has been proposed on, including rejected ones, so the
 * history of the write-off decision stays visible.
 */
class DisposalsRelationManager extends RelationManager
{
    protected static string $relationship = 'disposalLines';

    protected static ?string $title = 'Disposal';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('assetDisposal'))
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('Never proposed for disposal')
            ->columns([
                TextColumn::make('assetDisposal.number')->label('Number')->badge()->color('gray'),
                TextColumn::make('assetDisposal.disposal_date')->label('Date')->date('d M Y'),
                TextColumn::make('assetDisposal.status')->label('Status')->badge(),
                TextColumn::make('method')->label('Method')->badge(),
                TextColumn::make('proceeds')->label('Proceeds')->money('IDR')->alignEnd(),
                TextColumn::make('commercial_book_value')->label('Book Value')->money('IDR')->placeholder('—')->alignEnd(),
                TextColumn::make('commercial_gain_loss')->label('Gain / Loss')->money('IDR')->placeholder('—')->alignEnd(),
            ])
            ->recordUrl(fn (AssetDisposalLine $record): string => AssetDisposalResource::getUrl('view', ['record' => $record->asset_disposal_id]))
            ->paginated(false);
    }
}
