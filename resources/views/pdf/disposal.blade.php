{{-- Berita Acara Penghapusan Aset --}}
@php
    // Dokumen ini tetap berbahasa Indonesia walau locale aplikasi berbahasa Inggris,
    // jadi nama hari dan bulan dikunci di sini. copy() dipakai agar instance tanggal
    // milik model tidak ikut berubah locale-nya.
    $tanggal = fn (?\Carbon\CarbonInterface $date, string $format): string => $date
        ? $date->copy()->locale('id')->translatedFormat($format)
        : '-';

    // Rugi ditulis dalam kurung, sesuai kebiasaan laporan keuangan.
    $rupiah = function (?string $amount): string {
        if ($amount === null) {
            return '-';
        }

        $formatted = number_format(abs((float) $amount), 2, ',', '.');

        return (float) $amount < 0 ? "({$formatted})" : $formatted;
    };

    $isCompleted = $disposal->status->value === 'completed';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 14mm 14mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #111; }
        .header { border-bottom: 1.5pt solid #111; padding-bottom: 6pt; margin-bottom: 12pt; }
        .company { font-size: 13pt; font-weight: bold; }
        .company-address { font-size: 8.5pt; color: #555; }
        h1 { font-size: 12pt; text-align: center; margin: 0 0 2pt; text-transform: uppercase; letter-spacing: 0.5pt; }
        .doc-number { text-align: center; font-size: 9pt; color: #555; margin-bottom: 12pt; }
        table.meta { width: 100%; margin-bottom: 10pt; }
        table.meta td { padding: 2pt 0; vertical-align: top; }
        table.meta td.key { width: 22%; color: #555; }
        table.meta td.sep { width: 2%; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 10pt; }
        table.items th, table.items td { border: 0.5pt solid #999; padding: 3pt 4pt; font-size: 8pt; }
        table.items th { background: #f0f0f0; text-align: left; }
        table.items td.num { text-align: center; width: 4%; }
        table.items td.amount, table.items th.amount { text-align: right; }
        table.items tr.total td { font-weight: bold; background: #f7f7f7; }
        .note { font-size: 9pt; margin-bottom: 14pt; line-height: 1.5; }
        table.sign { width: 100%; margin-top: 6pt; }
        table.sign td { width: 33%; text-align: center; vertical-align: top; font-size: 9pt; }
        .sign-box { height: 60pt; }
        .sign-name { border-top: 0.5pt solid #111; padding-top: 3pt; display: inline-block; min-width: 60%; }
        .draft { color: #b91c1c; font-weight: bold; font-size: 9pt; text-align: center; margin-bottom: 8pt; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">{{ $companyName }}</div>
        @if ($companyAddress)
            <div class="company-address">{{ $companyAddress }}</div>
        @endif
    </div>

    @unless ($isCompleted)
        <div class="draft">— DRAF, BELUM BERLAKU —</div>
    @endunless

    <h1>Berita Acara Penghapusan Aset</h1>
    <div class="doc-number">Nomor: {{ $disposal->number }}</div>

    <p class="note">
        Pada hari ini, {{ $tanggal($disposal->disposal_date, 'l, d F Y') }}, telah dilakukan penghapusan aset milik
        {{ $companyName }} dari daftar aset perusahaan dengan rincian sebagai berikut.
    </p>

    <table class="meta">
        <tr>
            <td class="key">Alasan penghapusan</td>
            <td class="sep">:</td>
            <td>{{ $disposal->reason }}</td>
        </tr>
        @if ($disposal->recipient_name)
            <tr>
                <td class="key">Pembeli / penerima</td>
                <td class="sep">:</td>
                <td>{{ $disposal->recipient_name }}</td>
            </tr>
        @endif
        @if ($disposal->reference_number)
            <tr>
                <td class="key">Nomor referensi</td>
                <td class="sep">:</td>
                <td>{{ $disposal->reference_number }}</td>
            </tr>
        @endif
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="num">No</th>
                <th>Kode Aset</th>
                <th>Nama Aset</th>
                <th>Kategori</th>
                <th>Tgl Perolehan</th>
                <th class="amount">Harga Perolehan</th>
                <th class="amount">Nilai Buku</th>
                <th>Cara Penghapusan</th>
                <th class="amount">Nilai Jual</th>
                <th class="amount">Laba / (Rugi)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($disposal->lines as $index => $line)
                <tr>
                    <td class="num">{{ $index + 1 }}</td>
                    <td>{{ $line->asset->code }}</td>
                    <td>{{ $line->asset->name }}</td>
                    <td>{{ $line->asset->category?->name }}</td>
                    <td>{{ $tanggal($line->asset->acquisition_date, 'd/m/Y') }}</td>
                    <td class="amount">{{ $rupiah($line->acquisition_cost ?? $line->asset->acquisition_cost) }}</td>
                    <td class="amount">{{ $rupiah($line->commercial_book_value) }}</td>
                    <td>{{ $line->method->printedLabel() }}</td>
                    <td class="amount">{{ $rupiah($line->proceeds) }}</td>
                    <td class="amount">{{ $rupiah($line->commercial_gain_loss) }}</td>
                </tr>
            @endforeach
            @if ($isCompleted)
                <tr class="total">
                    <td colspan="5">Jumlah</td>
                    <td class="amount">{{ $rupiah($disposal->lineTotal('acquisition_cost')) }}</td>
                    <td class="amount">{{ $rupiah($disposal->lineTotal('commercial_book_value')) }}</td>
                    <td></td>
                    <td class="amount">{{ $rupiah($disposal->lineTotal('proceeds')) }}</td>
                    <td class="amount">{{ $rupiah($disposal->lineTotal('commercial_gain_loss')) }}</td>
                </tr>
            @endif
        </tbody>
    </table>

    <p class="note">
        Nilai buku dihitung dari penyusutan komersial yang telah diposting sampai dengan bulan sebelum bulan penghapusan.
        @if ($isCompleted)
            Laba / (rugi) penghapusan menurut buku fiskal: Rp {{ $rupiah($disposal->lineTotal('fiscal_gain_loss')) }}.
        @endif
        Sejak tanggal berita acara ini, aset di atas tidak lagi tercatat sebagai aset perusahaan.
        @if ($disposal->notes)
            <br><strong>Catatan:</strong> {{ $disposal->notes }}
        @endif
    </p>

    <table class="sign">
        <tr>
            <td>
                {{ $city }}{{ $city ? ', ' : '' }}{{ $tanggal($disposal->disposal_date, 'd F Y') }}<br>
                Diusulkan oleh
                <div class="sign-box"></div>
                <div class="sign-name">{{ $disposal->createdBy?->name ?? '(...........................)' }}</div>
            </td>
            <td>
                <br>
                Disetujui oleh
                <div class="sign-box"></div>
                <div class="sign-name">{{ $disposal->approvedBy?->name ?? '(...........................)' }}</div>
            </td>
            <td>
                <br>
                Dilaksanakan oleh
                <div class="sign-box"></div>
                <div class="sign-name">{{ $disposal->completedBy?->name ?? '(...........................)' }}</div>
            </td>
        </tr>
    </table>
</body>
</html>
