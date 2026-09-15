<?php

namespace App\Filament\Resources\AssetAudits\Pages;

use App\Enums\AssetAuditStatus;
use App\Enums\AssetCondition;
use App\Enums\AuditResult;
use App\Exceptions\AssetAuditException;
use App\Filament\Resources\AssetAudits\AssetAuditResource;
use App\Models\AssetAudit;
use App\Models\AssetAuditLine;
use App\Models\Location;
use App\Models\User;
use App\Services\AssetAuditService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Where assets are counted, on a phone or at a desk. A barcode scanner types the
 * code and presses Enter; the code can also be typed. The chosen room is kept in
 * the session, so opening a label's QR link with the phone camera records the
 * asset in this audit too.
 */
class ScanAssetAudit extends Page implements HasTable
{
    use InteractsWithRecord;
    use InteractsWithTable;

    /**
     * Session key holding the audit and room being counted.
     */
    public const SESSION_KEY = 'asset_audit_scan';

    protected static string $resource = AssetAuditResource::class;

    protected static ?string $title = 'Scan Assets';

    /** @var array<string, mixed> */
    public array $data = [];

    /**
     * @param  array<string, mixed>  $parameters
     */
    public static function canAccess(array $parameters = []): bool
    {
        $record = $parameters['record'] ?? null;

        return $record instanceof AssetAudit
            && $record->status === AssetAuditStatus::InProgress
            && (bool) Auth::user()?->can('update', $record);
    }

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        $remembered = session(self::SESSION_KEY);

        $this->form->fill([
            'location_id' => is_array($remembered) && ($remembered['audit'] ?? null) === $this->getRecord()->getKey()
                ? $remembered['location'] ?? null
                : null,
            'condition' => null,
            'code' => null,
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return "Scan Assets — {$this->getRecord()->number}";
    }

    public function getSubheading(): ?string
    {
        $summary = $this->audit()->summary();

        return "{$summary['scanned']} scanned of {$summary['expected']} listed · {$summary['misplaced']} in the wrong place · {$summary['unlisted']} not on the list";
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToAudit')
                ->label('Back to Audit')
                ->color('gray')
                ->url(fn (): string => AssetAuditResource::getUrl('view', ['record' => $this->getRecord()])),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Grid::make(['default' => 1, 'sm' => 2])->schema([
                    Select::make('location_id')
                        ->label('Room / Warehouse')
                        ->helperText('Where you are counting now.')
                        ->options(fn (): array => $this->roomOptions())
                        ->searchable()
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (mixed $state) => $this->rememberRoom($state)),
                    Select::make('condition')
                        ->label('Condition')
                        ->placeholder('As recorded')
                        ->options(AssetCondition::class),
                ]),
                TextInput::make('code')
                    ->label('Asset Code')
                    ->placeholder('Scan or type the code')
                    ->helperText('A barcode scanner records the asset on its own. With a room chosen, opening a label\'s QR with the phone camera records it too.')
                    ->autofocus()
                    ->autocomplete(false)
                    ->required()
                    ->maxLength(100),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('scan')
                    ->footer([
                        Actions::make([
                            Action::make('scan')
                                ->label('Record')
                                ->submit('scan'),
                        ])->key('form-actions'),
                    ]),
                EmbeddedTable::make(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Scanned So Far')
            ->query(fn (): Builder => AssetAuditLine::query()
                ->where('asset_audit_id', $this->getRecord()->getKey())
                ->whereNotNull('scanned_at')
                ->with(['asset', 'scannedLocation']))
            ->defaultSort('scanned_at', 'desc')
            ->emptyStateHeading('Nothing scanned yet')
            ->columns([
                TextColumn::make('asset.code')->label('Code')->badge()->color('gray'),
                TextColumn::make('asset.name')->label('Asset')->wrap(),
                TextColumn::make('result')->label('Result')->badge()
                    ->description(fn (AssetAuditLine $record): ?string => $record->is_expected ? null : 'Not on the list'),
                TextColumn::make('scannedLocation.name')->label('Found In'),
                TextColumn::make('observed_condition')->label('Condition')->badge(),
                TextColumn::make('scanned_at')->label('Time')->time('H:i'),
            ])
            ->paginated([10, 25, 50]);
    }

    public function scan(): void
    {
        $data = $this->form->getState();

        /** @var User $user */
        $user = Auth::user();

        try {
            $line = app(AssetAuditService::class)->scan(
                audit: $this->audit(),
                code: (string) $data['code'],
                foundIn: Location::query()->findOrFail($data['location_id']),
                scannedBy: $user,
                condition: AssetCondition::tryFrom((string) ($data['condition'] ?? '')),
            );
        } catch (AssetAuditException $exception) {
            Notification::make()->title('Not recorded')->body($exception->getMessage())->danger()->send();
            $this->data['code'] = null;

            return;
        }

        Notification::make()
            ->title("{$line->asset->code} recorded")
            ->body($line->scanSummary())
            ->status($line->result === AuditResult::Found && $line->is_expected ? 'success' : 'warning')
            ->send();

        $this->data['code'] = null;
        $this->data['condition'] = null;
    }

    private function audit(): AssetAudit
    {
        /** @var AssetAudit $audit */
        $audit = $this->getRecord();

        return $audit;
    }

    private function rememberRoom(mixed $locationId): void
    {
        if (blank($locationId)) {
            session()->forget(self::SESSION_KEY);

            return;
        }

        session([self::SESSION_KEY => ['audit' => $this->getRecord()->getKey(), 'location' => (int) $locationId]]);
    }

    /**
     * Rooms and warehouses within the audit's location, or all of them when the
     * audit covers a department instead.
     *
     * @return array<int, string>
     */
    private function roomOptions(): array
    {
        $audit = $this->audit();

        return Location::query()
            ->active()
            ->holdsAssets()
            ->when($audit->location_id !== null, fn (Builder $query) => $query->whereDescendantOrSelf($audit->location_id))
            ->defaultOrder()
            ->get()
            ->mapWithKeys(fn (Model $location): array => [$location->getKey() => $location->full_name])
            ->all();
    }
}
