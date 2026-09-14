<?php

namespace App\Filament\Resources\Assets\RelationManagers;

use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\WorkOrder;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Maintenance history for an asset. Work orders are managed from their own
 * resource, so this list only links there.
 */
class WorkOrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'workOrders';

    protected static ?string $title = 'Maintenance';

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('due_date', 'desc')
            ->emptyStateHeading('No maintenance recorded')
            ->columns([
                TextColumn::make('number')->label('Number')->badge()->color('gray'),
                TextColumn::make('title')->label('Title')->wrap(),
                TextColumn::make('due_date')->label('Due')->date('d M Y')->sortable(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('result')->label('Result')->badge()->placeholder('—'),
                TextColumn::make('actual_cost')->label('Actual Cost')->money('IDR')->placeholder('—')->alignEnd(),
                TextColumn::make('completed_at')->label('Completed')->dateTime('d M Y H:i')->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordUrl(fn (WorkOrder $record): string => WorkOrderResource::getUrl('view', ['record' => $record]))
            ->paginated([10, 25, 50]);
    }
}
