<?php

namespace App\Filament\Resources\RepairTickets\Schemas;

use App\Enums\RepairTicketStatus;
use App\Filament\Resources\Assets\AssetResource;
use App\Models\RepairTicket;
use Carbon\CarbonInterval;
use Filament\Infolists\Components\SpatieMediaLibraryImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RepairTicketInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Damage Report')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('number')->label('Number')->badge()->copyable(),
                            TextEntry::make('status')->label('Status')->badge(),
                            TextEntry::make('priority')->label('Priority')->badge(),
                            TextEntry::make('reported_at')->label('Reported At')->dateTime('d M Y H:i'),
                            TextEntry::make('asset.code')->label('Asset')->badge()->color('gray')
                                ->belowContent(fn (RepairTicket $record): ?string => $record->asset?->name)
                                ->url(fn (RepairTicket $record): ?string => $record->asset === null
                                    ? null
                                    : AssetResource::getUrl('view', ['record' => $record->asset])),
                            TextEntry::make('title')->label('Problem')->columnSpan(3),
                            TextEntry::make('reportedBy.name')->label('Reported By')->placeholder('Not recorded'),
                            TextEntry::make('createdBy.name')->label('Recorded By')->placeholder('—'),
                            TextEntry::make('is_under_warranty')->label('Warranty')->badge()
                                ->formatStateUsing(fn (bool $state): string => $state ? 'Under warranty' : 'Not under warranty')
                                ->color(fn (bool $state): string => $state ? 'success' : 'gray')
                                ->belowContent(fn (RepairTicket $record): ?string => $record->is_under_warranty
                                    ? 'Claim the repair with the vendor instead of paying for it.'
                                    : null)
                                ->columnSpan(2),
                            TextEntry::make('description')->label('Description')->placeholder('—')->columnSpanFull(),
                            SpatieMediaLibraryImageEntry::make('photos')->label('Photos')->collection('photos')
                                ->placeholder('No photos')
                                ->columnSpanFull(),
                        ]),
                    ]),
                Section::make('Assessment')
                    ->visible(fn (RepairTicket $record): bool => $record->verified_at !== null)
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('repair_type')->label('Repaired By')->badge(),
                            TextEntry::make('assignedTo.name')->label('Technician')->placeholder('Unassigned'),
                            TextEntry::make('supplier.name')->label('Service Vendor')->placeholder('—'),
                            TextEntry::make('estimated_cost')->label('Estimated Cost')->money('IDR'),
                            TextEntry::make('verifiedBy.name')->label('Verified By')->placeholder('—'),
                            TextEntry::make('verified_at')->label('Verified At')->dateTime('d M Y H:i'),
                            TextEntry::make('approvedBy.name')->label('Approved By')->placeholder('Not approved yet'),
                            TextEntry::make('approved_at')->label('Approved At')->dateTime('d M Y H:i')->placeholder('—'),
                        ]),
                    ]),
                Section::make('Repair')
                    ->visible(fn (RepairTicket $record): bool => $record->started_at !== null)
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('started_at')->label('Started')->dateTime('d M Y H:i'),
                            TextEntry::make('completed_at')->label('Finished')->dateTime('d M Y H:i')->placeholder('Still in repair'),
                            TextEntry::make('downtime')->label('Downtime')
                                ->state(fn (RepairTicket $record): ?string => self::duration($record->downtimeMinutes())),
                            TextEntry::make('completedBy.name')->label('Finished By')->placeholder('—'),
                            TextEntry::make('actual_cost')->label('Actual Cost')->money('IDR')->placeholder('—'),
                            TextEntry::make('spare_parts_cost')->label('Spare Parts Used')
                                ->state(fn (RepairTicket $record): float => (float) $record->sparePartsCost())
                                ->money('IDR'),
                            TextEntry::make('resolution')->label('Resolution')->placeholder('—')->columnSpan(2),
                        ]),
                    ]),
                Section::make('Rejection')
                    ->visible(fn (RepairTicket $record): bool => $record->status === RepairTicketStatus::Rejected)
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('rejected_at')->label('Rejected At')->dateTime('d M Y H:i'),
                            TextEntry::make('rejectedBy.name')->label('Rejected By')->placeholder('—'),
                            TextEntry::make('rejection_reason')->label('Reason')->columnSpan(2),
                        ]),
                    ]),
            ]);
    }

    private static function duration(?int $minutes): ?string
    {
        if ($minutes === null) {
            return null;
        }

        if ($minutes < 1) {
            return 'Under a minute';
        }

        return CarbonInterval::minutes($minutes)->cascade()->forHumans(['parts' => 2]);
    }
}
