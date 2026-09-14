<?php

namespace App\Filament\Resources\AssetCategories\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AssetCategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Category')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('code')->label('Code')->badge(),
                            TextEntry::make('full_name')->label('Category')->columnSpan(2),
                            TextEntry::make('prefix')->label('Asset Code Prefix')->badge()->color('warning'),
                            TextEntry::make('description')->label('Description')->placeholder('—')->columnSpan(2),
                        ]),
                    ]),
                Section::make('Depreciation Defaults')
                    ->schema([
                        Grid::make(4)->schema([
                            IconEntry::make('is_depreciable')->label('Depreciable')->boolean(),
                            TextEntry::make('depreciation_method')->label('Method')->badge(),
                            TextEntry::make('useful_life_months')->label('Useful Life')->suffix(' months'),
                            TextEntry::make('residual_percent')->label('Residual')->suffix(' %'),
                        ]),
                    ]),
                Section::make('Tax Depreciation & Journal Accounts')
                    ->schema([
                        Grid::make(4)->schema([
                            TextEntry::make('fiscal_group')->label('Tax Asset Group')->badge()->placeholder('Not set'),
                            TextEntry::make('fiscal_method')->label('Tax Method')->badge(),
                            TextEntry::make('expense_account_code')->label('Expense Account')->placeholder('—')
                                ->belowContent(fn ($record): ?string => $record->expense_account_name),
                            TextEntry::make('accumulated_account_code')->label('Accumulated Account')->placeholder('—')
                                ->belowContent(fn ($record): ?string => $record->accumulated_account_name),
                        ]),
                    ]),
            ]);
    }
}
