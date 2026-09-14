<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\Appearance;
use App\Support\Theme;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use UnitEnum;

/**
 * Panel look-and-feel options, kept apart from the general settings because the
 * list is expected to grow. Keys and defaults live in {@see Appearance}.
 */
class ManageAppearance extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaintBrush;

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 40;

    protected static ?string $title = 'Appearance';

    protected static ?string $navigationLabel = 'Appearance';

    /**
     * Both logo previews use this height, in px, so the two upload boxes line up
     * instead of each sizing itself to its own image.
     */
    private const LOGO_PREVIEW_HEIGHT = '120';

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill(
            collect($this->defaults())
                ->map(fn (mixed $default, string $key): mixed => Setting::get($key, $default))
                ->all()
        );
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Brand Logo')
                    ->description('Replaces the "AMS" name in the sidebar, topbar and login page. PNG, JPG or WEBP, up to 1 MB.')
                    ->schema([
                        Grid::make(2)->schema([
                            $this->logoUpload(Appearance::BRAND_LOGO)
                                ->label('Logo (Light Mode)')
                                ->helperText('Also used in dark mode if no dark logo is set.'),
                            $this->logoUpload(Appearance::BRAND_LOGO_DARK)
                                ->label('Logo (Dark Mode)')
                                ->helperText('Optional. A light version for dark backgrounds.'),
                        ]),
                        TextInput::make(Appearance::BRAND_LOGO_HEIGHT)
                            ->label('Logo Height')
                            ->numeric()
                            ->step(0.25)
                            ->minValue(Appearance::MIN_BRAND_LOGO_HEIGHT)
                            ->maxValue(Appearance::MAX_BRAND_LOGO_HEIGHT)
                            ->suffix('rem')
                            ->required()
                            ->helperText('Filament\'s default is 1.5rem. 1rem is 16px.'),
                    ]),
                Section::make('Theme Colors')
                    ->description('Bundled Filament palettes, applied as CSS variables. A change shows after the page reloads, with no rebuild.')
                    ->schema([
                        Grid::make(3)->schema([
                            $this->colorSelect(Theme::PRIMARY, 'Primary')
                                ->helperText('Buttons, links and active states.'),
                            $this->colorSelect(Theme::GRAY, 'Gray')
                                ->helperText('Surfaces, borders and body text.'),
                            $this->colorSelect(Theme::DANGER, 'Danger')
                                ->helperText('Delete actions and errors.'),
                            $this->colorSelect(Theme::INFO, 'Info')
                                ->helperText('Informational badges.'),
                            $this->colorSelect(Theme::SUCCESS, 'Success')
                                ->helperText('Available and completed states.'),
                            $this->colorSelect(Theme::WARNING, 'Warning')
                                ->helperText('Overdue and attention states.'),
                        ]),
                    ]),
                Section::make('Layout')
                    ->description('Applies to every page of the panel. Takes effect as soon as the page reloads.')
                    ->schema([
                        Select::make(Appearance::MAX_CONTENT_WIDTH)
                            ->label('Content Width')
                            ->options(Appearance::maxContentWidthOptions())
                            ->selectablePlaceholder(false)
                            ->required()
                            ->helperText('How wide the content area may grow before it stops and centres.'),
                    ]),
                Section::make('Navigation')
                    ->description('How moving between pages feels. Takes effect after the page reloads.')
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make(Appearance::SPA_MODE)
                                ->label('SPA Mode')
                                ->helperText('Switch pages without a full reload, so the panel feels instant.')
                                ->live(),
                            Toggle::make(Appearance::SPA_PREFETCHING)
                                ->label('Prefetch Links on Hover')
                                ->helperText('Start loading a page when the pointer rests on its link.')
                                ->visible(fn (Get $get): bool => (bool) $get(Appearance::SPA_MODE)),
                        ]),
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
                ->label('Save Appearance')
                ->submit('save')
                ->keyBindings(['mod+s']),
        ];
    }

    public function save(): void
    {
        Setting::setMany(
            Arr::only($this->form->getState(), array_keys($this->defaults())),
            group: Appearance::GROUP,
        );

        Notification::make()
            ->title('Appearance saved')
            ->success()
            ->send();

        // Appearance lives in the outer panel layout, which Livewire does not
        // re-render, so a full page visit is what makes the change visible.
        $this->redirect(static::getUrl(), navigate: false);
    }

    /**
     * Every key this page manages, with its default.
     *
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [...Appearance::defaults(), ...Theme::DEFAULTS];
    }

    private function colorSelect(string $key, string $label): Select
    {
        return Select::make($key)
            ->label($label)
            ->options(Theme::optionsHtml())
            ->allowHtml()
            ->native(false)
            ->searchable()
            ->selectablePlaceholder(false)
            ->required();
    }

    private function logoUpload(string $key): FileUpload
    {
        return FileUpload::make($key)
            ->disk(Appearance::LOGO_DISK)
            ->directory(Appearance::LOGO_DIRECTORY)
            ->visibility('public')
            ->acceptedFileTypes(Appearance::LOGO_MIME_TYPES)
            ->maxSize(Appearance::LOGO_MAX_KILOBYTES)
            ->imagePreviewHeight(self::LOGO_PREVIEW_HEIGHT);
    }
}
