<?php

namespace App\Filament\Resources\WorkOrders\Pages;

use App\Filament\Actions\CancelWorkOrderAction;
use App\Filament\Actions\CompleteWorkOrderAction;
use App\Filament\Actions\StartWorkOrderAction;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\WorkOrder;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkOrder extends ViewRecord
{
    protected static string $resource = WorkOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            StartWorkOrderAction::make(),
            CompleteWorkOrderAction::make(),
            CancelWorkOrderAction::make(),
            EditAction::make()
                ->visible(fn (WorkOrder $record): bool => $record->isEditable()),
        ];
    }
}
