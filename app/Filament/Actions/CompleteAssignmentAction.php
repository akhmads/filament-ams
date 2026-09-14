<?php

namespace App\Filament\Actions;

use App\Enums\AssignmentStatus;
use App\Exceptions\AssetTransitionException;
use App\Models\AssetAssignment;
use App\Services\AssignmentService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * Completes a handover document, moving every asset it lists to the destination.
 */
class CompleteAssignmentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'completeAssignment';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Complete')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Complete Handover Document')
            ->modalDescription('Every asset on this document will be moved and the document can no longer be edited.')
            ->modalSubmitActionLabel('Yes, complete it')
            ->visible(fn (AssetAssignment $record): bool => $record->status === AssignmentStatus::Draft)
            ->action(function (AssetAssignment $record): void {
                try {
                    app(AssignmentService::class)->complete($record);
                } catch (AssetTransitionException $exception) {
                    Notification::make()
                        ->title('Document could not be completed')
                        ->body($exception->getMessage())
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Handover completed')
                    ->body("{$record->items->count()} asset(s) moved.")
                    ->success()
                    ->send();
            });
    }
}
