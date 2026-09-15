<?php

namespace App\Filament\Resources\AssetAudits\Pages;

use App\Enums\AssetAuditStatus;
use App\Filament\Resources\AssetAudits\AssetAuditResource;
use App\Models\AssetAudit;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAssetAudits extends ListRecords
{
    protected static string $resource = AssetAuditResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Start Audit'),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'counting' => Tab::make('Counting')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', AssetAuditStatus::InProgress->value))
                ->badge(fn (): int => AssetAudit::query()->where('status', AssetAuditStatus::InProgress->value)->count()),
            'review' => Tab::make('Under Review')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', AssetAuditStatus::UnderReview->value))
                ->badge(fn (): int => AssetAudit::query()->where('status', AssetAuditStatus::UnderReview->value)->count())
                ->badgeColor('warning'),
            'completed' => Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', AssetAuditStatus::Completed->value)),
            'all' => Tab::make('All'),
        ];
    }
}
