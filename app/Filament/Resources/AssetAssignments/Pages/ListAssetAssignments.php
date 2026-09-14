<?php

namespace App\Filament\Resources\AssetAssignments\Pages;

use App\Enums\AssignmentStatus;
use App\Filament\Resources\AssetAssignments\AssetAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListAssetAssignments extends ListRecords
{
    protected static string $resource = AssetAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('New Document'),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'semua' => Tab::make('All'),
            'draf' => Tab::make('Draft')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', AssignmentStatus::Draft->value))
                ->badge(fn (): int => AssetAssignmentResource::getModel()::query()
                    ->where('status', AssignmentStatus::Draft->value)
                    ->count()),
            'selesai' => Tab::make('Completed')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('status', AssignmentStatus::Completed->value)),
            'terlambat' => Tab::make('Overdue')
                ->modifyQueryUsing(fn (Builder $query) => $query->overdue())
                ->badge(fn (): int => AssetAssignmentResource::getModel()::query()->overdue()->count())
                ->badgeColor('danger'),
        ];
    }
}
