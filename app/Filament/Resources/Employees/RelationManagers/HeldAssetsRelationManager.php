<?php

namespace App\Filament\Resources\Employees\RelationManagers;

use App\Filament\Resources\Assets\AssetResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Assets currently in an employee's hands. Read only — they move only
 * through a handover document.
 */
class HeldAssetsRelationManager extends RelationManager
{
    protected static string $relationship = 'heldAssets';

    protected static ?string $title = 'Assets Held';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->heading('Assets Held')
            ->description('All of these must be returned before the employee is marked as resigned.')
            ->emptyStateHeading('This employee is not holding any assets')
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->searchable(),
                TextColumn::make('name')
                    ->label('Asset Name')
                    ->searchable(),
                TextColumn::make('category.name')
                    ->label('Category'),
                TextColumn::make('serial_number')
                    ->label('Serial')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('condition')
                    ->label('Condition')
                    ->badge(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('acquisition_cost')
                    ->label('Acquisition Value')
                    ->money('IDR')
                    ->summarize(Sum::make()->money('IDR')->label('Total')),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('open')
                        ->label('Open')
                        ->icon('heroicon-m-arrow-top-right-on-square')
                        ->url(fn ($record): string => AssetResource::getUrl('view', ['record' => $record])),
                ]),
            ]);
    }
}
