{{-- A single label. Every size and position is inherited from pdf.labels. --}}
@php $asset = $label['asset']; @endphp
<div class="label" style="left: {{ $left }}mm; top: {{ $top }}mm;">
    @if ($hasQr)
        <img class="qr" src="{{ $label['qr'] }}" style="width: {{ $qrSize }}mm; height: {{ $qrSize }}mm;">
    @endif

    <div class="text">
        @if ($template->show_logo && $logo)
            <img class="logo" src="{{ $logo }}"><br>
        @endif
        @if ($template->show_company_name)
            <div class="company">{{ $companyName }}</div>
        @endif
        <div class="asset-code">{{ $asset->code }}</div>
        @if ($template->show_asset_name)
            <div class="asset-name">{{ \Illuminate\Support\Str::limit($asset->name, 32) }}</div>
        @endif
        @if ($template->show_branch)
            <div class="meta">{{ $asset->branch?->name }}</div>
        @endif
        @if ($template->show_acquisition_date)
            <div class="meta">{{ $asset->acquisition_date?->format('d/m/Y') }}</div>
        @endif
    </div>

    @if ($hasBarcode)
        <img class="barcode" src="{{ $label['barcode'] }}"
             style="width: {{ $contentWidth }}mm; height: {{ round($barcodeHeight * 0.85, 2) }}mm;">
    @endif
</div>
