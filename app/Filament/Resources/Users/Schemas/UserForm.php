<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('name')
                                ->label('Name')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('email')
                                ->label('Email')
                                ->email()
                                ->required()
                                ->maxLength(255)
                                ->unique(ignoreRecord: true),
                            TextInput::make('password')
                                ->label('Password')
                                ->password()
                                ->revealable()
                                ->minLength(8)
                                ->dehydrateStateUsing(fn (?string $state): ?string => filled($state) ? Hash::make($state) : null)
                                ->dehydrated(fn (?string $state): bool => filled($state))
                                ->required(fn (string $operation): bool => $operation === 'create')
                                ->helperText(fn (string $operation): ?string => $operation === 'edit'
                                    ? 'Leave empty to keep the current password.'
                                    : null),
                            TextInput::make('phone')
                                ->label('Phone')
                                ->tel()
                                ->maxLength(30),
                            Select::make('branch_id')
                                ->label('Branch')
                                ->relationship('branch', 'name')
                                ->searchable()
                                ->preload(),
                            Toggle::make('is_active')
                                ->label('Active')
                                ->helperText('Inactive users cannot sign in.')
                                ->default(true),
                        ]),
                        FileUpload::make('avatar_path')
                            ->label('Photo')
                            ->image()
                            ->avatar()
                            ->disk('public')
                            ->directory('avatars'),
                    ]),
                Section::make('Permissions')
                    ->schema([
                        Select::make('roles')
                            ->label('Roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),
                    ]),
            ]);
    }
}
