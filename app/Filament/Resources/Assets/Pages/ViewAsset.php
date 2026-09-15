<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Actions\PrintAssetLabelsAction;
use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Resources\RepairTickets\RepairTicketResource;
use App\Models\RepairTicket;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewAsset extends ViewRecord
{
    protected static string $resource = AssetResource::class;

    public function getSubheading(): ?string
    {
        return $this->record->current_holder;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reportDamage')
                ->label('Report Damage')
                ->icon('heroicon-o-exclamation-triangle')
                ->color('danger')
                ->url(fn (): string => RepairTicketResource::getUrl('create', ['asset' => $this->record->getKey()]))
                ->visible(fn (): bool => ! $this->record->status->isTerminal()
                    && (bool) Auth::user()?->can('create', RepairTicket::class)),
            PrintAssetLabelsAction::make(),
            EditAction::make(),
        ];
    }
}
