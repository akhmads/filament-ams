{{--
    A sheet of asset labels.

    The layout uses absolute positioning rather than tables: absolutely positioned
    elements sit outside the normal flow and so can never push a page break. That
    guarantees one label per box, and content that runs long is clipped instead of
    spilling onto the next page.

    Two print modes:
    - roll label printers (columns = 1): one label per page, the paper being the
      same size as the label;
    - A4 sheets (columns > 1): labels laid out as a grid on each page.
--}}
@php
    use Illuminate\Support\Str;

    $hasQr = $template->code_type->includesQr();
    $hasBarcode = $template->code_type->includesBarcode();
    $isSheet = $template->columns > 1;

    $labelWidth = (float) $template->width_mm;
    $labelHeight = (float) $template->height_mm;
    $margin = (float) $template->margin_mm;

    $contentWidth = $labelWidth - 2 * $margin;
    $contentHeight = $labelHeight - 2 * $margin;

    $barcodeHeight = $hasBarcode ? round($contentHeight * 0.30, 2) : 0.0;
    $topHeight = round($contentHeight - $barcodeHeight - ($hasBarcode ? 0.8 : 0), 2);

    $gap = 1.5;
    $qrSize = $hasQr ? round(min($topHeight, $contentWidth * 0.38), 2) : 0.0;
    $textLeft = round($margin + ($hasQr ? $qrSize + $gap : 0), 2);
    $textWidth = round($contentWidth - $qrSize - ($hasQr ? $gap : 0), 2);

    $mmToPt = 2.8346456693;
    $lineHeight = 1.15;

    // Average DejaVu Sans character width in em. Bold text full of capitals and
    // digits is far wider than regular text, so the two are measured separately.
    $boldCharWidth = 0.72;
    $regularCharWidth = 0.60;

    $textWidthPt = max(1.0, $textWidth * $mmToPt);
    $availableHeightPt = $topHeight * $mmToPt;

    $lineCount = fn (string $text, float $size, float $charWidth): int => max(
        1,
        (int) ceil((strlen($text) * $charWidth * $size) / $textWidthPt),
    );

    // The longest label in a batch sets the font size for the whole batch, so the
    // printed result stays uniform.
    $longestCode = $labels->map(fn (array $l): string => $l['asset']->code)
        ->sortByDesc(fn (string $code): int => strlen($code))
        ->first() ?? '';
    $longestName = $labels->map(fn (array $l): string => Str::limit($l['asset']->name, 32))
        ->sortByDesc(fn (string $name): int => strlen($name))
        ->first() ?? '';
    $companyLine = Str::limit($companyName, 30);

    $baseFont = (float) $template->font_size_pt;

    // Step 1 — the asset code must fit on one line; it is the point of the label.
    $codeFontSize = min($baseFont + 1, $textWidthPt / max(1, strlen($longestCode)) / $boldCharWidth);

    // Step 2 — shrink the text until its height fits inside the box.
    $scale = 1.0;

    for ($attempt = 0; $attempt < 16; $attempt++) {
        $code = $codeFontSize * $scale;
        $name = $baseFont * $scale;
        $company = ($baseFont - 1) * $scale;
        $meta = ($baseFont - 1.5) * $scale;

        $height = $lineCount($longestCode, $code, $boldCharWidth) * $code * $lineHeight;

        if ($template->show_company_name) {
            $height += $lineCount($companyLine, $company, $regularCharWidth) * $company * $lineHeight;
        }

        if ($template->show_asset_name) {
            $height += $lineCount($longestName, $name, $regularCharWidth) * $name * $lineHeight;
        }

        if ($template->show_branch) {
            $height += $meta * $lineHeight;
        }

        if ($template->show_acquisition_date) {
            $height += $meta * $lineHeight;
        }

        if ($template->show_logo && $logo) {
            $height += $topHeight * 0.22 * $mmToPt;
        }

        if ($height <= $availableHeightPt || $code <= 4.0) {
            break;
        }

        $scale -= 0.07;
    }

    $codeFontSize = round(max(4.0, $codeFontSize * $scale), 2);
    $nameFontSize = round(max(3.5, $baseFont * $scale), 2);
    $companyFontSize = round(max(3.5, ($baseFont - 1) * $scale), 2);
    $metaFontSize = round(max(3.5, ($baseFont - 1.5) * $scale), 2);

    // Grid layout for printing on A4.
    $pageMargin = 8.0;
    $usableWidth = 210.0 - 2 * $pageMargin;
    $usableHeight = 297.0 - 2 * $pageMargin;

    $columns = $isSheet ? max(1, min($template->columns, (int) floor($usableWidth / $labelWidth))) : 1;
    $rowsPerPage = $isSheet ? max(1, (int) floor($usableHeight / $labelHeight)) : 1;
    $perPage = $columns * $rowsPerPage;

    $pages = $isSheet ? $labels->chunk($perPage) : $labels->chunk(1);

    $shared = [
        'template' => $template,
        'logo' => $logo,
        'companyName' => $companyLine,
        'hasQr' => $hasQr,
        'hasBarcode' => $hasBarcode,
        'margin' => $margin,
        'contentWidth' => $contentWidth,
        'qrSize' => $qrSize,
        'topHeight' => $topHeight,
        'textLeft' => $textLeft,
        'barcodeHeight' => $barcodeHeight,
    ];
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: {{ $isSheet ? $pageMargin.'mm' : '0' }}; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: {{ $baseFont }}pt;
            margin: 0;
            padding: 0;
        }
        .page { position: relative; width: {{ $isSheet ? $usableWidth : $labelWidth }}mm; height: {{ $isSheet ? $usableHeight : $labelHeight }}mm; }
        .page-break { page-break-after: always; }

        .label {
            position: absolute;
            width: {{ $labelWidth }}mm;
            height: {{ $labelHeight }}mm;
            overflow: hidden;
            @if ($isSheet) border: 0.2mm dotted #bbb; @endif
        }
        .label .qr { position: absolute; left: {{ $margin }}mm; top: {{ $margin }}mm; }
        .label .text { position: absolute; top: {{ $margin }}mm; left: {{ $textLeft }}mm; width: {{ $textWidth }}mm; }
        .label .barcode { position: absolute; left: {{ $margin }}mm; bottom: {{ $margin }}mm; }

        /* line-height is set explicitly: Dompdf's default is taller than 1.15, so the
           height calculation above would not match without it. */
        .company { font-size: {{ $companyFontSize }}pt; line-height: {{ round($companyFontSize * $lineHeight, 2) }}pt; color: #555; }
        .asset-code { font-weight: bold; font-size: {{ $codeFontSize }}pt; line-height: {{ round($codeFontSize * $lineHeight, 2) }}pt; letter-spacing: 0.1pt; }
        .asset-name { font-size: {{ $nameFontSize }}pt; line-height: {{ round($nameFontSize * $lineHeight, 2) }}pt; }
        .meta { font-size: {{ $metaFontSize }}pt; line-height: {{ round($metaFontSize * $lineHeight, 2) }}pt; color: #666; }
        .logo { max-height: {{ max(2.5, round($topHeight * 0.22, 2)) }}mm; }
    </style>
</head>
<body>
@foreach ($pages as $page)
    <div class="page">
        @foreach ($page as $index => $label)
            @include('pdf.partials.label', [
                ...$shared,
                'label' => $label,
                'left' => $isSheet ? round(($index % $columns) * $labelWidth, 2) : 0,
                'top' => $isSheet ? round(intdiv($index, $columns) * $labelHeight, 2) : 0,
            ])
        @endforeach
    </div>
    @if (! $loop->last)
        <div class="page-break"></div>
    @endif
@endforeach
</body>
</html>
