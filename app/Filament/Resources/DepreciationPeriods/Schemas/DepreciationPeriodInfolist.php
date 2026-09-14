<?php

namespace App\Filament\Resources\DepreciationPeriods\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DepreciationPeriodInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Period')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('period')->label('Month')->date('F Y'),
                            TextEntry::make('book')->label('Book')->badge(),
                            TextEntry::make('status')->label('Status')->badge(),
                            TextEntry::make('asset_count')->label('Assets')->numeric(),
                            TextEntry::make('total_amount')->label('Total Depreciation')->money('IDR'),
                            TextEntry::make('calculated_at')->label('Last Calculated')->dateTime('d M Y H:i')->placeholder('—'),
                            TextEntry::make('posted_at')->label('Posted')->dateTime('d M Y H:i')->placeholder('—'),
                            TextEntry::make('postedBy.name')->label('Posted By')->placeholder('—'),
                        ]),
                    ]),
            ]);
    }
}
