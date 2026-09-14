<?php

namespace App\Filament\Actions;

use App\Enums\DepreciationBook;
use App\Exceptions\DepreciationException;
use App\Filament\Resources\DepreciationPeriods\DepreciationPeriodResource;
use App\Models\DepreciationPeriod;
use App\Services\DepreciationRunner;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Prepares or refreshes the draft depreciation for a month of one book.
 */
class CalculateDepreciationAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'calculateDepreciation';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Calculate Month')
            ->icon('heroicon-o-calculator')
            ->modalHeading('Calculate Depreciation')
            ->modalDescription('Builds a draft for review. A posted month cannot be recalculated.')
            ->modalSubmitActionLabel('Calculate')
            ->visible(fn (): bool => (bool) Auth::user()?->can('create', DepreciationPeriod::class))
            ->schema([
                Select::make('book')
                    ->label('Book')
                    ->options(DepreciationBook::class)
                    ->default(DepreciationBook::Commercial->value)
                    ->required(),
                DatePicker::make('month')
                    ->label('Month')
                    ->native(false)
                    ->displayFormat('F Y')
                    ->default(now()->startOfMonth())
                    ->required(),
            ])
            ->action(function (array $data, Component $livewire): void {
                $book = $data['book'] instanceof DepreciationBook ? $data['book'] : DepreciationBook::from($data['book']);

                try {
                    $period = app(DepreciationRunner::class)->calculate($book, CarbonImmutable::parse($data['month']));
                } catch (DepreciationException $exception) {
                    Notification::make()->title('Depreciation not calculated')->body($exception->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()
                    ->title('Draft calculated')
                    ->body("{$period->asset_count} asset(s), total Rp ".number_format((float) $period->total_amount, 2, ',', '.'))
                    ->success()
                    ->send();

                $livewire->redirect(DepreciationPeriodResource::getUrl('view', ['record' => $period]));
            });
    }
}
