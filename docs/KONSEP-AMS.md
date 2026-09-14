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
| **Pengaturan** | Identitas perusahaan, logo, format penomoran, tahun fiskal, dan **Appearance** — lebar area konten panel, memakai enum `Width` bawaan Filament |
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
