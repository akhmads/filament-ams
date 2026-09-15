<?php

namespace App\Filament\Resources\AssetDisposals\Pages;

use App\Enums\AssetDisposalStatus;
use App\Filament\Resources\AssetDisposals\AssetDisposalResource;
use App\Models\AssetDisposal;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAssetDisposals extends ListRecords
{
    protected static string $resource = AssetDisposalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Propose Disposal'),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'waiting' => Tab::make('Waiting for Approval')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', AssetDisposalStatus::Proposed->value))
                ->badge(fn (): int => AssetDisposal::query()->where('status', AssetDisposalStatus::Proposed->value)->count())
                ->badgeColor('warning'),
            'to_complete' => Tab::make('To Complete')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', AssetDisposalStatus::Approved->value))
                ->badge(fn (): int => AssetDisposal::query()->where('status', AssetDisposalStatus::Approved->value)->count()),
            'completed' => Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', AssetDisposalStatus::Completed->value)),
            'all' => Tab::make('All'),
        ];
    }
}
