<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class ManageSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 30;

    protected static ?string $title = 'General Settings';

    protected static ?string $navigationLabel = 'General Settings';

    /** @var array<string, mixed> */
    public array $data = [];

    /**
     * The setting keys this page manages.
     */
    private const KEYS = [
        'company_name',
        'company_address',
        'company_city',
        'company_logo_path',
        'asset_code_format',
        'asset_code_sequence_length',
        'fiscal_year_start_month',
    ];

    public function mount(): void
    {
        $this->form->fill(
            collect(self::KEYS)
                ->mapWithKeys(fn (string $key): array => [$key => Setting::get($key)])
                ->all()
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Company Identity')
                    ->description('Shown on asset labels and handover documents.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('company_name')
                                ->label('Company Name')
                                ->required()
                                ->maxLength(255),
                            TextInput::make('company_city')
                                ->label('City')
                                ->helperText('Used on the date line of the handover document.')
                                ->maxLength(255),
                        ]),
                        Textarea::make('company_address')
                            ->label('Address')
                            ->rows(2),
                        FileUpload::make('company_logo_path')
                            ->label('Logo')
                            ->image()
                            ->disk('public')
                            ->directory('branding')
                            ->imageEditor()
                            ->maxSize(1024),
                    ]),
                Section::make('Asset Numbering')
                    ->description('A change of format only applies to assets created afterwards. Existing asset codes stay as they are.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('asset_code_format')
                                ->label('Asset Code Format')
                                ->required()
                                ->helperText('Tokens: {CATEGORY} {BRANCH} {YY} {YYYY} {MM} {SEQ}')
                                ->placeholder('{CATEGORY}-{BRANCH}-{YY}{MM}-{SEQ}')
                                ->rule('regex:/\{SEQ\}/')
                                ->validationMessages(['regex' => 'The format must contain the {SEQ} token.']),
                            TextInput::make('asset_code_sequence_length')
                                ->label('Sequence Length')
                                ->numeric()
                                ->minValue(3)
                                ->maxValue(8)
                                ->default(4)
                                ->required(),
                        ]),
                    ]),
                Section::make('Period')
                    ->schema([
                        Select::make('fiscal_year_start_month')
                            ->label('Fiscal Year Start Month')
                            ->options([
                                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
                            ])
                            ->default(1)
                            ->required(),
                    ]),
            ]);
    }

    /**
     * Renders the form the way Filament's own pages do, so the save button gets
     * the panel's spacing. Utility classes in a custom Blade view are not compiled
     * into the panel CSS, which left the button flush against the card.
     */
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make($this->getFormActions())
                            ->alignment($this->getFormActionsAlignment())
                            ->key('form-actions'),
                    ]),
            ]);
    }

    /**
     * @return array<int, Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function save(): void
    {
        $values = $this->form->getState();

        Setting::setMany($values);

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }
}
