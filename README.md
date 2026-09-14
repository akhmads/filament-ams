# AMS — Asset Management System

Pencatatan aset perusahaan: registrasi aset, cetak label QR/barcode, serah terima
dengan berita acara, dan riwayat perpindahan yang tidak bisa diubah.

Dibangun dengan **Laravel 13**, **Filament 5**, dan **MySQL 8**.

## Yang sudah berjalan (Fase 1)

- **Aset** — form enam tab, kode otomatis (`LAP-HO-2609-0001`), spesifikasi dinamis per
  kategori, foto dan dokumen lampiran
- **Label** — QR mengarah ke `/a/{kode}`, Code 128, tiga template, cetak satuan dan massal
- **Serah terima** — dokumen BAST multi-aset, tanda tangan di kanvas, cetak PDF
- **Posisi aset** — ledger `asset_movements` yang append-only; kolom posisi pada `assets`
  hanya cache dari baris terakhir
- **Master data** — kategori dan lokasi bertingkat, cabang, departemen, karyawan, merek,
  model, supplier
- **Hak akses** — 161 permission dan 4 peran lewat Filament Shield

Konsep lengkap, keputusan desain, dan rencana fase berikutnya ada di
[`docs/KONSEP-AMS.md`](docs/KONSEP-AMS.md).

## Menjalankan

```bash
composer install
npm install && npm run build

cp .env.example .env
php artisan key:generate
# sesuaikan DB_* di .env

php artisan migrate --seed
php artisan serve
```

Masuk di `/admin` dengan `admin@admin.com` / `password`.
**Ganti kata sandi ini sebelum dipakai sungguhan.**

## Tes

```bash
php artisan test
```

## Catatan

- Antarmuka dan komentar kode berbahasa Inggris. Dokumen cetak — BAST dan label aset —
  tetap Bahasa Indonesia karena ditandatangani dan diarsipkan secara fisik.
- Web server membutuhkan izin tulis ke `storage/` dan `bootstrap/cache`.
