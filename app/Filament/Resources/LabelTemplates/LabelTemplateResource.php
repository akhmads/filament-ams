<?php

namespace App\Filament\Resources\LabelTemplates;

use App\Enums\LabelCodeType;
use App\Filament\Resources\LabelTemplates\Pages\ManageLabelTemplates;
use App\Models\LabelTemplate;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class LabelTemplateResource extends Resource
{
    protected static ?string $model = LabelTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 20;

    protected static ?string $modelLabel = 'Label Template';

    protected static ?string $pluralModelLabel = 'Label Templates';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Label Size')
                    ->description('Match the physical size of the label stock in use.')
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('name')
                                ->label('Template Name')
                                ->required()
                                ->maxLength(255)
                                ->columnSpan(3),
                            TextInput::make('width_mm')
                                ->label('Width (mm)')
                                ->numeric()
                                ->required()
                                ->minValue(10)
                                ->default(50),
                            TextInput::make('height_mm')
                                ->label('Height (mm)')
                                ->numeric()
                                ->required()
                                ->minValue(10)
                                ->default(25),
                            TextInput::make('columns')
                                ->label('Labels per Row')
                                ->helperText('Enter 1 for roll label printers.')
                                ->numeric()
                                ->required()
                                ->minValue(1)
                                ->maxValue(6)
                                ->default(1),
                            TextInput::make('margin_mm')
                                ->label('Margin (mm)')
                                ->numeric()
                                ->default(2),
                            TextInput::make('font_size_pt')
                                ->label('Font Size (pt)')
                                ->numeric()
                                ->default(6),
                        ]),
                    ]),
                Section::make('Label Content')
                    ->schema([
                        Select::make('code_type')
                            ->label('Code Type')
                            ->options(LabelCodeType::class)
                            ->default(LabelCodeType::Qr)
                            ->required(),
                        Grid::make(3)->schema([
                            Toggle::make('show_logo')->label('Show Logo')->default(true),
                            Toggle::make('show_company_name')->label('Company Name')->default(true),
                            Toggle::make('show_asset_name')->label('Asset Name')->default(true),
                            Toggle::make('show_branch')->label('Branch'),
                            Toggle::make('show_acquisition_date')->label('Acquisition Date'),
                        ]),
                    ]),
                Grid::make(2)->schema([
                    Toggle::make('is_default')
                        ->label('Set as Default Template')
                        ->helperText('Only one template can be the default.'),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('size')
                    ->label('Size')
                    ->state(fn (LabelTemplate $record): string => "{$record->width_mm} × {$record->height_mm} mm"),
                TextColumn::make('columns')
                    ->label('Per Row'),
                TextColumn::make('code_type')
                    ->label('Code Type')
                    ->badge(),
                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageLabelTemplates::route('/'),
        ];
    }
}
