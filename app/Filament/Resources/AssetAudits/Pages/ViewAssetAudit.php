<?php

namespace App\Filament\Resources\AssetAudits\Pages;

use App\Enums\AssetAuditStatus;
use App\Filament\Actions\CancelAssetAuditAction;
use App\Filament\Actions\CloseAssetAuditAction;
use App\Filament\Actions\FinishCountingAction;
use App\Filament\Actions\PrintAuditAction;
use App\Filament\Resources\AssetAudits\AssetAuditResource;
use App\Models\AssetAudit;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewAssetAudit extends ViewRecord
{
    protected static string $resource = AssetAuditResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('scanAssets')
                ->label('Scan Assets')
                ->icon('heroicon-o-qr-code')
                ->url(fn (AssetAudit $record): string => AssetAuditResource::getUrl('scan', ['record' => $record]))
                ->visible(fn (AssetAudit $record): bool => $record->status === AssetAuditStatus::InProgress
                    && (bool) Auth::user()?->can('update', $record)),
            FinishCountingAction::make(),
            CloseAssetAuditAction::make(),
            CancelAssetAuditAction::make(),
            PrintAuditAction::make(),
        ];
    }
}
