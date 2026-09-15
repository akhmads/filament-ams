<?php

namespace App\Filament\Resources\ItemRequests\Pages;

use App\Enums\ItemRequestStatus;
use App\Filament\Resources\ItemRequests\ItemRequestResource;
use App\Models\ItemRequest;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListItemRequests extends ListRecords
{
    protected static string $resource = ItemRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New Item Request'),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'waiting' => Tab::make('Waiting for Approval')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ItemRequestStatus::Submitted->value))
                ->badge(fn (): int => ItemRequest::query()->where('status', ItemRequestStatus::Submitted->value)->count())
                ->badgeColor('warning'),
            'to_issue' => Tab::make('To Issue')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', ItemRequestStatus::Approved->value))
                ->badge(fn (): int => ItemRequest::query()->where('status', ItemRequestStatus::Approved->value)->count()),
            'all' => Tab::make('All'),
        ];
    }
}
