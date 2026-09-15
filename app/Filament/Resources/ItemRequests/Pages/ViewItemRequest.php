<?php

namespace App\Filament\Resources\ItemRequests\Pages;

use App\Filament\Actions\ApproveItemRequestAction;
use App\Filament\Actions\CancelItemRequestAction;
use App\Filament\Actions\IssueItemRequestAction;
use App\Filament\Actions\RejectItemRequestAction;
use App\Filament\Resources\ItemRequests\ItemRequestResource;
use App\Models\ItemRequest;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewItemRequest extends ViewRecord
{
    protected static string $resource = ItemRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ApproveItemRequestAction::make(),
            IssueItemRequestAction::make(),
            RejectItemRequestAction::make(),
            CancelItemRequestAction::make(),
            EditAction::make()
                ->visible(fn (ItemRequest $record): bool => $record->isEditable()),
        ];
    }
}
