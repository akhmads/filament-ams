<?php

namespace App\Filament\Resources\AssetDisposals\Pages;

use App\Filament\Actions\ApproveAssetDisposalAction;
use App\Filament\Actions\CancelAssetDisposalAction;
use App\Filament\Actions\CompleteAssetDisposalAction;
use App\Filament\Actions\PrintDisposalAction;
use App\Filament\Actions\RejectAssetDisposalAction;
use App\Filament\Resources\AssetDisposals\AssetDisposalResource;
use App\Models\AssetDisposal;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAssetDisposal extends ViewRecord
{
    protected static string $resource = AssetDisposalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ApproveAssetDisposalAction::make(),
            CompleteAssetDisposalAction::make(),
            RejectAssetDisposalAction::make(),
            CancelAssetDisposalAction::make(),
            PrintDisposalAction::make(),
            EditAction::make()
                ->visible(fn (AssetDisposal $record): bool => $record->isEditable()),
        ];
    }
}
