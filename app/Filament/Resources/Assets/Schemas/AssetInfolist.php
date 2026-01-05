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
            ->components([

            ]);
    }
}
