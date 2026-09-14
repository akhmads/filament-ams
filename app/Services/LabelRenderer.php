<?php

namespace App\Services;

use App\Models\Asset;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Picqer\Barcode\Renderers\PngRenderer;
use Picqer\Barcode\Types\TypeCode128;

/**
 * Produces QR and barcode images as data URIs, ready to drop into the label
 * HTML that is rendered to PDF.
 */
class LabelRenderer
{
    public function qrDataUri(Asset $asset, int $size = 220): string
    {
        $qr = new QrCode(
            data: $asset->qr_url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: $size,
            margin: 0,
        );

        return (new PngWriter)->write($qr)->getDataUri();
    }

    public function barcodeDataUri(Asset $asset, int $widthFactor = 2, int $height = 40): string
    {
        $barcode = (new TypeCode128)->getBarcode($asset->code);

        $renderer = new PngRenderer;
        $png = $renderer->render($barcode, $barcode->getWidth() * $widthFactor, $height);

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
