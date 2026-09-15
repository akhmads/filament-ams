<?php

namespace App\Filament\Resources\StockDocuments\Schemas;

use App\Enums\StockDocumentStatus;
use App\Enums\StockDocumentType;
use App\Filament\Resources\ItemRequests\ItemRequestResource;
use App\Filament\Resources\RepairTickets\RepairTicketResource;
use App\Filament\Resources\WorkOrders\WorkOrderResource;
use App\Models\ItemRequest;
use App\Models\RepairTicket;
use App\Models\StockDocument;
use App\Models\WorkOrder;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StockDocumentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Document')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('number')->label('Number')->badge()->copyable(),
                            TextEntry::make('type')->label('Type')->badge(),
                            TextEntry::make('status')->label('Status')->badge(),
                            TextEntry::make('document_date')->label('Date')->date('d M Y'),
                            TextEntry::make('location.name')
                                ->label(fn (StockDocument $record): string => $record->type === StockDocumentType::Transfer ? 'From Warehouse' : 'Warehouse'),
                            TextEntry::make('destinationLocation.name')->label('To Warehouse')
                                ->visible(fn (StockDocument $record): bool => $record->type === StockDocumentType::Transfer),
                            TextEntry::make('supplier.name')->label('Supplier')->placeholder('—')
                                ->visible(fn (StockDocument $record): bool => $record->type === StockDocumentType::Receipt),
                            TextEntry::make('reference_number')->label('Delivery Note / Invoice No.')->placeholder('—')
                                ->visible(fn (StockDocument $record): bool => $record->type === StockDocumentType::Receipt),
                            TextEntry::make('employee.name')->label('Issued To')->placeholder('—')
                                ->visible(fn (StockDocument $record): bool => $record->type === StockDocumentType::Issue),
                            TextEntry::make('department.name')->label('Department')->placeholder('—')
                                ->visible(fn (StockDocument $record): bool => $record->type === StockDocumentType::Issue),
                            TextEntry::make('source')->label('Issued For')
                                ->state(fn (StockDocument $record): ?string => $record->source?->number)
                                ->url(fn (StockDocument $record): ?string => self::sourceUrl($record))
                                ->badge()
                                ->color('gray')
                                ->visible(fn (StockDocument $record): bool => $record->source_type !== null),
                            TextEntry::make('createdBy.name')->label('Created By')->placeholder('—'),
                            TextEntry::make('postedBy.name')->label('Posted By')->placeholder('—')
                                ->visible(fn (StockDocument $record): bool => $record->status === StockDocumentStatus::Posted),
                            TextEntry::make('posted_at')->label('Posted At')->dateTime('d M Y H:i')
                                ->visible(fn (StockDocument $record): bool => $record->status === StockDocumentStatus::Posted),
                            TextEntry::make('notes')->label('Notes')->placeholder('—')->columnSpanFull(),
                        ]),
                    ]),
            ]);
    }

    private static function sourceUrl(StockDocument $record): ?string
    {
        return match (true) {
            $record->source instanceof WorkOrder => WorkOrderResource::getUrl('view', ['record' => $record->source]),
            $record->source instanceof RepairTicket => RepairTicketResource::getUrl('view', ['record' => $record->source]),
            $record->source instanceof ItemRequest => ItemRequestResource::getUrl('view', ['record' => $record->source]),
            default => null,
        };
    }
}
