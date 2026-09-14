<?php

namespace App\Services;

use App\Models\AssetAssignment;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;

/**
 * Merender Berita Acara Serah Terima (BAST) menjadi PDF.
 */
class HandoverDocumentGenerator
{
    public function render(AssetAssignment $assignment): string
    {
        $assignment->loadMissing(['items.asset.category', 'branch', 'toEmployee.department', 'toLocation', 'handedOverBy', 'toDepartment']);

        return Pdf::loadView('pdf.handover', [
            'assignment' => $assignment,
            'companyName' => Setting::get('company_name', config('app.name')),
            'companyAddress' => Setting::get('company_address'),
            'city' => $assignment->branch?->city ?? Setting::get('company_city', ''),
        ])->setPaper('a4')->output();
    }

    public function filename(AssetAssignment $assignment): string
    {
        return str_replace('/', '-', $assignment->number).'.pdf';
    }
}
