<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Group;
use Illuminate\Database\Eloquent\Model;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\FileUpload;
use Filament\Infolists\Components\TextEntry;
use Filament\Forms\Components\DateTimePicker;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['xl' => 3])
            ->components([
                Section::make()
                    ->columnSpan(['xl' => 2])
                    ->columns(['xl' => 1])
                    ->schema([

                        TextInput::make('name')
                            ->maxLength(255)
                            ->required(),
                        TextInput::make('email')
                            ->label('Email address')
                            ->email()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->required(),
                        TextInput::make('password')
                            ->password()
                            // Only save the password if it has been filled
                            ->dehydrated(fn ($state) => filled($state))
                            // Optionally, hash the password when it's filled
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state)),
                        Toggle::make('is_active'),

                        Select::make('roles')
                            ->relationship('roles', 'name')
                            // ->saveRelationshipsUsing(function (Model $record, $state) {
                            //     $record->roles()->syncWithPivotValues($state);
                            // })
                            ->multiple()
                            ->preload()
                            ->searchable(),
                    ]),

                Group::make()
                    ->schema([
                        Section::make()
                            ->schema([
                                FileUpload::make('avatar')
                                    //->image()
                                    ->avatar()
                                    //->imageEditor()
                                    //->circleCropper()
                                    ->directory('avatar')
                                    ->disk('public')
                            ]),

                        Section::make('Other')
                            ->hiddenOn('create')
                            ->columns(2)
                            ->schema([
                                TextEntry::make('created_at')
                                    ->dateTime(),
                                TextEntry::make('updated_at')
                                    ->dateTime(),
                                TextEntry::make('email_verified_at')
                                    ->dateTime()
                                    ->placeholder('-'),
                            ])
                    ]),
            ]);
    }
}
