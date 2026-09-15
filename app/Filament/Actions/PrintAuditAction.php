<?php

namespace App\Filament\Actions;

use App\Models\AssetAudit;
use App\Services\AuditDocumentGenerator;
use Filament\Actions\Action;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Downloads the audit record (Berita Acara Hasil Audit Aset) as a PDF. Until the
 * audit is closed it prints as a draft.
 */
class PrintAuditAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'printAudit';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->label('Print Record')
            ->icon('heroicon-o-printer')
            ->color('gray')
            ->action(function (AssetAudit $record): StreamedResponse {
                $generator = app(AuditDocumentGenerator::class);
                $pdf = $generator->render($record);

                return response()->streamDownload(
                    fn () => print ($pdf),
                    $generator->filename($record),
                );
            });
    }
}
