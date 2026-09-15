<?php

namespace App\Filament\Resources\AssetDisposals\Schemas;

use App\Enums\AssetDisposalStatus;
use App\Models\AssetDisposal;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AssetDisposalInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Proposal')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('number')->label('Number')->badge()->copyable(),
                            TextEntry::make('status')->label('Status')->badge(),
                            TextEntry::make('disposal_date')->label('Disposal Date')->date('d M Y'),
                            TextEntry::make('createdBy.name')->label('Proposed By')->placeholder('—'),
                            TextEntry::make('recipient_name')->label('Buyer / Recipient')->placeholder('—'),
                            TextEntry::make('reference_number')->label('Auction / Invoice No.')->placeholder('—'),
                            TextEntry::make('approvedBy.name')->label('Approved By')->placeholder('Not approved yet'),
                            TextEntry::make('approved_at')->label('Approved At')->dateTime('d M Y H:i')->placeholder('—'),
                            TextEntry::make('reason')->label('Reason')->columnSpanFull(),
                            TextEntry::make('notes')->label('Notes')->placeholder('—')->columnSpanFull(),
                        ]),
                    ]),
                Section::make('Result')
                    ->description('Book values use the depreciation posted up to the month before disposal. A negative figure is a loss.')
                    ->visible(fn (AssetDisposal $record): bool => $record->status === AssetDisposalStatus::Completed)
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('completed_at')->label('Completed At')->dateTime('d M Y H:i'),
                            TextEntry::make('completedBy.name')->label('Completed By')->placeholder('—'),
                            TextEntry::make('total_proceeds')->label('Total Proceeds')
                                ->state(fn (AssetDisposal $record): float => (float) $record->lineTotal('proceeds'))
                                ->money('IDR'),
                            TextEntry::make('total_book_value')->label('Total Book Value (Commercial)')
                                ->state(fn (AssetDisposal $record): float => (float) $record->lineTotal('commercial_book_value'))
                                ->money('IDR'),
                            TextEntry::make('total_commercial_gain_loss')->label('Gain / Loss (Commercial)')
                                ->state(fn (AssetDisposal $record): float => (float) $record->lineTotal('commercial_gain_loss'))
                                ->money('IDR')
                                ->color(fn (float $state): string => $state < 0 ? 'danger' : 'success'),
                            TextEntry::make('total_fiscal_gain_loss')->label('Gain / Loss (Fiscal)')
                                ->state(fn (AssetDisposal $record): float => (float) $record->lineTotal('fiscal_gain_loss'))
                                ->money('IDR')
                                ->color(fn (float $state): string => $state < 0 ? 'danger' : 'success'),
                        ]),
                    ]),
                Section::make('Rejection')
                    ->visible(fn (AssetDisposal $record): bool => $record->status === AssetDisposalStatus::Rejected)
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('rejected_at')->label('Rejected At')->dateTime('d M Y H:i'),
                            TextEntry::make('rejectedBy.name')->label('Rejected By')->placeholder('—'),
                            TextEntry::make('rejection_reason')->label('Reason')->columnSpan(2),
                        ]),
                    ]),
                Section::make('Cancellation')
                    ->visible(fn (AssetDisposal $record): bool => $record->status === AssetDisposalStatus::Cancelled)
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('cancelled_at')->label('Cancelled At')->dateTime('d M Y H:i'),
                            TextEntry::make('cancellation_reason')->label('Reason')->columnSpan(3),
                        ]),
                    ]),
            ]);
    }
}
