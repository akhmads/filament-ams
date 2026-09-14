<?php

namespace App\Filament\Actions;

use App\Exceptions\DepreciationException;
use App\Filament\Resources\DepreciationPeriods\DepreciationPeriodResource;
use App\Models\DepreciationPeriod;
use App\Services\DepreciationRunner;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Refreshes a draft period after assets have changed.
 */
class RecalculateDepreciationAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'recalculateDepreciation';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Recalculate')
            ->icon('heroicon-o-arrow-path')
            ->color('gray')
            ->visible(fn (DepreciationPeriod $record): bool => ! $record->isPosted()
                && (bool) Auth::user()?->can('update', $record))
            ->action(function (DepreciationPeriod $record, Component $livewire): void {
                try {
                    $period = app(DepreciationRunner::class)->calculate($record->book, CarbonImmutable::parse($record->period));
                } catch (DepreciationException $exception) {
                    Notification::make()->title('Depreciation not recalculated')->body($exception->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()->title('Draft recalculated')->success()->send();

                $livewire->redirect(DepreciationPeriodResource::getUrl('view', ['record' => $period]));
            });
    }
}
