<?php

namespace App\Filament\Resources\WorkOrders\Tables;

use App\Enums\WorkOrderStatus;
use App\Filament\Actions\CompleteWorkOrderAction;
use App\Filament\Actions\StartWorkOrderAction;
use App\Models\WorkOrder;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class WorkOrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('due_date')
            ->emptyStateHeading('No work orders')
            ->emptyStateDescription('Maintenance plans open work orders as they fall due, or create one by hand.')
            ->columns([
                TextColumn::make('number')
                    ->label('Number')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('asset.code')
                    ->label('Asset')
                    ->description(fn (WorkOrder $record): ?string => $record->asset?->name)
                    ->searchable(['code', 'name']),
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('due_date')
                    ->label('Due')
                    ->date('d M Y')
                    ->color(fn (WorkOrder $record): ?string => $record->isOverdue() ? 'danger' : null)
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
                TextColumn::make('checklist_progress')
                    ->label('Checklist')
                    ->state(fn (WorkOrder $record): ?string => $record->checklist_items_count > 0
                        ? "{$record->done_checklist_items_count}/{$record->checklist_items_count}"
                        : null)
                    ->placeholder('—'),
                TextColumn::make('assignedTo.name')
                    ->label('Technician')
                    ->placeholder('Unassigned')
                    ->toggleable(),
                TextColumn::make('maintenancePlan.name')
                    ->label('Plan')
                    ->placeholder('Opened by hand')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime('d M Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(WorkOrderStatus::class),
                SelectFilter::make('assigned_to')
                    ->label('Technician')
                    ->relationship('assignedTo', 'name')
                    ->preload(),
                SelectFilter::make('maintenance_plan_id')
                    ->label('Plan')
                    ->relationship('maintenancePlan', 'name')
                    ->preload(),
                Filter::make('overdue')
                    ->label('Overdue')
                    ->query(fn (Builder $query): Builder => $query->overdue()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    StartWorkOrderAction::make(),
                    CompleteWorkOrderAction::make(),
                    EditAction::make()
                        ->visible(fn (WorkOrder $record): bool => $record->isEditable()),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
