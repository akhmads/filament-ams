<?php

namespace App\Filament\Resources\Employees\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmployeeInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Employee Details')
                    ->schema([
                        Grid::make(3)->schema([
                            TextEntry::make('employee_number')->label('Employee ID')->badge(),
                            TextEntry::make('name')->label('Name'),
                            TextEntry::make('status')->label('Status')->badge(),
                            TextEntry::make('department.name')->label('Department')->placeholder('—'),
                            TextEntry::make('position')->label('Position')->placeholder('—'),
                            TextEntry::make('branch.name')->label('Branch'),
                            TextEntry::make('email')->label('Email')->placeholder('—'),
                            TextEntry::make('phone')->label('Phone')->placeholder('—'),
                            TextEntry::make('joined_at')->label('Joined')->date('d M Y')->placeholder('—'),
                        ]),
                    ]),
            ]);
    }
}
