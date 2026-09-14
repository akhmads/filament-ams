<?php

namespace App\Filament\Actions;

use App\Models\AssetAssignment;
use App\Services\HandoverDocumentGenerator;
use Filament\Actions\Action;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Mengunduh berita acara serah terima dalam bentuk PDF.
 */
class PrintHandoverAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'printHandover';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Print BAST')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->action(function (AssetAssignment $record): StreamedResponse {
                $generator = app(HandoverDocumentGenerator::class);
                $pdf = $generator->render($record);

                return response()->streamDownload(
                    fn () => print ($pdf),
                    $generator->filename($record),
                );
            });
    }
}
