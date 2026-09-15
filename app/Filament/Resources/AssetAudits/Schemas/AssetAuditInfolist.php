<?php

namespace App\Filament\Resources\AssetAudits\Schemas;

use App\Enums\AssetAuditStatus;
use App\Models\AssetAudit;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AssetAuditInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Audit')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('number')->label('Number')->badge()->copyable(),
                            TextEntry::make('status')->label('Status')->badge(),
                            TextEntry::make('title')->label('Title')->columnSpan(2),
                            TextEntry::make('location.full_name')->label('Location')->placeholder('All locations'),
                            TextEntry::make('department.name')->label('Department')->placeholder('All departments'),
                            TextEntry::make('category.full_name')->label('Category')->placeholder('All categories'),
                            TextEntry::make('createdBy.name')->label('Started By')->placeholder('—'),
                            TextEntry::make('started_at')->label('Started')->dateTime('d M Y H:i')->placeholder('—'),
                            TextEntry::make('counted_at')->label('Counting Finished')->dateTime('d M Y H:i')->placeholder('Still counting'),
                            TextEntry::make('closedBy.name')->label('Closed By')->placeholder('—'),
                            TextEntry::make('closed_at')->label('Closed')->dateTime('d M Y H:i')->placeholder('—'),
                            TextEntry::make('notes')->label('Notes')->placeholder('—')->columnSpanFull(),
                        ]),
                    ]),
                Section::make('Findings')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('progress')->label('Scanned')
                                ->state(fn (AssetAudit $record): string => self::progress($record)),
                            TextEntry::make('summary_found')->label('Found')
                                ->state(fn (AssetAudit $record): int => $record->summary()['found'])
                                ->color('success'),
                            TextEntry::make('summary_misplaced')->label('Wrong Location')
                                ->state(fn (AssetAudit $record): int => $record->summary()['misplaced'])
                                ->color('warning'),
                            TextEntry::make('summary_missing')->label('Missing')
                                ->state(fn (AssetAudit $record): string => $record->status === AssetAuditStatus::InProgress
                                    ? 'Known once counting finishes'
                                    : (string) $record->summary()['missing'])
                                ->color('danger'),
                            TextEntry::make('summary_condition_changed')->label('Condition Changed')
                                ->state(fn (AssetAudit $record): int => $record->summary()['condition_changed']),
                            TextEntry::make('summary_unlisted')->label('Not on the List')
                                ->state(fn (AssetAudit $record): int => $record->summary()['unlisted']),
                        ]),
                    ]),
                Section::make('Cancellation')
                    ->visible(fn (AssetAudit $record): bool => $record->status === AssetAuditStatus::Cancelled)
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('cancelled_at')->label('Cancelled At')->dateTime('d M Y H:i'),
                            TextEntry::make('cancellation_reason')->label('Reason')->columnSpan(3),
                        ]),
                    ]),
            ]);
    }

    private static function progress(AssetAudit $record): string
    {
        $summary = $record->summary();

        return "{$summary['scanned']} scanned, of {$summary['expected']} listed";
    }
}
