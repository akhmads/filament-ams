<?php

namespace App\Filament\Resources\AssetAssignments\Schemas;

use App\Enums\AssetCondition;
use App\Enums\AssignmentType;
use App\Enums\PlacementType;
use App\Forms\Components\SignaturePad;
use App\Models\Asset;
use App\Models\Employee;
use App\Models\Location;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class AssetAssignmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Document')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('number')
                                ->label('Document Number')
                                ->placeholder('Automatic')
                                ->disabled()
                                ->dehydrated(false),
                            Select::make('type')
                                ->label('Document Type')
                                ->options(AssignmentType::class)
                                ->default(AssignmentType::Checkout)
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Set $set, mixed $state): void {
                                    // Pengembalian selalu bermuara ke gudang.
                                    $set('to_placement_type', $state === AssignmentType::Checkin->value
                                        ? PlacementType::Warehouse->value
                                        : PlacementType::Employee->value);
                                    $set('to_employee_id', null);
                                    $set('to_location_id', null);
                                    $set('items', []);
                                }),
                            DatePicker::make('assignment_date')
                                ->label('Date')
                                ->default(now())
                                ->required(),
                            Select::make('branch_id')
                                ->label('Branch')
                                ->relationship('branch', 'name')
                                ->searchable()
                                ->preload()
                                ->required()
                                ->live()
                                ->afterStateUpdated(fn (Set $set) => $set('items', [])),
                            DatePicker::make('expected_return_date')
                                ->label('Due Back')
                                ->helperText('Fill in if the asset is only borrowed temporarily.')
                                ->visible(fn (Get $get): bool => $get('type') === AssignmentType::Checkout->value)
                                ->afterOrEqual('assignment_date'),
                        ]),
                    ]),
                Section::make('Destination')
                    ->schema([
                        Grid::make(3)->schema([
                            Select::make('to_placement_type')
                                ->label('Placement Type')
                                ->options(PlacementType::class)
                                ->default(PlacementType::Employee)
                                ->required()
                                ->live()
                                ->afterStateUpdated(function (Set $set): void {
                                    $set('to_employee_id', null);
                                    $set('to_location_id', null);
                                }),
                            Select::make('to_employee_id')
                                ->label('Receiving Employee')
                                ->options(fn (Get $get): array => self::employeeOptions($get('branch_id')))
                                ->searchable()
                                ->required(fn (Get $get): bool => $get('to_placement_type') === PlacementType::Employee->value)
                                ->visible(fn (Get $get): bool => $get('to_placement_type') === PlacementType::Employee->value)
                                ->live(),
                            Select::make('to_location_id')
                                ->label('Destination Room / Warehouse')
                                ->options(fn (Get $get): array => self::locationOptions($get('branch_id')))
                                ->searchable()
                                ->required(fn (Get $get): bool => in_array($get('to_placement_type'), [
                                    PlacementType::Location->value,
                                    PlacementType::Warehouse->value,
                                ], strict: true))
                                ->visible(fn (Get $get): bool => in_array($get('to_placement_type'), [
                                    PlacementType::Location->value,
                                    PlacementType::Warehouse->value,
                                ], strict: true)),
                            Select::make('to_department_id')
                                ->label('Department')
                                ->relationship('toDepartment', 'name')
                                ->searchable()
                                ->preload(),
                        ]),
                    ]),
                Section::make('Assets')
                    ->schema([
                        Repeater::make('items')
                            ->hiddenLabel()
                            ->relationship()
                            ->schema([
                                Grid::make(3)->schema([
                                    Select::make('asset_id')
                                        ->label('Asset')
                                        ->options(fn (Get $get): array => self::assetOptions(
                                            branchId: $get('../../branch_id'),
                                            type: $get('../../type'),
                                            employeeId: $get('../../to_employee_id'),
                                        ))
                                        ->searchable()
                                        ->required()
                                        ->distinct()
                                        ->fixIndistinctState()
                                        ->columnSpan(2),
                                    Select::make('condition')
                                        ->label('Condition')
                                        ->options(AssetCondition::class)
                                        ->default(AssetCondition::Good)
                                        ->required(),
                                ]),
                                TextInput::make('notes')
                                    ->label('Notes')
                                    ->maxLength(255),
                            ])
                            ->addActionLabel('New Asset')
                            ->defaultItems(1)
                            ->minItems(1)
                            ->reorderable(false),
                    ]),
                Section::make('Handover Details')
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('handed_over_by')
                                ->label('Handed Over By')
                                ->options(fn (Get $get): array => self::employeeOptions($get('branch_id')))
                                ->searchable(),
                            TextInput::make('received_by_name')
                                ->label('Recipient Name')
                                ->helperText('Fill in if the recipient is not a registered employee.')
                                ->maxLength(255),
                            SignaturePad::make('handover_signature')
                                ->label('Handover Signature'),
                            SignaturePad::make('receiver_signature')
                                ->label('Recipient Signature'),
                        ]),
                        Textarea::make('purpose')
                            ->label('Purpose')
                            ->rows(2),
                        Textarea::make('notes')
                            ->label('Notes')
                            ->rows(2),
                    ]),
            ]);
    }

    /**
     * Which assets may go on the document depends on the document type.
     *
     * @return array<int, string>
     */
    private static function assetOptions(mixed $branchId, mixed $type, mixed $employeeId): array
    {
        if (blank($branchId)) {
            return [];
        }

        $query = Asset::query()->where('branch_id', $branchId)->active();

        $query = match ($type) {
            AssignmentType::Checkin->value => $query
                ->where('placement_type', PlacementType::Employee->value)
                ->when($employeeId, fn ($q, $id) => $q->where('current_employee_id', $id)),
            AssignmentType::Checkout->value => $query->assignable(),
            default => $query,
        };

        return $query
            ->orderBy('code')
            ->get()
            ->mapWithKeys(fn (Asset $asset): array => [
                $asset->id => "{$asset->code} — {$asset->name}",
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function employeeOptions(mixed $branchId): array
    {
        if (blank($branchId)) {
            return [];
        }

        return Employee::query()
            ->where('branch_id', $branchId)
            ->active()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Employee $employee): array => [$employee->id => $employee->display_name])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private static function locationOptions(mixed $branchId): array
    {
        if (blank($branchId)) {
            return [];
        }

        return Location::query()
            ->where('branch_id', $branchId)
            ->active()
            ->holdsAssets()
            ->defaultOrder()
            ->get()
            ->mapWithKeys(fn (Location $location): array => [$location->id => $location->full_name])
            ->all();
    }
}
