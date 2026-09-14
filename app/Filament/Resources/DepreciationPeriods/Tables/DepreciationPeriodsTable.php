<?php

namespace App\Filament\Resources\DepreciationPeriods\Tables;

use App\Enums\DepreciationBook;
use App\Enums\DepreciationPeriodStatus;
use App\Filament\Actions\PostDepreciationAction;
use App\Filament\Actions\RecalculateDepreciationAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DepreciationPeriodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('period', 'desc')
            ->emptyStateHeading('No depreciation calculated yet')
            ->emptyStateDescription('Use "Calculate Month" to prepare a draft for review.')
            ->columns([
                TextColumn::make('period')->label('Month')->date('F Y')->sortable(),
                TextColumn::make('book')->label('Book')->badge(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('asset_count')->label('Assets')->numeric()->alignEnd(),
                TextColumn::make('total_amount')->label('Depreciation')->money('IDR')->alignEnd(),
                TextColumn::make('calculated_at')->label('Last Calculated')->since()->toggleable(),
                TextColumn::make('posted_at')->label('Posted')->dateTime('d M Y H:i')->placeholder('—'),
                TextColumn::make('postedBy.name')->label('Posted By')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('book')->label('Book')->options(DepreciationBook::class),
                SelectFilter::make('status')->label('Status')->options(DepreciationPeriodStatus::class),
            ])
            ->recordActions([
                ViewAction::make(),
                RecalculateDepreciationAction::make(),
                PostDepreciationAction::make(),
            ]);
    }
}
