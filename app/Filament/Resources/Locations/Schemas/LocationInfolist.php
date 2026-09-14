<?php

namespace App\Filament\Resources\Locations\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LocationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Location Information')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('code')->label('Code')->badge(),
                            TextEntry::make('full_name')->label('Location')->columnSpan(2),
                            TextEntry::make('type')->label('Type')->badge(),
                            TextEntry::make('branch.name')->label('Branch'),
                            TextEntry::make('pic_name')->label('Person in Charge')->placeholder('—'),
                            IconEntry::make('is_active')->label('Active')->boolean(),
                            TextEntry::make('description')->label('Description')->placeholder('—')->columnSpanFull(),
                        ]),
                    ]),
            ]);
    }
}
