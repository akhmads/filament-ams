<?php

namespace App\Services;

use App\Models\AssetDisposal;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Renders the asset disposal record (Berita Acara Penghapusan Aset) as a PDF.
 */
class DisposalDocumentGenerator
{
    public function render(AssetDisposal $disposal): string
    {
        $disposal->loadMissing(['lines.asset.category', 'createdBy', 'approvedBy', 'completedBy']);

        return Pdf::loadView('pdf.disposal', [
            'disposal' => $disposal,
            'companyName' => Setting::get('company_name', config('app.name')),
            'companyAddress' => Setting::get('company_address'),
            'city' => Setting::get('company_city', ''),
        ])->setPaper('a4', 'landscape')->output();
    }

    public function filename(AssetDisposal $disposal): string
    {
        return str_replace('/', '-', $disposal->number).'.pdf';
    }
}
