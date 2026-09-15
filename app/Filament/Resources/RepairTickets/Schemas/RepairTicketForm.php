<?php

namespace App\Filament\Resources\RepairTickets\Schemas;

use App\Enums\RepairPriority;
use App\Models\Employee;
use App\Support\AssetOptions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The damage report. How the repair is carried out is decided later, by the
 * actions on the ticket.
 */
class RepairTicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Damage Report')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('number')
                                ->label('Number')
                                ->placeholder('Automatic')
                                ->disabled()
                                ->dehydrated(false),
                            // The warranty is judged against this asset when the damage is
                            // reported, so the asset cannot change afterwards.
                            Select::make('asset_id')
                                ->label('Asset')
                                ->searchable()
                                ->getSearchResultsUsing(fn (string $search): array => AssetOptions::search($search))
                                ->getOptionLabelUsing(fn (mixed $value): ?string => AssetOptions::label($value))
                                ->disabledOn('edit')
                                ->required(),
                            TextInput::make('title')
                                ->label('Problem')
                                ->placeholder('e.g. Screen does not turn on')
                                ->required()
                                ->maxLength(255),
                            Select::make('priority')
                                ->label('Priority')
                                ->options(RepairPriority::class)
                                ->default(RepairPriority::Normal->value)
                                ->selectablePlaceholder(false)
                                ->required(),
                            Select::make('reported_by_employee_id')
                                ->label('Reported By')
                                ->helperText('The employee who reported the damage, if not you.')
                                ->relationship('reportedBy', 'name')
                                ->getOptionLabelFromRecordUsing(fn (Employee $record): string => $record->display_name)
                                ->searchable(['name', 'employee_number']),
                            DateTimePicker::make('reported_at')
                                ->label('Reported At')
                                ->seconds(false)
                                ->default(now())
                                ->required(),
                        ]),
                        Textarea::make('description')
                            ->label('Description')
                            ->rows(4),
                        SpatieMediaLibraryFileUpload::make('photos')
                            ->label('Photos')
                            ->helperText('Photos of the damage.')
                            ->collection('photos')
                            ->image()
                            ->multiple()
                            ->maxFiles(8),
                    ]),
            ]);
    }
}
