<?php

namespace App\Filament\Resources\AssetCategories\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class AssetCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->defaultOrder())
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('full_name')
                    ->label('Category')
                    ->searchable(['name'])
                    ->wrap(),
                TextColumn::make('prefix')
                    ->label('Prefix')
                    ->badge()
                    ->color('warning'),
                TextColumn::make('useful_life_months')
                    ->label('Useful Life')
                    ->formatStateUsing(fn (?int $state): string => $state ? "{$state} bln" : '—')
                    ->toggleable(),
                TextColumn::make('depreciation_method')
                    ->label('Depr. Method')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('assets_count')
                    ->label('Asset')
                    ->counts('assets')
                    ->badge()
                    ->color('info'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_depreciable')
                    ->label('Depreciable'),
                TernaryFilter::make('requires_maintenance')
                    ->label('Needs Maintenance'),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
