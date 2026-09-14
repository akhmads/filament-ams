<?php

namespace App\Filament\Resources\Employees\Schemas;

use App\Enums\EmployeeStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class EmployeeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Employee Details')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('employee_number')
                                ->label('Employee ID / Staff Number')
                                ->required()
                                ->maxLength(30)
                                ->unique(ignoreRecord: true),
                            TextInput::make('name')
                                ->label('Full Name')
                                ->required()
                                ->maxLength(255),
                            Select::make('branch_id')
                                ->label('Branch')
                                ->relationship('branch', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live(),
                            Select::make('department_id')
                                ->label('Department')
                                ->relationship(
                                    name: 'department',
                                    titleAttribute: 'name',
                                    modifyQueryUsing: fn ($query, Get $get) => $query
                                        ->where(fn ($q) => $q
                                            ->whereNull('branch_id')
                                            ->orWhere('branch_id', $get('branch_id'))),
                                )
                                ->searchable()
                                ->preload(),
                            TextInput::make('position')
                                ->label('Position')
                                ->maxLength(255),
                            Select::make('status')
                                ->label('Status')
                                ->options(EmployeeStatus::class)
                                ->default(EmployeeStatus::Active)
                                ->required()
                                ->live(),
                        ]),
                    ]),
                Section::make('Contact & Employment')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('email')
                                ->label('Email')
                                ->email()
                                ->maxLength(255),
                            TextInput::make('phone')
                                ->label('Phone')
                                ->tel()
                                ->maxLength(30),
                            DatePicker::make('joined_at')
                                ->label('Join Date'),
                            DatePicker::make('resigned_at')
                                ->label('Resignation Date')
                                ->visible(fn (Get $get): bool => $get('status') === EmployeeStatus::Resigned->value)
                                ->helperText('Make sure every asset has been returned before marking the employee as resigned.'),
                            Select::make('user_id')
                                ->label('User Account')
                                ->relationship('user', 'name')
                                ->searchable()
                                ->preload()
                                ->helperText('Link this if the employee also uses the application.'),
                        ]),
                    ]),
            ]);
    }
}
