<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Filament\Resources\Assets\AssetResource;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Components or sub-assets attached to this asset.
 */
class ComponentsRelationManager extends RelationManager
{
    protected static string $relationship = 'components';

    protected static ?string $title = 'Components';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('code')
            ->heading('Components')
            ->emptyStateHeading('This asset has no components yet')
            ->emptyStateDescription('Make another asset a component by setting its Parent Asset field.')
            ->columns([
                TextColumn::make('code')->label('Code')->badge()->searchable(),
                TextColumn::make('name')->label('Name')->searchable(),
                TextColumn::make('category.name')->label('Category'),
                TextColumn::make('serial_number')->label('Serial')->placeholder('—'),
                TextColumn::make('condition')->label('Condition')->badge(),
                TextColumn::make('acquisition_cost')->label('Value')->money('IDR'),
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

    public function isReadOnly(): bool
    {
        return true;
    }
}
