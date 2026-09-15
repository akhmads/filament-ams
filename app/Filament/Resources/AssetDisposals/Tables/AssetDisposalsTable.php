<?php

namespace App\Filament\Resources\AssetDisposals\Tables;

use App\Enums\AssetDisposalStatus;
use App\Filament\Actions\PrintDisposalAction;
use App\Models\AssetDisposal;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AssetDisposalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(fn ($query) => $query->orderByDesc('disposal_date')->orderByDesc('id'))
            ->emptyStateHeading('No disposals')
            ->emptyStateDescription('Propose writing off assets that are sold, donated, scrapped or lost.')
            ->columns([
                TextColumn::make('number')
                    ->label('Number')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('disposal_date')
                    ->label('Date')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('reason')
                    ->label('Reason')
                    ->limit(60)
                    ->wrap(),
                TextColumn::make('lines_count')
                    ->label('Assets')
                    ->alignEnd(),
                TextColumn::make('recipient_name')
                    ->label('Buyer / Recipient')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(AssetDisposalStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                PrintDisposalAction::make(),
                EditAction::make()
                    ->visible(fn (AssetDisposal $record): bool => $record->isEditable()),
            ]);
    }
}
