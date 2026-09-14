<?php

namespace App\Filament\Resources\Employees\Tables;

use App\Enums\EmployeeStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmployeesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('employee_number')
                    ->label('Employee ID')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('position')
                    ->label('Position')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('branch.name')
                    ->label('Branch')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('held_assets_count')
                    ->label('Assets Held')
                    ->counts('heldAssets')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray'),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Branch')
                    ->relationship('branch', 'name')
                    ->preload(),
                SelectFilter::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name')
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(EmployeeStatus::class),
                Filter::make('holding_assets')
                    ->label('Currently holding assets')
                    ->query(fn (Builder $query): Builder => $query->has('heldAssets')),
                Filter::make('resigned_with_assets')
                    ->label('Resigned but still holding assets')
                    ->query(fn (Builder $query): Builder => $query
                        ->where('status', EmployeeStatus::Resigned->value)
                        ->has('heldAssets')),
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
