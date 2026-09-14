<?php

namespace App\Filament\Resources\WorkOrders\Schemas;

use App\Enums\WorkOrderStatus;
use App\Filament\Resources\Assets\AssetResource;
use App\Models\WorkOrder;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkOrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Work Order')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('number')->label('Number')->badge()->copyable(),
                            TextEntry::make('status')->label('Status')->badge(),
                            TextEntry::make('due_date')->label('Due Date')->date('d M Y')
                                ->color(fn (WorkOrder $record): ?string => $record->isOverdue() ? 'danger' : null),
                            TextEntry::make('maintenancePlan.name')->label('Plan')->placeholder('Opened by hand'),
                            TextEntry::make('asset.code')->label('Asset')->badge()->color('gray')
                                ->belowContent(fn (WorkOrder $record): ?string => $record->asset?->name)
                                ->url(fn (WorkOrder $record): ?string => $record->asset === null
                                    ? null
                                    : AssetResource::getUrl('view', ['record' => $record->asset])),
                            TextEntry::make('title')->label('Title')->columnSpan(3),
                            TextEntry::make('assignedTo.name')->label('Technician')->placeholder('Unassigned'),
                            TextEntry::make('supplier.name')->label('Service Vendor')->placeholder('—'),
                            TextEntry::make('estimated_cost')->label('Estimated Cost')->money('IDR'),
                            TextEntry::make('estimated_minutes')->label('Estimated Duration')->suffix(' minutes')->placeholder('—'),
                            TextEntry::make('started_at')->label('Started')->dateTime('d M Y H:i')->placeholder('Not started'),
                            TextEntry::make('instructions')->label('Instructions')->placeholder('—')->columnSpanFull(),
                        ]),
                    ]),
                Section::make('Result')
                    ->visible(fn (WorkOrder $record): bool => $record->status === WorkOrderStatus::Completed)
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('result')->label('Result')->badge(),
                            TextEntry::make('completed_at')->label('Completed')->dateTime('d M Y H:i'),
                            TextEntry::make('completedBy.name')->label('Completed By')->placeholder('—'),
                            TextEntry::make('actual_cost')->label('Actual Cost')->money('IDR')->placeholder('—'),
                            TextEntry::make('labor_minutes')->label('Time Spent')->suffix(' minutes')->placeholder('—'),
                            TextEntry::make('findings')->label('Findings')->placeholder('—')->columnSpan(3),
                        ]),
                    ]),
                Section::make('Cancellation')
                    ->visible(fn (WorkOrder $record): bool => $record->status === WorkOrderStatus::Cancelled)
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('cancelled_at')->label('Cancelled')->dateTime('d M Y H:i'),
                            TextEntry::make('cancellation_reason')->label('Reason')->columnSpan(3),
                        ]),
                    ]),
            ]);
    }
}
