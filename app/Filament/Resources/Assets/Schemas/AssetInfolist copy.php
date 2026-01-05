<?php

namespace App\Filament\Resources\Assets\Schemas;

use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Forms\Components\Repeater\TableColumn;

class AssetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(3)
            ->components([

                Section::make('Info')
                    ->columnSpan(2)
                    ->columns(2)
                    // ->description('Prevent abuse by limiting the number of requests per period')
                    ->schema([
                        TextEntry::make('code'),
                        TextEntry::make('name'),
                        TextEntry::make('category.name'),
                        TextEntry::make('brand.name'),
                        TextEntry::make('model'),
                        TextEntry::make('serial_number'),
                        TextEntry::make('condition')->badge(),
                        TextEntry::make('description')->columnSpan(2)->html(true),
                    ]),

                Group::make()
                    ->schema([
                        Section::make('Image')
                            ->schema([
                                ImageEntry::make('image')
                                    ->hiddenLabel()
                                    ->label(null)
                                    ->alignment(Alignment::Center)
                                    ->imageHeight(200)
                            ]),

                        Section::make('Other')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->dateTime(),
                                TextEntry::make('updated_at')
                                    ->dateTime(),
                            ])
                    ]),

                Section::make('Accesories')
                    ->columnSpan('full')
                    ->schema([
                        RepeatableEntry::make('accesories')
                            ->columnSpan('full')
                            ->columns(2)
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('name')->hiddenLabel(),
                                TextEntry::make('description')->hiddenLabel(),
                            ]),
                    ]),

            ]);
    }
}
