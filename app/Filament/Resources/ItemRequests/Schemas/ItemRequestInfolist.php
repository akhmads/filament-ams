<?php

namespace App\Filament\Resources\ItemRequests\Schemas;

use App\Enums\ItemRequestStatus;
use App\Filament\Resources\StockDocuments\StockDocumentResource;
use App\Models\ItemRequest;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ItemRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Request')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('number')->label('Number')->badge()->copyable(),
                            TextEntry::make('status')->label('Status')->badge(),
                            TextEntry::make('request_date')->label('Request Date')->date('d M Y'),
                            TextEntry::make('needed_by')->label('Needed By')->date('d M Y')->placeholder('—'),
                            TextEntry::make('employee.name')->label('Requested By'),
                            TextEntry::make('department.name')->label('Department')->placeholder('—'),
                            TextEntry::make('createdBy.name')->label('Recorded By')->placeholder('—')->columnSpan(2),
                            TextEntry::make('purpose')->label('Purpose')->placeholder('—')->columnSpanFull(),
                        ]),
                    ]),
                Section::make('Decision')
                    ->visible(fn (ItemRequest $record): bool => $record->approved_at !== null || $record->rejected_at !== null)
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('approvedBy.name')->label('Approved By')->placeholder('—')
                                ->visible(fn (ItemRequest $record): bool => $record->approved_at !== null),
                            TextEntry::make('approved_at')->label('Approved At')->dateTime('d M Y H:i')
                                ->visible(fn (ItemRequest $record): bool => $record->approved_at !== null),
                            TextEntry::make('rejectedBy.name')->label('Rejected By')->placeholder('—')
                                ->visible(fn (ItemRequest $record): bool => $record->status === ItemRequestStatus::Rejected),
                            TextEntry::make('rejected_at')->label('Rejected At')->dateTime('d M Y H:i')
                                ->visible(fn (ItemRequest $record): bool => $record->status === ItemRequestStatus::Rejected),
                            TextEntry::make('rejection_reason')->label('Reason')->columnSpan(2)
                                ->visible(fn (ItemRequest $record): bool => $record->status === ItemRequestStatus::Rejected),
                        ]),
                    ]),
                Section::make('Issue')
                    ->visible(fn (ItemRequest $record): bool => $record->status === ItemRequestStatus::Fulfilled)
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('fulfilled_at')->label('Issued At')->dateTime('d M Y H:i'),
                            TextEntry::make('stockDocument.number')->label('Goods Issue')->badge()->color('gray')
                                ->url(fn (ItemRequest $record): ?string => $record->stock_document_id === null
                                    ? null
                                    : StockDocumentResource::getUrl('view', ['record' => $record->stock_document_id])),
                        ]),
                    ]),
                Section::make('Cancellation')
                    ->visible(fn (ItemRequest $record): bool => $record->status === ItemRequestStatus::Cancelled)
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('cancelled_at')->label('Cancelled At')->dateTime('d M Y H:i'),
                            TextEntry::make('cancellation_reason')->label('Reason')->columnSpan(3),
                        ]),
                    ]),
            ]);
    }
}
