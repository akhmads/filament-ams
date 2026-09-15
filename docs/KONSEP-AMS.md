# Konsep Aplikasi — Asset Management System (AMS)

Stack: **Laravel 13.31** · **Filament 5.8** · **MySQL 8** · PHP 8.4

---

## 1. Prinsip Desain

| Prinsip | Konsekuensi teknis |
|---|---|
| Setiap aset punya identitas fisik unik | Kode aset auto-generate + QR/barcode tercetak di label |
| Semua perpindahan tercatat, tidak ada yang "hilang diam-diam" | Tabel `asset_movements` sebagai ledger append-only |
| Angka keuangan harus auditable | Penyusutan **disimpan per periode** (bukan dihitung on-the-fly), bisa di-lock per bulan |
| Aset tidak pernah dihapus | Soft delete + status `disposed`/`lost`, activity log penuh |
| Dua jenis barang berbeda perlakuan | **Aset** (serialized, disusutkan) vs **Stok** (consumable/sparepart, dihitung qty) |
| Data lapangan masuk lewat HP | Halaman scan QR mobile-friendly, foto kondisi, tanda tangan digital |

---

## 2. Peran Pengguna (Role)

| Role | Kewenangan inti |
|---|---|
| **Super Admin** | Semua akses + pengaturan sistem, user, role |
| **Asset Manager** | CRUD aset, penyusutan, disposal, approve mutasi & penghapusan |
| **Staff Aset / Admin Gudang** | Input aset, serah terima, cetak label, stok masuk/keluar, opname |
| **Teknisi / Maintenance** | Work order, eksekusi PM, tiket repair, pakai sparepart |
| **Kepala Departemen** | Approver permintaan aset/barang & mutasi di unitnya, lihat aset unitnya |
| **Auditor** | Read-only seluruh data + modul audit/opname aset |
| **Karyawan** | Portal self-service: lihat aset yang dipegang, lapor kerusakan, ajukan permintaan |

Implementasi: `spatie/laravel-permission` + `filament-shield` (permission per-resource auto-generate).

---

## 3. Struktur Menu (Panel Admin)

```
📊 Dashboard

📦 ASET
   ├─ Daftar Aset
   ├─ Komponen / Sub-Aset
   ├─ Serah Terima (Assignment)
   ├─ Mutasi & Transfer
   ├─ Cetak Label
   ├─ Audit / Opname Aset
   └─ Penghapusan (Disposal)

💰 PENYUSUTAN
   ├─ Kebijakan Penyusutan
   ├─ Jadwal Penyusutan
   ├─ Proses Penyusutan Bulanan (posting)
   └─ Nilai Buku / Fixed Asset Register

🔧 PEMELIHARAAN
   ├─ Rencana PM (Preventive)
   ├─ Work Order
   ├─ Tiket Kerusakan (Repair)
   ├─ Checklist Pemeliharaan
   ├─ Kalender Maintenance
   └─ Vendor Servis

📥 INVENTORI / STOK
   ├─ Item Stok
   ├─ Gudang
   ├─ Penerimaan Barang
   ├─ Pengeluaran / Pemakaian
   ├─ Transfer Antar Gudang
   ├─ Stock Opname
   ├─ Kartu Stok
   └─ Permintaan Barang

🗂️ MASTER DATA
   ├─ Kategori Aset
   ├─ Merek & Model
   ├─ Lokasi (Site › Gedung › Lantai › Ruangan)
   ├─ Departemen
   ├─ Karyawan
   ├─ Supplier / Vendor
   ├─ Satuan (UoM)
   └─ Status & Kondisi

📈 LAPORAN

⚙️ PENGATURAN
   ├─ Pengguna
   ├─ Role & Hak Akses
   ├─ Format Penomoran
   ├─ Template Label
   ├─ Pengaturan Umum (logo, perusahaan, tahun fiskal)
   └─ Log Aktivitas
```

**Panel kedua — Portal Karyawan** (`/portal`): Aset Saya · Lapor Kerusakan · Permintaan Barang · Konfirmasi Serah Terima.

---

## 4. Modul & Fitur Detail

### 4.1 Pencatatan Aset

**Field inti:**
- Identitas: kode aset (auto), nama, kategori, merek, model, **serial number**, tahun produksi
- Perolehan: tanggal, harga, supplier, no. PO/invoice, sumber dana, mata uang
- Penyusutan: metode, masa manfaat (bulan), nilai residu, tanggal mulai susut
- Garansi: mulai, berakhir, no. kontrak, vendor
- Posisi: lokasi saat ini, pemegang saat ini, departemen, cost center
- Kondisi & status
- Lampiran: foto aset (multi), invoice, kartu garansi, manual book (media library)
- Spesifikasi dinamis per kategori (custom field JSON — mis. laptop punya RAM/CPU/SSD, kendaraan punya no. polisi/rangka/mesin)

**Kategori bertingkat** (`parent_id`) — IT › Komputer › Laptop. Kategori menentukan default: masa manfaat, metode susut, checklist maintenance, prefix kode, dan set custom field.

**Parent-child asset:** 1 PC bisa punya child (monitor, keyboard, UPS). Nilai & penyusutan bisa digabung atau terpisah.

### 4.2 Cetak Label Barcode / QR

- **Isi QR:** URL publik `https://app/a/{uuid}` → halaman detail aset read-only (bisa dibuka siapa pun yang scan, tanpa login) berisi: kode, nama, pemegang, lokasi, status, tombol "Lapor Kerusakan".
- **Barcode Code128** berisi kode aset, untuk scanner gun.
- **Template label** yang bisa dikonfigurasi: ukuran (50×25, 38×25, 100×50 mm), elemen yang ditampilkan (logo, nama perusahaan, kode, nama aset, QR, barcode).
- **Cetak massal**: pilih banyak aset dari tabel → bulk action → PDF siap cetak (thermal label printer atau sheet A4 3×8).
- **Reprint log**: siapa mencetak ulang label apa (kontrol anti-duplikasi).
- Paket: `picqer/php-barcode-generator` (Code128) + `endroid/qr-code`, render ke PDF via `barryvdh/laravel-dompdf` atau `spatie/laravel-pdf`.

### 4.3 Penyusutan (Depresiasi)

- Metode: **Garis Lurus (Straight Line)**, **Saldo Menurun Ganda (Double Declining)**, **Jumlah Angka Tahun (SYD)**, dan **Tanpa Penyusutan** (tanah, aset sewa).
- Perhitungan **per bulan**, disimpan di `depreciation_entries` (asset_id, periode, beban, akumulasi, nilai buku).
- Job terjadwal akhir bulan menghasilkan draft → Asset Manager **posting** → periode terkunci (tidak bisa diubah tanpa reversal).
- Output: **jurnal penyusutan** (Beban Penyusutan vs Akumulasi Penyusutan) per kategori/cost center, siap diekspor ke sistem akuntansi.
- Menangani: penyusutan prorata bulan perolehan, aset yang dijual/dihapus di tengah periode (gain/loss on disposal), aset yang sudah nilai buku = residu (berhenti susut).

### 4.4 Posisi Aset: Karyawan vs Ruangan

Ini kuncinya — satu aset **selalu** punya tepat satu "penempatan aktif" dengan dua dimensi:

| Tipe penempatan | Contoh |
|---|---|
| `employee` | Laptop dipegang Budi (Dept. IT) |
| `location` | Proyektor terpasang di Ruang Meeting A |
| `warehouse` | Kursi cadangan di Gudang Pusat |
| `in_transit` | Sedang dikirim antar cabang |
| `vendor` | Sedang di tempat servis |

- Tabel `asset_movements`: `from_*` → `to_*`, tanggal, PIC, alasan, catatan, foto kondisi, lampiran.
- **Serah Terima (Assignment)**: checkout ke karyawan → generate **BAST PDF** dengan tanda tangan digital (canvas signature) penerima & penyerah. Checkin saat dikembalikan, catat kondisi saat kembali.
- **Peminjaman (Loan)**: assignment dengan `expected_return_date` → notifikasi H-3 dan alert overdue.
- **Mutasi antar lokasi/cabang**: butuh approval, status `in_transit` sampai dikonfirmasi diterima.
- **Riwayat lengkap** per aset (timeline) dan per karyawan (daftar aset yang dipegang + total nilai — berguna saat karyawan resign: *exit clearance checklist*).

### 4.5 Maintenance Terjadwal (Preventive)

- **Rencana PM** per aset atau per kategori: interval **kalender** (tiap N hari/minggu/bulan/tahun) atau **meter** (tiap N jam operasi / N km).
- Scheduler harian mengecek jatuh tempo → **auto-generate Work Order** dengan lead time (mis. WO dibuat H-7).
- WO berisi: checklist tugas (per kategori), teknisi/vendor yang ditugaskan, estimasi biaya & durasi, sparepart yang dibutuhkan.
- Eksekusi: teknisi centang checklist, input jam kerja, sparepart terpakai (**otomatis mengurangi stok**), biaya, foto sebelum/sesudah, hasil (OK / perlu tindak lanjut).
- **Kalender maintenance** (FullCalendar) — tampilan bulanan semua WO.
- Notifikasi: WO jatuh tempo, WO terlambat, WO selesai.

### 4.6 Repair / Perbaikan (Corrective)

- **Tiket kerusakan** dibuat karyawan (dari portal atau scan QR di aset) → foto + deskripsi.
- Alur status: `Dilaporkan → Diverifikasi → Disetujui → Dalam Perbaikan (internal/vendor) → Selesai / Tidak Bisa Diperbaiki (→ usul disposal)`.
- Fitur: klaim **garansi** (auto-cek tanggal garansi aset), estimasi & realisasi biaya, sparepart terpakai, **aset pengganti sementara (loaner)**, pencatatan **downtime**.
- Metrik: MTTR, MTBF, frekuensi kerusakan per aset — dasar keputusan "perbaiki atau ganti".

### 4.7 Ketersediaan Stok

Dua hal yang berbeda dan keduanya perlu:

**a. Ketersediaan aset** — aset berstatus `Available` di gudang, siap dialokasikan. Tampilan: "Laptop tersedia: 12 unit" dengan daftar unitnya.

**b. Stok consumable & sparepart** — bukan aset tetap (toner, kabel, oli, filter, ATK):
- Item stok dengan satuan, stok minimum, **reorder point**
- Transaksi: penerimaan, pengeluaran/pemakaian (termasuk otomatis dari WO), transfer antar gudang, penyesuaian, opname
- **Kartu stok** (ledger) + valuasi **moving average**
- Alert stok di bawah minimum + daftar usulan pembelian
- Permintaan barang oleh karyawan → approval atasan → pengeluaran barang

### 4.8 Audit / Stock Opname Aset

- Buat sesi audit per lokasi/departemen dengan periode.
- Petugas keliling **scan QR** lewat HP → aset tertandai "ditemukan" + foto kondisi.
- Hasil: **Ditemukan / Hilang / Salah Lokasi / Kondisi Berubah** → selisih otomatis.
- Berita acara hasil audit (PDF) + tindak lanjut (update lokasi, usul penghapusan aset hilang).

### 4.9 Penghapusan (Disposal)

Usulan → approval berjenjang → metode (**dijual / hibah / musnah / tukar tambah / hilang**) → nilai jual → hitung **gain/loss** = nilai jual − nilai buku → berita acara penghapusan → status aset `disposed`, penyusutan berhenti.

### 4.10 Dashboard

- KPI: total aset, nilai perolehan, akumulasi penyusutan, **nilai buku saat ini**
- Donut: aset per status · per kategori · per kondisi
- Bar: aset per lokasi / departemen
- Widget aksi: **WO jatuh tempo 30 hari**, tiket repair terbuka, **garansi berakhir 60 hari**, stok di bawah minimum, aset belum diaudit, peminjaman overdue
- Tren biaya maintenance 12 bulan
- Filter global: periode, cabang, departemen

### 4.11 Laporan (semua export Excel & PDF)

1. Daftar Aset (Asset Register) — filter kategori/lokasi/departemen/status
2. **Kartu Aset** per unit (riwayat lengkap: mutasi, maintenance, biaya, penyusutan)
3. Laporan Penyusutan bulanan/tahunan + **jurnal penyusutan**
4. Laporan Nilai Buku per tanggal
5. Laporan Mutasi Aset per periode
6. Aset per Karyawan (untuk exit clearance)
7. Laporan Maintenance & Biaya per aset/kategori/vendor
8. Laporan Downtime, MTTR, MTBF
9. Laporan Garansi (akan/sudah berakhir)
10. Laporan Stok, Kartu Stok, Stok Minimum
11. **TCO (Total Cost of Ownership)** per aset = perolehan + maintenance + repair − nilai jual
12. Laporan Hasil Audit & Selisih
13. Laporan Penghapusan + Gain/Loss

---

## 5. Model Data (ERD Ringkas)

```
companies ──< branches ──< locations (self-nested: site/gedung/lantai/ruangan)
departments ──< employees ──< users (opsional 1:1)

asset_categories (self-nested, punya default depresiasi + custom fields)
brands ──< asset_models
suppliers

assets ─┬─ category, brand, model, supplier
        ├─ current_location_id, current_employee_id, current_holder_type
        ├─ parent_id (komponen)
        ├─< asset_movements      (ledger perpindahan)
        ├─< asset_assignments    (serah terima + BAST + ttd)
        ├─< depreciation_entries (per periode, posted/draft)
        ├─< maintenance_plans ──< work_orders ──< wo_checklist_items
        │                                     └─< wo_parts (→ stock_transactions)
        ├─< repair_tickets ──< repair_costs
        ├─< audit_lines (← asset_audits)
        ├─< asset_disposals
        ├─< media (foto & dokumen)
        └─< activity_log

warehouses ──< stock_items ──< stock_transactions (in/out/transfer/adjust/opname)
             └─< stock_balances (cache saldo per gudang)
item_requests ──< item_request_lines

settings, number_sequences, label_templates, approval_flows, notifications
```

**Catatan desain penting:**
- `assets.current_*` adalah **cache** dari movement terakhir — sumber kebenaran tetap `asset_movements`.
- `depreciation_entries` punya kolom `is_posted` + `locked_at`; periode terkunci tidak bisa diubah.
- `stock_balances` di-update transaksional (dengan lock) agar tidak race condition.
- Semua tabel transaksi punya `created_by`, `approved_by`, timestamps, soft delete.

---

## 6. Aturan Bisnis Penting (sering terlewat)

1. **Kode aset unik & tidak berubah selamanya**, format configurable: `{PREFIX_KATEGORI}-{KODE_LOKASI}-{YY}{MM}-{SEQ:4}` → `IT-LAP-HO-2609-0123`. Sequence per prefix, transaksional (tidak boleh duplikat saat input bersamaan).
2. **State machine status aset** — transisi tidak sembarangan: aset `In Repair` tidak bisa langsung di-assign ke karyawan; aset `Disposed` tidak bisa diapa-apakan lagi.
3. Aset yang **sedang dipegang karyawan tidak bisa di-assign** ke orang lain tanpa checkin dulu.
4. **Karyawan resign** → sistem menolak/mengingatkan jika masih ada aset di tangannya (exit clearance).
5. **Penyusutan tidak boleh double-post** untuk periode yang sama (unique constraint asset_id + period).
6. **Sparepart dari WO otomatis mengurangi stok** dan biayanya masuk ke TCO aset.
7. **Tanggal garansi** dicek otomatis saat buat tiket repair → tandai "masih garansi, jangan bayar".
8. **Foto kondisi wajib** saat serah terima & pengembalian (bukti jika ada kerusakan).
9. **Approval berjenjang** untuk: mutasi antar cabang, penghapusan aset, permintaan barang di atas nilai tertentu.
10. **Activity log** untuk semua perubahan data aset — siapa mengubah apa, kapan, nilai lama & baru.
11. **Tahun fiskal** bisa berbeda dari tahun kalender (mis. April–Maret).
12. **Import massal Excel** dengan template & validasi baris-per-baris — wajib untuk migrasi data awal ribuan aset.

---

## 7. Stack & Paket

| Kebutuhan | Paket |
|---|---|
| Framework & Panel | `laravel/framework ^13.31`, `filament/filament ^5.8` |
| Role & Permission | `spatie/laravel-permission ^8`, `bezhansalleh/filament-shield ^4` |
| Audit trail | `spatie/laravel-activitylog ^5` |
| Foto & dokumen | `spatie/laravel-medialibrary ^11` + plugin Filament |
| QR Code | `endroid/qr-code` |
| Barcode Code128 | `picqer/php-barcode-generator` |
| PDF (label, BAST, laporan) | `barryvdh/laravel-dompdf` (ringan) atau `spatie/laravel-pdf` (Browsershot, hasil presisi) |
| Excel import/export | `pxlrbt/filament-excel` / `maatwebsite/excel` |
| Kalender maintenance | `saade/filament-fullcalendar` |
| Kategori/lokasi bertingkat | `kalnoy/nestedset` |
| Tanda tangan digital | canvas JS → base64 PNG (custom Filament field) |
| Penjadwalan | Laravel Scheduler + Queue (`database` atau Redis) |
| Uang | `brick/money` atau kolom `decimal(18,2)` + casting konsisten |

**Infrastruktur:** MySQL 8 (belum terpasang di mesin ini — bisa via Docker), Redis untuk queue/cache (opsional), supervisor untuk queue worker, cron untuk scheduler.

---

## 8. Roadmap Implementasi

### Fase 1 — Fondasi & Aset Inti *(MVP)*
Setup Laravel + Filament + MySQL · Auth, User, Role · Master data (kategori, lokasi, departemen, karyawan, merek, supplier) · CRUD Aset + foto/dokumen · Penomoran otomatis · **QR/Barcode + cetak label** · Serah terima & mutasi + BAST PDF · Dashboard dasar · Import Excel

### Fase 2 — Penyusutan & Maintenance
Kebijakan & perhitungan penyusutan · Posting bulanan + jurnal · Rencana PM + auto work order · Eksekusi WO + checklist · Tiket repair + alur perbaikan · Kalender maintenance · Notifikasi

### Fase 3 — Stok & Audit
Gudang & item stok · Transaksi stok + kartu stok · Integrasi sparepart WO ↔ stok · Stok minimum & alert · Permintaan barang + approval · Audit/opname aset via scan QR · Penghapusan aset

### Fase 4 — Penyempurnaan
Portal karyawan self-service · Halaman scan mobile · Semua laporan + export · Widget dashboard lanjutan (TCO, MTTR) · Multi-cabang/multi-company · Notifikasi email/WhatsApp · Backup terjadwal

---

## 9. Keputusan yang Sudah Dikunci

| Topik | Keputusan |
|---|---|
| Struktur entitas | **Multi-cabang, satu perusahaan** — ada tabel `branches`, kolom `branch_id` pada aset & transaksi, filter per cabang, kode cabang masuk ke kode aset |
| Cakupan pengerjaan | **Fase 1 dulu** — aset, kategori, master data, label QR/barcode, serah terima, mutasi, dashboard dasar, import Excel |
| Portal karyawan | **Tidak ada** — karyawan hanya master data tanpa akun login; semua input lewat staff aset. QR pada label mengarah ke `/a/{kode}` yang me-redirect ke halaman detail aset di panel admin (butuh login), dan label juga memuat Code128 agar bisa dibaca scanner gun |
| Database | MySQL 8.0.46 sudah tersedia — db `ams`, user `app` |

### Masih terbuka untuk fase berikutnya
- Integrasi ekspor jurnal ke sistem akuntansi (format?)
- Modul pengadaan (PR → PO → penerimaan)
- Estimasi volume aset/user/lokasi
- Bahasa antarmuka dan komentar kode: **Bahasa Inggris** (`APP_LOCALE=en`). Dokumen cetak — BAST dan label aset — tetap **Bahasa Indonesia** karena ditandatangani dan diarsipkan secara fisik; istilahnya diambil dari `printedLabel()` pada enum, terpisah dari `getLabel()` yang dipakai antarmuka. Tanggal pada BAST dikunci ke locale `id` di dalam blade. Dokumentasi di `docs/` tetap Bahasa Indonesia.

---

## 10. Status Implementasi — Fase 1 (selesai)

Dibangun di atas **Laravel 13.31**, **Filament 5.8**, PHP 8.4, MySQL 8.0.46 (database `ams`).

### Yang sudah jalan

| Bagian | Isi |
|---|---|
| **Skema** | 16 tabel + tabel permission, activity log, media, notifikasi. Seluruh relasi berkunci asing, soft delete pada data induk |
| **Aset** | CRUD lengkap 6 tab (umum, perolehan, penyusutan & garansi, posisi, spesifikasi dinamis per kategori, lampiran), kode otomatis, tab status, filter lengkap, pencarian global |
| **Kode aset** | Format konfigurabel `{CATEGORY}-{BRANCH}-{YY}{MM}-{SEQ}`, urutan terkunci transaksi, per pola — bukan global |
| **Label** | QR (URL `/a/{kode}`) + Code 128, 3 template bawaan, cetak satuan & massal, tata letak absolut sehingga satu label = satu halaman pada printer gulungan dan grid rapi di A4 |
| **Serah terima** | Dokumen BAST multi-aset, tanda tangan digital di kanvas, penyelesaian dokumen memindahkan seluruh aset sekaligus, cetak PDF berbahasa Indonesia |
| **Ledger posisi** | `asset_movements` append-only, kolom posisi pada `assets` hanya cache; setiap perpindahan mencatat asal, tujuan, status, kondisi, pelaku, dan dokumen rujukan |
| **Aturan bisnis** | Aset yang masih dipegang tidak bisa diserahkan ke orang lain; aset terhapus/hilang tidak bisa dipindahkan; lokasi non-ruangan ditolak; satu dokumen tidak bisa diselesaikan dua kali; kegagalan satu aset membatalkan seluruh dokumen |
| **Master data** | Kategori & lokasi bertingkat (nested set), cabang, departemen, karyawan, merek, model, supplier |
| **Dashboard** | 4 KPI, donat status, batang per kategori, tabel garansi akan berakhir |
| **Hak akses** | 161 permission + 13 policy via Shield; 4 peran: super_admin, asset_manager (137), asset_staff (35), auditor (27) |
| **Pengaturan** | Identitas perusahaan, logo, format penomoran, tahun fiskal, tahun fiskal. **Appearance** punya halaman sendiri (`ManageAppearance`) — logo terang/gelap, tinggi logo dalam rem, warna tema (primary, gray, danger, info, success, warning — palet bawaan Filament, pola `App\Support\Theme` dari lastmile), lebar area konten panel, serta SPA mode dengan opsi prefetch saat hover, memakai enum `Width` bawaan Filament |
| **Tes** | 59 tes, 101 asersi — generator kode, ledger perpindahan, penyelesaian BAST, PDF label & BAST, render seluruh halaman panel |

### Menjalankan

```bash
php artisan serve          # atau arahkan vhost ke public/
npm run dev                # saat mengubah aset frontend
```

Masuk di `/admin` dengan `admin@admin.com` / `password` — **ganti kata sandi ini sebelum dipakai sungguhan.**

### Catatan teknis untuk fase berikutnya

- Tata letak label memakai posisi absolut, bukan tabel. Dompdf mengabaikan `box-sizing` dan menaksir tinggi sel tabel terlalu besar, sehingga label meluber ke halaman berikutnya. Ukuran font juga disesuaikan otomatis terhadap lebar dan tinggi kotak, memakai metrik lebar karakter DejaVu Sans (tebal 0,72 em; biasa 0,60 em).
- Pengaturan tampilan ada di `App\Support\Appearance`. Panel membacanya lewat closure (`->maxContentWidth(fn () => Appearance::maxContentWidth())`) sehingga perubahan berlaku pada muat ulang berikutnya tanpa deploy. Pilihannya memakai enum `Filament\Support\Enums\Width` — case yang terlalu sempit (3xs–sm) dan yang bukan lebar kontainer (min/max/fit/prose/screen-*) tidak ditawarkan. Nilai yang tidak dikenali atau tidak ditawarkan jatuh ke bawaan Filament, `7xl`. Baris pengaturannya memakai `group` = `appearance`.
- `full_name` pada kategori dan lokasi dibangun dari peta seluruh tabel yang di-memo per request (`App\Models\Concerns\HasTreePath`), bukan dari relasi `ancestors`. Membaca relasi itu akan melanggar `preventLazyLoading` di setiap pemanggil yang lupa melakukan eager load, dan memberi label 50 opsi select akan memakan 50 query. Dengan peta, satu query menutupi seluruh request. Relasi `ancestors` yang sudah dimuat tetap dipakai bila ada. Memo bersifat statis, jadi dibuang di `saved`/`deleted` dan di `setUp` tes.
- `Model::shouldBeStrict()` aktif di semua environment kecuali production, termasuk `testing`. Sebelumnya hanya di `local`, sehingga pelanggaran lazy loading hanya muncul di browser dan tidak pernah tertangkap tes.
- Pengaturan disimpan di tabel `settings` dan dibaca lewat metode statis `Setting::get()/set()/setMany()`. Hasil baca hanya di-memo **selama satu request**, bukan di-cache lintas request — menyimpan halaman pengaturan langsung berlaku dan tidak ada cache yang bisa tertinggal basi bila suatu baris diubah lewat migrasi data atau SQL manual. Perubahan pengaturan ikut tercatat di jejak audit (`log_name` = `setting`). Memo bersifat statis, jadi `tests/TestCase.php` memanggil `Setting::flush()` di tiap `setUp`.
- Pada activitylog v5 diff perubahan tersimpan di kolom `attribute_changes`, bukan `properties` seperti v4. `properties` kini hanya untuk properti tambahan buatan sendiri.
- `config/dompdf.php` dipublikasikan dan `temp_dir` diarahkan ke `storage/app/private/dompdf`. Bawaannya `sys_get_temp_dir()`, yang pada server bersama dapat dibaca proses lain dan dibersihkan di tengah permintaan. Cache font juga di `storage/fonts`.
- `RoleSeeder` memanggil `shield:generate` sehingga peran tetap benar ketika resource baru ditambahkan; daftar permission di-cache per proses agar tes tetap cepat.
- Kolom penyusutan pada `assets` sudah terisi dari kategori, tinggal ditambahkan tabel `depreciation_entries` dan proses posting bulanan di Fase 2.
- Belum ada repositori git — jalankan `git init` bila ingin mulai melacak perubahan.

---

## 11. Fase 2 — Penyusutan (selesai)

### Keputusan
| Topik | Keputusan |
|---|---|
| Buku | **Komersial + Fiskal (pajak)** — dua jadwal paralel per aset |
| Awal susut | **Bulan perolehan dihitung penuh** |
| Pemicu maintenance | Kalender (dikerjakan setelah penyusutan) |
| Jurnal | Laporan + ekspor Excel (menunggu persetujuan dependensi) |

### Aturan fiskal yang diverifikasi dari sumber resmi
- **Tarif & masa manfaat** (UU 36/2008 Pasal 11 ayat 6): Kelompok 1 4 th 25%/50% · Kelompok 2 8 th 12,5%/25% · Kelompok 3 16 th 6,25%/12,5% · Kelompok 4 20 th 5%/10% · Bangunan permanen 20 th 5% · tidak permanen 10 th 10% (garis lurus / saldo menurun; bangunan hanya garis lurus).
- **Saldo menurun** (ayat 2): tarif atas nilai sisa buku; *"pada akhir masa manfaat nilai sisa buku disusutkan sekaligus"*.
- **Awal penyusutan** (ayat 3): bulan dilakukannya pengeluaran. Contoh PMK 72/2023: perahu Kelompok 2 dibeli Oktober 2023 → 3/12 di 2023, habis 9/12 di 2031.
- **Harta tidak tercantum di lampiran PMK 72/2023**: memakai masa manfaat Kelompok 3.
- **Penarikan/penjualan** (ayat 8): sisa nilai buku dibebankan sebagai kerugian — ditangani modul disposal (Fase 3).

Sumber: [UU 36/2008 — pajak.go.id](https://www.pajak.go.id/sites/default/files/2019-07/UU%2036%202008.pdf), [Sosialisasi PMK 72/2023 — IAI](https://web.iaiglobal.or.id/assets/files/file_publikasi/Sosialisasi%20PMK%2072%20Tahun%202023%20Penyusutan%20Amortisasi.pdf), [Ringkasan PMK 72/2023 — JDIH Kemenkeu](https://jdih.kemenkeu.go.id/dok/pmk-72-tahun-2023/summary), [Pokok aturan PMK-72/2023 — DJP](https://www.pajak.go.id/en/node/98645).

### Yang sudah jalan
- `DepreciationCalculator` murni tanpa database: garis lurus, saldo menurun (tahun mengikuti `fiscal_year_start_month`), jumlah angka tahun (komersial saja). Uang dihitung dalam **sen bilangan bulat** dengan pembulatan kumulatif — CLI PHP di mesin ini tidak punya bcmath, dan float tidak dipakai.
- `DepreciationRunner`: draft per buku per bulan di `depreciation_periods` / `depreciation_entries`; **posting berurutan tanpa celah**, selalu menghitung ulang sebelum mengunci; bulan yang sudah diposting dan bulan sebelumnya tidak bisa dihitung ulang.
- Buku fiskal: kelompok dari aset atau, bila kosong, dari kategori; tanpa kelompok → tidak masuk buku fiskal; tanpa residu; mulai bulan perolehan walau buku komersial memakai tanggal mulai susut; bangunan dipaksa garis lurus.
- Aset berstatus Disposed/Lost tidak disusutkan (tanggal & laba/rugi pelepasan milik modul disposal).
- Kategori: kelompok & metode fiskal, akun beban dan akumulasi untuk jurnal. Aset: kelompok & metode fiskal sendiri (opsional).
- **Harga & tanggal perolehan serta pengaturan penyusutan aset terkunci** setelah ada bulan yang diposting, agar buku besar tetap konsisten.
- Resource **Depreciation Periods** (grup navigasi Depreciation): Calculate Month, Recalculate, Post; rincian per aset. Hak akses lewat policy Shield — calculate = `create`, recalculate/post = `update`.
- Command `depreciation:calculate [--book=] [--month=YYYY-MM]` + jadwal hari terakhir tiap bulan pukul 22:00 (hanya draft, tidak pernah posting). **Butuh cron `schedule:run` di server.**
- Laporan **Book Value**: harga perolehan, akumulasi, dan nilai buku per aset per akhir bulan untuk buku yang dipilih. Hanya penyusutan yang **sudah diposting** yang dihitung (dibatasi bulan posting terakhir), jadi draft tidak mengubah angka. Filter kategori & cabang, total di bawah tabel.
- Laporan **Depreciation Journal**: satu baris per kategori — debit akun beban, kredit akun akumulasi, jumlah aset, nominal, total. Draft ikut tampil (dengan keterangan "draft") agar jurnal bisa dicek sebelum posting; aset yang sudah dihapus tetap masuk jurnal bulannya.
- Kedua laporan memakai hak akses `viewAny` Depreciation Period.

### Catatan teknis
- `phpunit.xml` memakai `VIEW_COMPILED_PATH=storage/framework/testing/views`: view terkompilasi bersama dimiliki `www-data`, dan Blade tidak bisa `touch()` berkas milik user lain.

- **Ekspor CSV & XLSX** pada kedua laporan (tombol Export). Ekspor mengikuti filter, pencarian, dan urutan yang sedang tampil, tanpa paging. XLSX menyimpan tanggal sebagai sel tanggal dan nominal sebagai angka berformat ribuan; CSV memakai angka polos, tanggal ISO, dan BOM agar Excel membacanya sebagai UTF-8.
- Ekspor jurnal berbentuk **baris jurnal siap impor**: dua baris per kategori (debit akun beban, kredit akun akumulasi) bertanggal akhir bulan. Nama file jurnal yang belum diposting diberi akhiran `-draft`; bulan yang belum dihitung tidak menampilkan tombol ekspor.
- Dependensi `openspout/openspout ^4.32` kini dideklarasikan langsung (sebelumnya hanya transitif lewat Filament). File sementara ditulis ke `storage/app/private/exports`, bukan folder temp sistem, dan dihapus setelah diunduh.

---

## 12. Fase 2 — Maintenance Preventif (selesai)

### Keputusan
- Interval **kalender saja** (hari/minggu/bulan/tahun); interval meter (jam operasi/km) belum.
- Jatuh tempo **mengikuti kalender, bukan tanggal selesai**: jatuh tempo berikutnya = jatuh tempo sebelumnya + interval, jadi servis yang telat tidak menggeser seluruh jadwal. Bulan/tahun tidak "meluber" (tanggal 31 jatuh ke akhir bulan yang lebih pendek).
- Satu aset punya **paling banyak satu work order terbuka per rencana**. Jadwal yang terlewat digabung menjadi satu work order untuk jatuh tempo terakhir, tidak menumpuk.
- Jatuh tempo pertama = tanggal mulai rencana; aset yang diperoleh setelahnya jatuh tempo satu interval setelah tanggal perolehan.
- Work order PM **tidak mengubah status aset** (servis rutin tidak membuat aset berhenti dipakai). Perpindahan ke vendor/perbaikan menjadi urusan modul repair.

### Yang sudah jalan
- **Maintenance Plans** (grup navigasi Maintenance): untuk satu aset atau satu kategori (opsional termasuk subkategori; aset Disposed/Lost/Retired dilewati), interval, tanggal mulai, lead time (hari sebelum jatuh tempo work order dibuka), teknisi, vendor servis, estimasi biaya & durasi, checklist, instruksi, aktif/nonaktif. Tombol **Open Due Work Orders** menjalankan generator saat itu juga.
- **Work Orders**: nomor `WO/YYMM/SEQ`, dibuat otomatis dari rencana (teknisi, estimasi, checklist, dan instruksi **disalin** sehingga perubahan rencana tidak menulis ulang pekerjaan lama) atau manual. Alur `Open → In Progress → Completed / Cancelled`. Checklist hanya bisa dicentang saat In Progress (tercatat siapa & kapan). Hasil **OK** mewajibkan semua checklist tercentang; pekerjaan yang tidak tuntas diselesaikan sebagai **Needs Follow-up** dengan temuan wajib. Biaya aktual, waktu kerja, temuan, alasan pembatalan tersimpan. Detail hanya bisa diedit selama Open. Tab To Do / Overdue / Completed, badge overdue di navigasi.
- Halaman aset punya tab **Maintenance** berisi riwayat work order.
- Command `maintenance:generate-work-orders [--date=YYYY-MM-DD]`, dijadwalkan setiap hari pukul 06:00; aman dijalankan berulang (unik per rencana + aset + tanggal jatuh tempo, termasuk work order yang dihapus). **Butuh cron `schedule:run` di server.**
- Hak akses lewat policy Shield; start/centang/selesai/batal = `update` WorkOrder. `RoleSeeder`: asset_staff kini boleh membuat & mengerjakan work order (bukan rencana).

---

## 13. Fase 2 — Tiket Repair, Kalender & Notifikasi (selesai)

### Keputusan
| Topik | Keputusan |
|---|---|
| Dampak ke aset | **Lewat ledger** — mulai perbaikan mencatat movement `repair` ke status Under Repair; perbaikan vendor memindahkan aset ke penempatan Vendor. Selesai = kembali ke penempatan asal dengan status sebelumnya; tidak bisa diperbaiki = Retired |
| Kalender | Grid bulanan buatan sendiri, tanpa dependensi baru (plugin FullCalendar untuk Filament 5 masih beta) |
| Notifikasi | Hanya di aplikasi (lonceng Filament), dikirim sinkron sehingga tidak butuh queue worker |

### Yang sudah jalan
- **Repair Tickets** (grup Maintenance): nomor `RPR/YYMM/SEQ`, alur `Reported → Verified → Approved → In Repair → Repaired / Cannot Be Repaired`; **Rejected** bisa dipilih sampai perbaikan dimulai. Laporan berisi aset, masalah, prioritas, karyawan pelapor, waktu, deskripsi, dan foto. Laporan hanya bisa diedit sebelum disetujui.
- Status garansi **dibekukan saat dilaporkan** (`is_under_warranty`) dan tampil sebagai peringatan "klaim ke vendor"; form verifikasi langsung menyarankan perbaikan vendor bila masih bergaransi.
- Verifikasi menentukan in-house atau vendor, teknisi, vendor (wajib untuk perbaikan vendor), dan estimasi biaya. **Approval memakai permission khusus `Approve:RepairTicket`** (custom permission Shield — `config/filament-shield.php` kini dipublikasikan): asset_staff memverifikasi dan mengerjakan, asset_manager menyetujui biaya.
- Mulai perbaikan: satu aset hanya boleh punya satu perbaikan berjalan; aset yang sedang dalam perjalanan, hilang, atau dihapus ditolak oleh aturan transisi ledger, dan tiket tetap Approved. Movement menyimpan referensi ke tiket; `repair_movement_id` menunjuk movement keluar sehingga penempatan asal bisa dipulihkan.
- Selesai: kondisi aset, biaya aktual, resolusi (wajib bila tidak bisa diperbaiki). **Downtime** dihitung dari mulai sampai selesai.
- Halaman aset: tombol **Report Damage** (form terbuka dengan aset terisi) dan tab **Repairs**.
- **Maintenance Calendar**: grid bulanan (minggu dimulai Senin) berisi work order pada tanggal jatuh tempo (merah bila terlambat), tiket repair pada tanggal laporan, dan **jadwal PM yang belum menjadi work order** — diproyeksikan dengan aturan `MaintenanceScheduler::nextDueDate`, dikelompokkan per rencana per hari, paling jauh 12 bulan ke depan, dan tidak untuk tanggal yang sudah lewat. Tiap jenis hanya tampil bagi yang berhak melihatnya.
- **Notifikasi** (lonceng panel): work order ditugaskan (dibuka rencana, dibuat manual, atau teknisinya diganti), kerusakan dilaporkan (ke pemegang `Update:RepairTicket`), perbaikan menunggu approval (ke pemegang `Approve:RepairTicket`), tiket repair ditugaskan. Tidak ada notifikasi atas tindakan sendiri; user nonaktif dilewati.
- Command `maintenance:send-reminders`, dijadwalkan pukul 07:00: paling banyak satu pengingat per user per hari berisi jumlah work order terlambat dan jatuh tempo hari ini; work order tanpa teknisi dihitung untuk para perencana (`Create:MaintenancePlan`).
- Widget dashboard **Maintenance**: work order terbuka & terlambat, jatuh tempo 30 hari, tiket repair terbuka & menunggu approval, aset Under Repair.

### Catatan teknis
- Di SQLite kolom `date` tersimpan dengan jam (`Y-m-d H:i:s`), jadi query per tanggal memakai rentang (`>=` hari ini, `<` besok), bukan kesamaan.
- Panel belum punya tema Tailwind sendiri; kalender memakai `<style>` dengan variabel warna Filament (`--primary-600`, `--gray-200`, …) sehingga ikut palet Appearance dan mode gelap.
- Setelah deploy jalankan `php artisan migrate` dan `RoleSeeder` agar permission `RepairTicket` dan `Approve:RepairTicket` terbentuk dan terbagi ke peran.

### Berikutnya
Aset pengganti sementara (loaner) · interval meter · ~~sparepart dari work order/repair ke stok (Fase 3)~~ · usul penghapusan dari tiket "Cannot Be Repaired" (Fase 3) · MTTR/MTBF (Fase 4).

---

## 14. Fase 3 — Stok, Sparepart & Permintaan Barang (selesai)

### Keputusan
| Topik | Keputusan |
|---|---|
| Urutan Fase 3 | **Stok dulu**, lalu penghapusan aset, lalu audit/opname aset |
| Gudang | Lokasi bertipe **Warehouse** yang sudah ada — tidak ada master gudang kedua. Saldo dicatat per item per gudang |
| Valuasi | **Moving average per gudang**. Saldo menyimpan *total nilai*, bukan harga satuan: barang keluar membawa bagian proporsionalnya, dan unit terakhir membawa sisa nilai sehingga tidak ada rupiah yang tertinggal di gudang kosong karena pembulatan |
| Permintaan barang | Karyawan tidak punya akun, jadi **staff mencatat atas nama karyawan**; pemegang `Approve:ItemRequest` menyetujui; barang dikeluarkan dari satu gudang |
| Stok minimum | Satu angka per item, dijumlah dari semua gudang |
| Stok negatif | Ditolak — posting yang melebihi stok gagal seluruhnya |

### Yang sudah jalan
- **Stock Items** (grup navigasi Inventory): kode, nama, jenis (Consumable / Spare Part), satuan, stok minimum, jumlah pembelian ulang, aktif. Daftar menampilkan stok & nilai total; tab **Below Minimum** dan badge navigasi. Halaman item berisi saldo **per gudang** (dengan harga rata-rata) dan **Stock Card** — setiap mutasi dengan saldo berjalan.
- **Stock Transactions** — lima jenis dokumen bernomor sendiri: Goods Receipt `RCV/`, Goods Issue `ISS/`, Transfer `TRF/`, Adjustment `ADJ/`, Stock Count `OPN/`. Disimpan sebagai **draft** (tidak mengubah stok), lalu **Post**. Posting mengunci dokumen dan saldo dengan row lock; satu baris gagal membatalkan seluruh dokumen. Dokumen terposting tidak bisa diedit atau dihapus — koreksi lewat Adjustment.
- **Adjustment & Stock Count butuh permission khusus `Post:StockAdjustment`** (menulis stok naik/turun tanpa barang berpindah tangan adalah celah menutupi kehilangan). asset_staff bisa membuat draft-nya, asset_manager yang memposting.
- **Stock Count**: selisih dihitung terhadap stok **saat diposting**, bukan saat diinput; baris menyimpan stok sistem saat itu. Hitungan yang sama dengan stok tidak mencatat mutasi.
- Stok masuk tanpa harga beli (Adjustment +, kelebihan hasil hitung) dinilai pada harga rata-rata gudang itu, atau **harga beli terakhir** item bila gudang sedang kosong.
- Transfer: barang tiba di gudang tujuan membawa nilai yang sama saat keluar dari gudang asal.
- **Sparepart dari work order & repair**: tab **Spare Parts** di work order (In Progress) dan tiket repair (In Repair) → tombol **Use Spare Parts** membuat dan langsung memposting Goods Issue yang bersumber dari WO/tiket tersebut. Infolist menampilkan **Spare Parts Used**. *Actual Cost* tetap untuk jasa/vendor; biaya sparepart dihitung terpisah dari nilai stok yang keluar.
- **Item Requests**: `REQ/YYMM/SEQ`, karyawan peminta, departemen, tanggal dibutuhkan, tujuan, daftar barang. Alur `Waiting for Approval → Approved → Issued`, bisa **Rejected** (alasan wajib) atau **Cancelled** sebelum dikeluarkan. **Issue Items** memilih gudang dan mengeluarkan seluruh permintaan sekaligus; bila gudang tidak mencukupi, tidak ada yang dikeluarkan dan permintaan tetap Approved. Hanya bisa diedit sebelum diputuskan.
- **Notifikasi**: permintaan baru → pemegang `Approve:ItemRequest`; keputusan → pencatat permintaan; item turun di bawah minimum → pemegang `Create:StockDocument`, **sekali saat melewati batas** (item yang sudah kurang tidak diberitahukan ulang). Pemosting ikut diberi tahu karena ini akibat, bukan keputusan.
- Widget dashboard **Low Stock**: item di bawah minimum dengan **Suggested Purchase** (jumlah pembelian ulang, atau kekurangannya bila tidak diisi).
- `RoleSeeder`: asset_staff kini menulis StockItem, StockDocument, ItemRequest; tidak menyetujui permintaan dan tidak memposting adjustment/count. Auditor hanya melihat.
- **Tes**: 335 tes, 1.008 asersi (52 baru untuk stok, sparepart, permintaan barang, `Quantity`, `Money::share`).

### Catatan teknis
- Kuantitas `decimal(18,2)` dihitung sebagai **perseratus bilangan bulat** lewat `App\Support\Quantity`, sejajar dengan `Money` untuk rupiah. `Money::share($sen, $bagian, $keseluruhan)` membagi proporsional dengan pembulatan setengah menjauhi nol, dipecah agar nilai stok besar tidak overflow integer.
- `stock_balances` adalah cache dari `stock_movements` (append-only), sama seperti `assets.current_*` terhadap `asset_movements`. Satu-satunya penulisnya `StockLedger`.
- Baris saldo dibuat dengan `createOrFirst` lalu dibaca ulang dengan `lockForUpdate`, sehingga dua posting bersamaan untuk item & gudang baru tidak saling tabrak.
- Dokumen sparepart dan permintaan barang memakai kolom polimorfik `source` di `stock_documents`; relasi `sparePartLines` (trait `UsesSpareParts`) dipakai WorkOrder dan RepairTicket.
- Migrasi sudah dijalankan di database dev. **Permission baru belum terbagi ke peran** — jalankan `php artisan db:seed --class=RoleSeeder` (menyinkronkan ulang permission keempat peran bawaan) atau `composer shield` lalu atur lewat Settings → Roles, agar StockItem, StockDocument, ItemRequest, `Approve:ItemRequest`, dan `Post:StockAdjustment` muncul.

### Berikutnya
~~Penghapusan aset (disposal) + usul dari tiket "Cannot Be Repaired"~~ · Audit/opname aset via scan QR · ekspor laporan stok & kartu stok · pengeluaran sebagian untuk permintaan barang · retur sparepart yang tidak terpakai · minimum stok per gudang.

---

## 15. Fase 3 — Penghapusan Aset (selesai)

### Keputusan
| Topik | Keputusan |
|---|---|
| Approval | **Satu tingkat** — staff mengusulkan dan melaksanakan, pemegang `Approve:AssetDisposal` (asset_manager) menyetujui atau menolak |
| Penyusutan bulan penghapusan | **Tidak disusutkan** — kebalikan dari bulan perolehan yang dihitung penuh. Penghapusan baru bisa diselesaikan bila penyusutan **kedua buku sudah diposting sampai bulan sebelumnya** |
| Dokumen | **Satu dokumen, banyak aset** (seperti BAST); metode dan nilai jual per aset, laba/rugi per aset dan total |
| Aset yang boleh dihapus | Berstatus Available, In Storage, Retired, atau Lost; tidak sedang dipegang karyawan; tidak ada di usulan penghapusan lain yang masih terbuka |

### Yang sudah jalan
- **Disposals** (grup navigasi Assets): nomor `DSP/YYMM/SEQ`, tanggal penghapusan, pembeli/penerima, nomor lelang/invoice, alasan, catatan, daftar aset dengan metode **Sold / Traded In / Donated / Scrapped / Lost** dan nilai jual (hanya Sold & Traded In). Alur `Waiting for Approval → Approved → Completed`, bisa **Rejected** (alasan wajib) atau **Cancelled** sebelum selesai. Hanya bisa diedit sebelum diputuskan.
- **Complete Disposal** memproses semua aset sekaligus atau tidak sama sekali: menetapkan harga perolehan, akumulasi, **nilai buku, dan laba/rugi di buku komersial dan fiskal**, lalu mencatat movement `disposal` di ledger sehingga aset menjadi **Disposed** — sejak itu tidak lagi disusutkan. Tanggal penghapusan tidak boleh di masa depan.
- Nilai buku = harga perolehan − akumulasi penyusutan **terposting** sebelum bulan penghapusan. Ditolak bila: masih ada bulan terjadwal sebelum bulan penghapusan yang belum diposting (pesan menyebut bulannya), atau aset sudah ikut diposting pada bulan penghapusan atau sesudahnya. Aset yang diperoleh di bulan penghapusan atau yang tidak disusutkan keluar pada harga perolehan. Aset tanpa kelompok fiskal: nilai buku fiskal = harga perolehan.
- Sebelum selesai, daftar aset menampilkan **perkiraan nilai buku** dari penyusutan yang sudah diposting, agar approver bisa menilai.
- **Berita Acara Penghapusan Aset** (PDF A4 landscape, Bahasa Indonesia): rincian per aset, jumlah, laba/(rugi) fiskal, tanda tangan pengusul, penyetuju, pelaksana; bertanda *DRAF* sebelum selesai.
- Tombol **Propose Disposal** di halaman aset, dan di tiket repair berstatus *Cannot Be Repaired* — form terisi aset, metode Scrapped, alasan dari resolusi tiket, dan tautan balik ke tiket.
- Tab **Disposal** di halaman aset: riwayat usulan penghapusan aset tersebut.
- **Notifikasi**: usulan baru → pemegang `Approve:AssetDisposal`; keputusan → pengusul.
- Hak akses: asset_staff kini menulis AssetDisposal (usul, batal, selesaikan) tanpa hak menyetujui.
- **Tes**: 358 tes, 1.089 asersi (23 baru untuk penghapusan).

### Catatan teknis
- Transisi status aset: Available dan In Storage kini boleh langsung ke Disposed. `AssetMovementRecorder` tetap menolak setiap perpindahan aset Lost/Disposed, **kecuali** movement `disposal` dari Lost.
- `DepreciationRunner::schedule()` dan `lastPostedMonth()` kini publik agar `DisposalValuation` memakai jadwal yang sama persis dengan posting bulanan.
- Kolom hasil di `asset_disposal_lines` diisi sekali saat selesai dan tidak dihitung ulang, sehingga dokumen tetap sama walau data aset berubah kemudian.
- Migrasi dan `RoleSeeder` sudah dijalankan di database dev (permission hanya bertambah; tidak ada yang dicabut).

### Berikutnya
~~Audit/opname aset via scan QR (terakhir di Fase 3)~~ · jurnal penghapusan (debit akumulasi & rugi, kredit aset) untuk ekspor akuntansi · laporan penghapusan + laba/rugi per periode (Fase 4).

---

## 16. Fase 3 — Audit / Opname Aset (selesai — Fase 3 tuntas)

### Keputusan
| Topik | Keputusan |
|---|---|
| Cara scan | **Tanpa dependensi baru** — satu kolom kode di halaman scan (scanner gun Code128, ketik manual). Saat petugas sudah memilih ruangan di halaman scan, membuka QR label `/a/{kode}` dengan kamera bawaan HP langsung mencatat aset ke audit itu |
| Tindak lanjut selisih | **Dipilih per temuan** oleh pemeriksa: pindahkan ke lokasi temuan, perbarui kondisi, atau tandai hilang — diterapkan sebagai movement *Audit Adjustment* |
| Peran | Auditor & asset_staff membuat sesi dan memindai; menerapkan tindak lanjut & menutup audit butuh `Close:AssetAudit` (asset_manager) |

### Yang sudah jalan
- **Asset Audits** (grup Assets): nomor `AUD/YYMM/SEQ`, judul, cakupan **lokasi (termasuk semua ruangan di bawahnya)** dan/atau **departemen (termasuk aset yang dipegang karyawannya)**, opsional kategori (termasuk subkategori). Saat dibuat, daftar aset yang diharapkan diambil dari register saat itu (aset Disposed/Lost tidak ikut) dan cakupan tidak bisa diubah lagi.
- Alur `Counting → Under Review → Completed`, bisa **Cancelled** sebelum ditutup (register tidak berubah).
- **Halaman Scan** (ramah HP): pilih ruangan/gudang (dibatasi ke cakupan lokasi), kondisi opsional ("As recorded"), lalu kode. Hasil langsung: *Found*, *Wrong Location* (ditemukan di ruangan lain dari catatan), atau *Not on the list* (aset di luar daftar ikut ditambahkan). Scan ulang menggantikan scan sebelumnya. Kode tak dikenal dan aset yang sudah dihapus ditolak. Daftar "Scanned So Far" dan ringkasan progres di atas halaman.
- **Finish Counting**: aset yang belum di-scan menjadi **Missing**; usulan otomatis — aset salah lokasi dipindahkan dan kondisi yang berubah diperbarui — sedangkan **tandai hilang tidak pernah otomatis**.
- **Peninjauan**: tab Assets di halaman audit berisi hasil per aset dengan toggle *Move*, *Update Condition*, *Mark Lost* (hanya aktif bila cocok dengan temuannya dan hanya bagi pemegang `Close:AssetAudit`), filter *Findings only*.
- **Apply & Close**: semua koreksi diterapkan sekaligus atau tidak sama sekali, lewat `AssetMovementRecorder` (movement `audit_adjustment` dengan referensi ke audit). Status aset dipertahankan kecuali pindah antara ruangan dan gudang. **Aset yang sudah berpindah setelah di-scan tidak dikoreksi** — penutupan ditolak dengan nama asetnya, agar koreksi dari pengamatan lama tidak membatalkan perpindahan yang sah.
- **Berita Acara Hasil Audit Aset** (PDF A4 landscape, Bahasa Indonesia): cakupan, ringkasan (terdaftar, ditemukan, salah lokasi, tidak ditemukan, kondisi berubah, di luar daftar), tabel temuan dengan tindak lanjutnya (bertanda *usulan* sebelum ditutup), tanda tangan petugas & pemeriksa; *DRAF* sebelum selesai.
- `RoleSeeder`: auditor kini boleh membuat & mengerjakan AssetAudit (subjek lain tetap baca saja); asset_staff juga.
- **Tes**: 382 tes, 1.172 asersi (24 baru untuk audit).

### Catatan teknis
- Ruangan yang dipilih di halaman scan disimpan di session (`asset_audit_scan`); `AssetLookupController` hanya mencatat scan bila user login, audit masih *Counting*, dan user berhak `update` audit itu — selain itu QR tetap membuka halaman aset seperti biasa.
- Deteksi "sudah berpindah" memakai `created_at` movement, bukan `moved_at`, karena serah terima memakai tanggal dokumen (tengah malam) yang bisa lebih awal dari jam scan.
- Aset yang dipegang karyawan tidak punya ruangan tercatat, sehingga di-scan di ruangan mana pun dihitung *Found*.
- Foto kondisi saat scan belum ada.

---

## 17. Import & Export Aset

### Keputusan
| Topik | Keputusan |
|---|---|
| Mekanisme | **Seperti lastmile** — Importer/Exporter Filament lewat queue, dengan `ImportAction` yang juga membaca XLSX/ODS, hanya mengenali pemisah koma atau titik koma, dan opsi *tolak seluruh file bila ada baris gagal* |
| Aset yang sudah ada | **Import hanya menambah aset baru**. Baris berisi kode aset ditolak; perubahan posisi, status, dan nilai tetap lewat dokumennya masing-masing |

### Yang sudah jalan
- Tombol **Import** (untuk pemegang `Create:Asset`) dan **Export** di daftar aset.
- **Export**: CSV/XLSX mengikuti filter tabel. Header sama dengan kolom import, dan master data ditulis sebagai kode yang dicari importer — export dengan kolom `code` dikosongkan langsung menjadi template import. Nominal ditulis sebagai angka di XLSX; nama kategori dan posisi tersedia sebagai kolom opsional.
- **Import** per baris:
  - Wajib: nama, kategori, cabang, tanggal & harga perolehan, serta **salah satu** ruangan/gudang (kode atau nama, di cabang aset) atau nomor karyawan pemegang.
  - Master dicocokkan lewat kode lalu nama tanpa peduli huruf besar/kecil; nama yang cocok ke lebih dari satu data ditolak dan meminta kode. Master yang tidak ada tidak dibuat.
  - Kode aset dibuat otomatis seperti di form, dan **movement awal dicatat di ledger**; kode, simpan, dan movement dalam satu transaksi.
  - Kosong berarti default: status *in_use* bila di ruangan/karyawan atau *available* bila di gudang; kondisi *good*; metode, masa manfaat, dan metode fiskal mengikuti kategori; tanggal mulai susut = tanggal perolehan; departemen mengikuti karyawan pemegang.
  - Status awal hanya *available, in_storage, in_use, retired*. Tanggal hanya dibaca sebagai `YYYY-MM-DD` (atau sel tanggal di Excel) — `03/04/2026` ditolak karena ambigu. Tanggal perolehan tidak boleh di masa depan.
- Opsi **Reject the whole file if any row fails** (default aktif): seluruh baris diperiksa lebih dulu tanpa menulis apa pun; bila ada yang gagal, file dikembalikan dengan daftar barisnya. Dilepas, baris yang baik masuk dan baris gagal bisa diunduh setelah selesai.
- **Tes**: 398 tes, 1.226 asersi (16 baru untuk import/export).

### Catatan teknis
- **Butuh queue worker.** `php artisan dev` sudah menjalankan `queue:listen`; di server jalankan `php artisan queue:work` lewat supervisor. Tanpa worker, import/export hanya menunggu dan notifikasi selesai tidak pernah datang.
- Tabel `imports`, `exports`, `failed_import_rows` dari Filament (`vendor:publish --tag=filament-actions-migrations`); `job_batches` sudah ada.
- Port dari lastmile: `App\Filament\Actions\ImportAction`, `App\Support\Spreadsheet`, `App\Support\ImportPreflight`, `App\Support\Search`, kontrak `ValidatesFileUpFront` + `ValidatesRowsUpFront`, `NumberExportColumn` + `HasNumberExportColumns`. Modul lain tinggal menambah Importer/Exporter-nya sendiri.

### Berikutnya (Fase 4)
Portal karyawan · laporan & ekspor lengkap (termasuk kartu stok, penghapusan, hasil audit) · widget aset belum diaudit, TCO, MTTR/MTBF · foto kondisi saat audit · notifikasi email/WhatsApp · backup terjadwal.
