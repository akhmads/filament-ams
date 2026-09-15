<?php

namespace App\Filament\Resources\AssetAudits\Tables;

use App\Enums\AssetAuditStatus;
use App\Filament\Actions\PrintAuditAction;
use App\Filament\Resources\AssetAudits\AssetAuditResource;
use App\Models\AssetAudit;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AssetAuditsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(fn ($query) => $query->orderByDesc('started_at')->orderByDesc('id'))
            ->emptyStateHeading('No asset audits')
            ->emptyStateDescription('Start an audit to count the assets in a location or department.')
            ->columns([
                TextColumn::make('number')
                    ->label('Number')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('location.name')
                    ->label('Location')
                    ->placeholder('All locations'),
                TextColumn::make('department.name')
                    ->label('Department')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('lines_count')
                    ->label('Assets')
                    ->alignEnd(),
                TextColumn::make('started_at')
                    ->label('Started')
                    ->date('d M Y')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options(AssetAuditStatus::class),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make(),
                    Action::make('scanAssets')
                        ->label('Scan')
                        ->icon('heroicon-o-qr-code')
                        ->url(fn (AssetAudit $record): string => AssetAuditResource::getUrl('scan', ['record' => $record]))
                        ->visible(fn (AssetAudit $record): bool => $record->status === AssetAuditStatus::InProgress
                            && (bool) Auth::user()?->can('update', $record)),
                    PrintAuditAction::make(),
                ]),
            ]);
    }
}
