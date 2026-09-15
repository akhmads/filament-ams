<?php

namespace App\Filament\Resources\Assets\Tables;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\PlacementType;
use App\Filament\Actions\PrintAssetLabelsAction;
use App\Filament\Actions\PrintAssetLabelsBulkAction;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('code')
            ->persistFiltersInSession()
            ->columns([
                TextColumn::make('code')
                    ->label('Asset Code')
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Name')
                    ->description(fn ($record): ?string => $record->serial_number
                        ? "SN: {$record->serial_number}"
                        : null)
                    ->searchable(['name', 'serial_number'])
                    ->sortable()
                    ->wrap(),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('brand.name')
                    ->label('Brand')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('placement_type')
                    ->label('Placement')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('current_holder')
                    ->label('Position')
                    ->description(fn ($record): ?string => $record->branch?->name)
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('condition')
                    ->label('Condition')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('acquisition_date')
                    ->label('Acquired')
                    ->date('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('acquisition_cost')
                    ->label('Acquisition Value')
                    ->money('IDR')
                    ->sortable()
                    ->summarize(Sum::make()->money('IDR')->label('Total'))
                    ->toggleable(),
                TextColumn::make('warranty_end')
                    ->label('Warranty Until')
                    ->date('d M Y')
                    ->placeholder('—')
                    ->color(fn ($record): ?string => $record->warranty_end?->isPast() ? 'danger' : null)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Branch')
                    ->relationship('branch', 'name')
                    ->preload()
                    ->multiple(),
                SelectFilter::make('asset_category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->multiple(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(AssetStatus::class)
                    ->multiple(),
                SelectFilter::make('condition')
                    ->label('Condition')
                    ->options(AssetCondition::class)
                    ->multiple(),
                SelectFilter::make('placement_type')
                    ->label('Placement')
                    ->options(PlacementType::class)
                    ->multiple(),
                SelectFilter::make('current_employee_id')
                    ->label('Held By')
                    ->relationship('currentEmployee', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('current_location_id')
                    ->label('Room')
                    ->relationship('currentLocation', 'name')
                    ->searchable()
                    ->preload(),
                Filter::make('warranty_expiring')
                    ->label('Warranty expiring within 60 days')
                    ->query(fn (Builder $query): Builder => $query->warrantyExpiringWithin(60)),
                Filter::make('never_labelled')
                    ->label('Label never printed')
                    ->query(fn (Builder $query): Builder => $query->whereNull('label_printed_at')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                    PrintAssetLabelsAction::make(),
                ]),
            ])
            ->toolbarActions([
                PrintAssetLabelsBulkAction::make(),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
