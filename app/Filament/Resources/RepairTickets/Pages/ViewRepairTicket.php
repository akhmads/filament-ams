<?php

namespace App\Filament\Resources\RepairTickets\Pages;

use App\Enums\RepairTicketStatus;
use App\Filament\Actions\ApproveRepairTicketAction;
use App\Filament\Actions\CompleteRepairAction;
use App\Filament\Actions\RejectRepairTicketAction;
use App\Filament\Actions\StartRepairAction;
use App\Filament\Actions\VerifyRepairTicketAction;
use App\Filament\Resources\AssetDisposals\AssetDisposalResource;
use App\Filament\Resources\RepairTickets\RepairTicketResource;
use App\Models\AssetDisposal;
use App\Models\RepairTicket;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

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
            // An asset that cannot be repaired is retired; the next step is writing it off.
            Action::make('proposeDisposal')
                ->label('Propose Disposal')
                ->icon('heroicon-o-archive-box-x-mark')
                ->color('gray')
                ->url(fn (RepairTicket $record): string => AssetDisposalResource::getUrl('create', ['ticket' => $record->getKey()]))
                ->visible(fn (RepairTicket $record): bool => $record->status === RepairTicketStatus::Unrepairable
                    && (bool) $record->asset?->canBeProposedForDisposal()
                    && (bool) Auth::user()?->can('create', AssetDisposal::class)),
            EditAction::make()
                ->visible(fn (RepairTicket $record): bool => $record->isEditable()),
        ];
    }
}
