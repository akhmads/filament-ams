<?php

namespace App\Filament\Resources\AssetAssignments\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AssetAssignmentInfolist
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
                            TextEntry::make('assignment_date')->label('Date')->date('d M Y'),
                            TextEntry::make('status')->label('Status')->badge(),
                            TextEntry::make('recipient_name')->label('Received By'),
                            TextEntry::make('toDepartment.name')->label('Department')->placeholder('—'),
                            TextEntry::make('branch.name')->label('Branch'),
                            TextEntry::make('expected_return_date')->label('Due Back')->date('d M Y')->placeholder('—'),
                            TextEntry::make('handedOverBy.name')->label('Handed Over By')->placeholder('—'),
                            TextEntry::make('purpose')->label('Purpose')->placeholder('—')->columnSpan(3),
                        ]),
                    ]),
                Section::make('Assets')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->schema([
                                Grid::make(4)->schema([
                                    TextEntry::make('asset.code')->label('Code')->badge(),
                                    TextEntry::make('asset.name')->label('Asset Name'),
                                    TextEntry::make('condition')->label('Condition')->badge(),
                                    TextEntry::make('notes')->label('Notes')->placeholder('—'),
                                ]),
                            ]),
                    ]),
            ]);
    }
}
