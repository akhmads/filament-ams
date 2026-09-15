<?php

namespace App\Filament\Actions;

use App\Models\AssetDisposal;
use App\Services\DisposalDocumentGenerator;
use Filament\Actions\Action;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads the disposal record (Berita Acara Penghapusan Aset) as a PDF. Until
 * the disposal is completed it prints as a draft.
 */
class PrintDisposalAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'printDisposal';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Print Record')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->action(function (AssetDisposal $record): StreamedResponse {
                $generator = app(DisposalDocumentGenerator::class);
                $pdf = $generator->render($record);

                return response()->streamDownload(
                    fn () => print ($pdf),
                    $generator->filename($record),
                );
            });
    }
}
