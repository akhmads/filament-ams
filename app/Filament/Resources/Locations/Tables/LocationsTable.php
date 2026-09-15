<?php

namespace App\Filament\Resources\Locations\Tables;

use App\Enums\LocationType;
use App\Models\Location;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class LocationsTable
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
                    ->label('Location')
                    ->searchable(['name'])
                    ->wrap(),
                TextColumn::make('type')
                    ->label('Type')
                    ->badge(),
                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('pic_name')
                    ->label('Person in Charge')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
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
                SelectFilter::make('branch_id')
                    ->label('Branch')
                    ->relationship('branch', 'name')
                    ->preload(),
                SelectFilter::make('type')
                    ->label('Type')
                    ->options(LocationType::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    EditAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->before(function (DeleteBulkAction $action, $records): void {
                            // A location that still holds assets must not be deleted.
                            $blocked = $records->filter(fn (Location $location): bool => $location->assets()->exists());

                            if ($blocked->isNotEmpty()) {
                                $action->failureNotificationTitle('Some locations still hold assets and were not deleted.');
                                $action->cancel();
                            }
                        }),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
