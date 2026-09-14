<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\LabelTemplate;
use App\Models\Setting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Renders a sheet of asset labels into a print-ready PDF.
 */
class LabelSheetGenerator
{
    /** Konversi milimeter ke poin (satuan Dompdf). */
    private const MM_TO_PT = 2.8346456693;

    public function __construct(
        private readonly LabelRenderer $renderer,
    ) {}

    /**
     * @param  Collection<int, Asset>  $assets
     */
    public function render(Collection $assets, LabelTemplate $template): string
    {
        // The single-row action passes a Support\Collection and the bulk action an
        // Eloquent one — normalise first so relations can be eager loaded.
        $assets = EloquentCollection::make($assets->all())->loadMissing(['category', 'branch']);

        $labels = $assets->map(fn (Asset $asset): array => [
            'asset' => $asset,
            'qr' => $template->code_type->includesQr() ? $this->renderer->qrDataUri($asset) : null,
            'barcode' => $template->code_type->includesBarcode() ? $this->renderer->barcodeDataUri($asset) : null,
        ]);

        $pdf = Pdf::loadView('pdf.labels', [
            'labels' => $labels,
            'template' => $template,
            'companyName' => Setting::get('company_name', config('app.name')),
            'logo' => $this->logoDataUri(),
        ]);

        $pdf->setPaper($this->paperSize($template));

        return $pdf->output();
    }

    /**
     * Marks an asset's label as printed, as a simple audit trail.
     *
     * @param  Collection<int, Asset>  $assets
     */
    public function markPrinted(Collection $assets): void
    {
        Asset::whereIn('id', $assets->pluck('id'))->update([
            'label_printed_at' => now(),
        ]);

        Asset::whereIn('id', $assets->pluck('id'))->increment('label_printed_count');
    }

    /**
     * Roll printers use paper the size of one label; multi-column printing
     * uses A4.
     *
     * @return array<int, float>|string
     */
    private function paperSize(LabelTemplate $template): array|string
    {
        if ($template->columns > 1) {
            return 'a4';
        }

        return [
            0.0,
            0.0,
            (float) $template->width_mm * self::MM_TO_PT,
            (float) $template->height_mm * self::MM_TO_PT,
        ];
    }

    private function logoDataUri(): ?string
    {
        $path = Setting::get('company_logo_path');

        if (blank($path)) {
            return null;
        }

        $absolute = storage_path('app/public/'.$path);

        if (! is_file($absolute)) {
            return null;
        }

        $mime = mime_content_type($absolute) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode((string) file_get_contents($absolute));
    }
}
