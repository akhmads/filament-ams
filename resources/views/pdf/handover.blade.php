{{-- Berita Acara Serah Terima Aset --}}
@php
    // Dokumen ini tetap berbahasa Indonesia walau locale aplikasi berbahasa Inggris,
    // jadi nama hari dan bulan dikunci di sini. copy() dipakai agar instance tanggal
    // milik model tidak ikut berubah locale-nya.
    $tanggal = fn (?\Carbon\CarbonInterface $date, string $format): string => $date
        ? $date->copy()->locale('id')->translatedFormat($format)
        : '-';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #111; }
        .header { border-bottom: 1.5pt solid #111; padding-bottom: 6pt; margin-bottom: 14pt; }
        .company { font-size: 13pt; font-weight: bold; }
        .company-address { font-size: 8.5pt; color: #555; }
        h1 { font-size: 12pt; text-align: center; margin: 0 0 2pt; text-transform: uppercase; letter-spacing: 0.5pt; }
        .doc-number { text-align: center; font-size: 9pt; color: #555; margin-bottom: 14pt; }
        table.meta { width: 100%; margin-bottom: 12pt; }
        table.meta td { padding: 2pt 0; vertical-align: top; }
        table.meta td.key { width: 32%; color: #555; }
        table.meta td.sep { width: 3%; }
        table.items { width: 100%; border-collapse: collapse; margin-bottom: 14pt; }
        table.items th, table.items td { border: 0.5pt solid #999; padding: 4pt 5pt; font-size: 9pt; }
        table.items th { background: #f0f0f0; text-align: left; }
        table.items td.num { text-align: center; width: 6%; }
        .note { font-size: 9pt; margin-bottom: 18pt; line-height: 1.5; }
        table.sign { width: 100%; margin-top: 6pt; }
        table.sign td { width: 50%; text-align: center; vertical-align: top; font-size: 9pt; }
        .sign-box { height: 70pt; }
        .sign-box img { max-height: 65pt; }
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

    @if ($assignment->status->value === 'draft')
        <div class="draft">— DRAF, BELUM BERLAKU —</div>
    @endif

    <h1>Berita Acara {{ $assignment->type->printedLabel() }} Aset</h1>
    <div class="doc-number">Nomor: {{ $assignment->number }}</div>

    <p class="note">
        Pada hari ini, {{ $tanggal($assignment->assignment_date, 'l, d F Y') }}, telah dilakukan
        {{ strtolower($assignment->type->printedLabel()) }} aset milik {{ $companyName }} dengan rincian sebagai berikut.
    </p>

    <table class="meta">
        <tr>
            <td class="key">Diserahkan oleh</td>
            <td class="sep">:</td>
            <td>{{ $assignment->handedOverBy?->name ?? '-' }}
                @if ($assignment->handedOverBy?->position) ({{ $assignment->handedOverBy->position }}) @endif
            </td>
        </tr>
        <tr>
            <td class="key">Diterima oleh</td>
            <td class="sep">:</td>
            <td>{{ $assignment->recipient_name }}</td>
        </tr>
        @if ($assignment->toEmployee?->employee_number)
            <tr>
                <td class="key">NIK</td>
                <td class="sep">:</td>
                <td>{{ $assignment->toEmployee->employee_number }}</td>
            </tr>
        @endif
        <tr>
            <td class="key">Departemen</td>
            <td class="sep">:</td>
            <td>{{ $assignment->toDepartment?->name ?? $assignment->toEmployee?->department?->name ?? '-' }}</td>
        </tr>
        <tr>
            <td class="key">Lokasi penempatan</td>
            <td class="sep">:</td>
            <td>{{ $assignment->toLocation?->full_name ?? $assignment->to_placement_type->printedLabel() }}</td>
        </tr>
        <tr>
            <td class="key">Cabang</td>
            <td class="sep">:</td>
            <td>{{ $assignment->branch?->name }}</td>
        </tr>
        @if ($assignment->expected_return_date)
            <tr>
                <td class="key">Rencana pengembalian</td>
                <td class="sep">:</td>
                <td>{{ $tanggal($assignment->expected_return_date, 'd F Y') }}</td>
            </tr>
        @endif
        @if ($assignment->purpose)
            <tr>
                <td class="key">Keperluan</td>
                <td class="sep">:</td>
                <td>{{ $assignment->purpose }}</td>
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
                <th>Nomor Seri</th>
                <th>Kondisi</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($assignment->items as $index => $item)
                <tr>
                    <td class="num">{{ $index + 1 }}</td>
                    <td>{{ $item->asset->code }}</td>
                    <td>{{ $item->asset->name }}</td>
                    <td>{{ $item->asset->category?->name }}</td>
                    <td>{{ $item->asset->serial_number ?: '-' }}</td>
                    <td>{{ $item->condition->printedLabel() }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <p class="note">
        Penerima bertanggung jawab atas pemeliharaan dan keamanan aset tersebut selama berada dalam
        penguasaannya, serta wajib mengembalikannya dalam kondisi baik sesuai ketentuan perusahaan.
        @if ($assignment->notes)
            <br><strong>Catatan:</strong> {{ $assignment->notes }}
        @endif
    </p>

    <table class="sign">
        <tr>
            <td>
                {{ $city }}, {{ $tanggal($assignment->assignment_date, 'd F Y') }}<br>
                Yang Menyerahkan
                <div class="sign-box">
                    @if ($assignment->handover_signature)
                        <img src="{{ $assignment->handover_signature }}">
                    @endif
                </div>
                <div class="sign-name">{{ $assignment->handedOverBy?->name ?? '(...........................)' }}</div>
            </td>
            <td>
                <br>
                Yang Menerima
                <div class="sign-box">
                    @if ($assignment->receiver_signature)
                        <img src="{{ $assignment->receiver_signature }}">
                    @endif
                </div>
                <div class="sign-name">{{ $assignment->recipient_name }}</div>
            </td>
        </tr>
    </table>
</body>
</html>
