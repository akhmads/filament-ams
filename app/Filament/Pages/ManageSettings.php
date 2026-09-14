<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\Appearance;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use UnitEnum;

class ManageSettings extends Page
{
    protected string $view = 'filament.pages.manage-settings';

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
        Appearance::MAX_CONTENT_WIDTH,
    ];

    /**
     * Keys stored under the `appearance` group rather than the general one.
     */
    private const APPEARANCE_KEYS = [
        Appearance::MAX_CONTENT_WIDTH,
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
                Section::make('Appearance')
                    ->description('Applies to every page of the panel. Takes effect as soon as the page reloads.')
                    ->schema([
                        Select::make(Appearance::MAX_CONTENT_WIDTH)
                            ->label('Content Width')
                            ->options(Appearance::maxContentWidthOptions())
                            ->default(Appearance::DEFAULT_MAX_CONTENT_WIDTH->value)
                            ->selectablePlaceholder(false)
                            ->required()
                            ->helperText('How wide the content area may grow before it stops and centres.'),
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
     * @return array<int, Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->submit('save'),
        ];
    }

    public function save(): void
    {
        $values = $this->form->getState();

        Setting::setMany(Arr::except($values, self::APPEARANCE_KEYS));
        Setting::setMany(Arr::only($values, self::APPEARANCE_KEYS), group: 'appearance');

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();

        // The panel width lives in the outer layout, which Livewire does not
        // re-render, so a full page visit is what makes the change visible.
        $this->redirect(static::getUrl(), navigate: false);
    }
}
