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
 * Posts a period: recalculates it one last time, then locks it for good.
 */
class PostDepreciationAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'postDepreciation';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Post')
            ->icon('heroicon-o-lock-closed')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading(fn (DepreciationPeriod $record): string => "Post {$record->book->getLabel()} depreciation for {$record->period_label}?")
            ->modalDescription('The period is recalculated once more and then locked. Posted months cannot be changed, and assets depreciated in them keep their cost and depreciation settings frozen.')
            ->modalSubmitActionLabel('Post and lock')
            ->visible(fn (DepreciationPeriod $record): bool => ! $record->isPosted()
                && (bool) Auth::user()?->can('update', $record))
            ->action(function (DepreciationPeriod $record, Component $livewire): void {
                try {
                    $period = app(DepreciationRunner::class)->post($record->book, CarbonImmutable::parse($record->period), Auth::user());
                } catch (DepreciationException $exception) {
                    Notification::make()->title('Depreciation not posted')->body($exception->getMessage())->danger()->persistent()->send();

                    return;
                }

                Notification::make()->title('Depreciation posted')->body("{$period->book->getLabel()} — {$period->period_label} is now locked.")->success()->send();

                $livewire->redirect(DepreciationPeriodResource::getUrl('view', ['record' => $period]));
            });
    }
}
