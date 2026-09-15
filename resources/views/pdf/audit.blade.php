{{-- Berita Acara Hasil Audit Aset --}}
@php
    // Dokumen ini tetap berbahasa Indonesia walau locale aplikasi berbahasa Inggris,
    // jadi nama hari dan bulan dikunci di sini. copy() dipakai agar instance tanggal
    // milik model tidak ikut berubah locale-nya.
    $tanggal = fn (?\Carbon\CarbonInterface $date, string $format): string => $date
        ? $date->copy()->locale('id')->translatedFormat($format)
        : '-';

    $isCompleted = $audit->status->value === 'completed';

    $tindakLanjut = function (\App\Models\AssetAuditLine $line) use ($isCompleted): string {
        $actions = array_filter([
            $line->mark_lost ? 'Ditandai hilang' : null,
            $line->apply_relocation ? 'Dipindahkan ke lokasi temuan' : null,
            $line->apply_condition ? 'Kondisi diperbarui' : null,
        ]);

        if ($actions === []) {
            return $isCompleted ? 'Tidak ada' : '-';
        }

        return implode(', ', $actions).($isCompleted ? '' : ' (usulan)');
    };
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
        h2 { font-size: 10pt; margin: 10pt 0 4pt; }
        .doc-number { text-align: center; font-size: 9pt; color: #555; margin-bottom: 12pt; }
        table.meta { width: 100%; margin-bottom: 8pt; }
        table.meta td { padding: 2pt 0; vertical-align: top; }
        table.meta td.key { width: 22%; color: #555; }
        table.meta td.sep { width: 2%; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 10pt; }
        table.items th, table.items td { border: 0.5pt solid #999; padding: 3pt 4pt; font-size: 8pt; }
        table.items th { background: #f0f0f0; text-align: left; }
        table.items td.num { text-align: center; width: 4%; }
        table.items td.count { text-align: right; }
        .note { font-size: 9pt; margin-bottom: 14pt; line-height: 1.5; }
        table.sign { width: 100%; margin-top: 6pt; }
        table.sign td { width: 50%; text-align: center; vertical-align: top; font-size: 9pt; }
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

    <h1>Berita Acara Hasil Audit Aset</h1>
    <div class="doc-number">Nomor: {{ $audit->number }}</div>

    <table class="meta">
        <tr>
            <td class="key">Nama audit</td>
            <td class="sep">:</td>
            <td>{{ $audit->title }}</td>
        </tr>
        <tr>
            <td class="key">Pelaksanaan</td>
            <td class="sep">:</td>
            <td>{{ $tanggal($audit->started_at, 'd F Y') }} s.d. {{ $tanggal($audit->counted_at, 'd F Y') }}</td>
        </tr>
        <tr>
            <td class="key">Lokasi</td>
            <td class="sep">:</td>
            <td>{{ $audit->location?->full_name ?? 'Semua lokasi' }}</td>
        </tr>
        <tr>
            <td class="key">Departemen</td>
            <td class="sep">:</td>
            <td>{{ $audit->department?->name ?? 'Semua departemen' }}</td>
        </tr>
        @if ($audit->category)
            <tr>
                <td class="key">Kategori</td>
                <td class="sep">:</td>
                <td>{{ $audit->category->full_name }}</td>
            </tr>
        @endif
    </table>

    <h2>Ringkasan</h2>
    <table class="items" style="width: 60%">
        <tr><td>Aset terdaftar dalam cakupan</td><td class="count">{{ $summary['expected'] }}</td></tr>
        <tr><td>Ditemukan sesuai catatan</td><td class="count">{{ $summary['found'] }}</td></tr>
        <tr><td>Salah lokasi</td><td class="count">{{ $summary['misplaced'] }}</td></tr>
        <tr><td>Tidak ditemukan</td><td class="count">{{ $summary['missing'] }}</td></tr>
        <tr><td>Kondisi berubah</td><td class="count">{{ $summary['condition_changed'] }}</td></tr>
        <tr><td>Ditemukan di luar daftar</td><td class="count">{{ $summary['unlisted'] }}</td></tr>
    </table>

    <h2>Temuan</h2>
    @if ($findings->isEmpty())
        <p class="note">Seluruh aset ditemukan sesuai catatan, tanpa perubahan kondisi.</p>
    @else
        <table class="items">
            <thead>
                <tr>
                    <th class="num">No</th>
                    <th>Kode Aset</th>
                    <th>Nama Aset</th>
                    <th>Hasil</th>
                    <th>Lokasi Tercatat</th>
                    <th>Ditemukan di</th>
                    <th>Kondisi Tercatat</th>
                    <th>Kondisi Ditemukan</th>
                    <th>Tindak Lanjut</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($findings as $index => $line)
                    <tr>
                        <td class="num">{{ $index + 1 }}</td>
                        <td>{{ $line->asset->code }}</td>
                        <td>{{ $line->asset->name }}</td>
                        <td>{{ $line->result->printedLabel() }}{{ $line->is_expected ? '' : ' (di luar daftar)' }}</td>
                        <td>{{ $line->expectedLocation?->name ?? '-' }}</td>
                        <td>{{ $line->scannedLocation?->name ?? '-' }}</td>
                        <td>{{ $line->expected_condition?->printedLabel() ?? '-' }}</td>
                        <td>{{ $line->scanned_at ? $line->observed_condition?->printedLabel() : '-' }}</td>
                        <td>{{ $tindakLanjut($line) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($audit->notes)
        <p class="note"><strong>Catatan:</strong> {{ $audit->notes }}</p>
    @endif

    <table class="sign">
        <tr>
            <td>
                {{ $city }}{{ $city ? ', ' : '' }}{{ $tanggal($audit->closed_at ?? $audit->counted_at, 'd F Y') }}<br>
                Petugas Audit
                <div class="sign-box"></div>
                <div class="sign-name">{{ $audit->countedBy?->name ?? $audit->createdBy?->name ?? '(...........................)' }}</div>
            </td>
            <td>
                <br>
                Diperiksa dan Disetujui
                <div class="sign-box"></div>
                <div class="sign-name">{{ $audit->closedBy?->name ?? '(...........................)' }}</div>
            </td>
        </tr>
    </table>
</body>
</html>
