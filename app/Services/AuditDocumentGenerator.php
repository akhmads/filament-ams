<?php

namespace App\Services;

use App\Enums\AuditResult;
use App\Models\AssetAudit;
use App\Models\AssetAuditLine;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renders the audit record (Berita Acara Hasil Audit Aset) as a PDF: a summary of
 * the count and every finding with the correction made.
 */
class AuditDocumentGenerator
{
    public function render(AssetAudit $audit): string
    {
        $audit->loadMissing(['location', 'department', 'category', 'createdBy', 'countedBy', 'closedBy']);

        $findings = $audit->lines()
            ->with(['asset', 'expectedLocation', 'scannedLocation'])
            ->orderBy('id')
            ->get()
            ->filter(fn (AssetAuditLine $line): bool => $line->result !== AuditResult::Found
                || ! $line->is_expected
                || $line->hasConditionChanged())
            ->values();

        return Pdf::loadView('pdf.audit', [
            'audit' => $audit,
            'summary' => $audit->summary(),
            'findings' => $findings,
            'companyName' => Setting::get('company_name', config('app.name')),
            'companyAddress' => Setting::get('company_address'),
            'city' => Setting::get('company_city', ''),
        ])->setPaper('a4', 'landscape')->output();
    }

    public function filename(AssetAudit $audit): string
    {
        return str_replace('/', '-', $audit->number).'.pdf';
    }
}
