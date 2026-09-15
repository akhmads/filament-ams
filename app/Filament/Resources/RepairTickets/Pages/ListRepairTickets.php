<?php

namespace App\Filament\Resources\RepairTickets\Pages;

use App\Enums\RepairTicketStatus;
use App\Filament\Resources\RepairTickets\RepairTicketResource;
use App\Models\RepairTicket;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListRepairTickets extends ListRecords
{
    protected static string $resource = RepairTicketResource::class;

    /** Tickets that still need a decision before the repair can start. */
    private const WAITING_STATUSES = [
        RepairTicketStatus::Reported->value,
        RepairTicketStatus::Verified->value,
        RepairTicketStatus::Approved->value,
    ];

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Report Damage'),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'waiting' => Tab::make('Waiting')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereIn('status', self::WAITING_STATUSES))
                ->badge(fn (): int => RepairTicket::query()->whereIn('status', self::WAITING_STATUSES)->count()),
            'in_repair' => Tab::make('In Repair')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', RepairTicketStatus::InRepair->value))
                ->badge(fn (): int => RepairTicket::query()->where('status', RepairTicketStatus::InRepair->value)->count())
                ->badgeColor('danger'),
            'closed' => Tab::make('Closed')
                ->modifyQueryUsing(fn (Builder $query) => $query->whereNotIn('status', RepairTicketStatus::openValues())),
            'all' => Tab::make('All'),
        ];
    }
}
