<?php

namespace App\Filament\Resources\RepairTickets\Pages;

use App\Filament\Actions\ApproveRepairTicketAction;
use App\Filament\Actions\CompleteRepairAction;
use App\Filament\Actions\RejectRepairTicketAction;
use App\Filament\Actions\StartRepairAction;
use App\Filament\Actions\VerifyRepairTicketAction;
use App\Filament\Resources\RepairTickets\RepairTicketResource;
use App\Models\RepairTicket;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRepairTicket extends ViewRecord
{
    protected static string $resource = RepairTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            VerifyRepairTicketAction::make(),
            ApproveRepairTicketAction::make(),
            StartRepairAction::make(),
            CompleteRepairAction::make(),
            RejectRepairTicketAction::make(),
            EditAction::make()
                ->visible(fn (RepairTicket $record): bool => $record->isEditable()),
        ];
    }
}
