# SIMDOK — Smart Document Automation Dashboard (Versi DEMO)

> **PERHATIAN — INI ADALAH VERSI DEMO UNTUK PORTOFOLIO.**
> Aplikasi ini **bukan sistem resmi PT PLN** dan tidak terhubung ke sistem, akun, maupun dokumen PT PLN mana pun.
> Seluruh data (folder, file, status monitoring, nama penandatangan) adalah **data dummy**, dan integrasi
> Google Drive / Google Sheets **disimulasikan**. Logo dan nama PLN hanya dipertahankan agar tampilan
> serta alur kerja sama dengan aplikasi aslinya.

## Tujuan

SIMDOK adalah dasbor otomasi dokumen: memantau kelengkapan dokumen bukti per unit dan per bulan di Google Drive,
mencatat checklist di Google Sheets, membuat struktur folder secara otomatis, serta menghasilkan laporan monitoring
(Excel & PDF). Repo ini adalah **versi demo** yang bisa dicoba publik tanpa akun Google dan tanpa data nyata,
untuk memperlihatkan alur dan antarmukanya.

## Akun demo

Halaman login sudah **terisi otomatis**; cukup tekan **Login**.

| Kolom     | Nilai       |
|-----------|-------------|
| Username  | `demo`      |
| Password  | `demo12345` |

Akun ini bersifat publik dan hanya ada di data dummy.

## Fitur yang disimulasikan

| Fitur | Di versi demo |
|-------|---------------|
| Koneksi Google (OAuth) | Tombol *Hubungkan* menuju halaman simulasi internal — **tidak** ke Google. Status koneksi tersimpan per sesi pengunjung (awalnya sudah terhubung). Setelah *putuskan* lalu *hubungkan* ulang, Root Folder harus dipilih lagi (sama seperti aslinya). |
| Google Drive | Pohon folder dummy deterministik (Des 2025 – Nov 2026, 12 bulan → region → unit → file). Buat folder, unggah, ganti nama, hapus, dan unduh berjalan pada *overlay* per sesi; tidak ada yang dikirim ke Drive sungguhan. Pratinjau file memakai halaman placeholder demo. |
| Google Sheets (checklist) | Dihitung dari pohon folder dummy; perubahan checklist disimpan di overlay sesi. |
| Sinkronisasi & Full Sync | Tidak memindai apa pun. Progres dihitung berdasarkan waktu (±12 detik); setelah selesai beberapa unit merah berubah hijau secara deterministik. |
| Auto Generate Folder | Folder dibuat di overlay saat request; progres ditampilkan berbasis waktu (±6 detik). |
| Queue / job latar belakang | Tanpa worker — queue dipaksa `sync`. |
| Pratinjau & ekspor PDF | Hanya animasi "Mengonversi ke PDF…" yang disimulasikan; hasilnya **PDF statis** per semester (`resources/demo/pdf/`) yang dibuat sekali dari data dummy dengan dompdf. |
| Ekspor Excel | Sama dengan aslinya, memakai template `Monitoring_Template.xlsx` dengan penandatangan dummy. |

Setiap halaman terkait diberi catatan **"demonstrasi"**; banner demo tampil di bagian atas aplikasi.

## Keterbatasan (serverless / demo)

- **Data bersifat sementara dan per-instance.** Di Vercel, database SQLite disalin ke `/tmp` saat *cold start*;
  perubahan hilang ketika instance berganti/tidur, dan bisa berbeda antar instance. Overlay Drive per sesi
  dikosongkan ketika sesi baru dimulai (mis. setelah logout lalu login).
- **Tanpa queue worker** dan tanpa LibreOffice. Runtime `vercel-php` juga tidak menyertakan ekstensi `gd`
  (relevan bila PDF contoh dibuat ulang dengan dompdf; di runtime demo PDF tidak dikonversi).
- **PDF bersifat statis:** pratinjau/unduh PDF pada mode demo memakai berkas contoh yang sudah disiapkan dari data
  dummy awal, sehingga mengubah footer atau status monitoring tidak mengubah isi PDF (ekspor Excel tetap dibuat
  sungguhan). Kode konversi dompdf tetap ada di `DemoPdfConverter::renderWithDompdf()` untuk membuat ulang PDF contoh.
- **Unggah file maksimal ±4 MB** (batas body request fungsi Vercel ±4,5 MB). File tidak disimpan; hanya metadata.
- **Status sel Monitoring dan isi folder Drive selalu konsisten dan bersifat per sesi.** Setiap pengunjung yang
  login mendapat salinan database dan cache sendiri (`DemoSessionIsolation`), jadi mengubah status folder
  (Belum Upload / Pending / Complete) tidak memengaruhi pengunjung lain. Sel *Belum Upload* = folder kosong;
  Pending/Complete = folder berisi file dummy. Pada folder bersama (beberapa unit dalam satu folder region,
  mis. KANTOR UID) perubahan status berlaku untuk semua unit di folder itu.
- **Kasus tepi 409:** bila sinkronisasi/generate sudah melewati durasi simulasinya tetapi halaman tidak melakukan
  polling, permintaan berikutnya dapat dijawab 409 ("sedang berjalan") sekali sebelum status diperbarui.
- Template Excel memuat beberapa unit yang tidak ada di konfigurasi struktur folder, sehingga barisnya muncul
  tanpa data pada pemindaian.
- Bug lama yang diketahui dan **sengaja tidak diubah** (di luar lingkup demo): variabel `$driveStatus` pada alur
  Drive Explorer.

## Menjalankan secara lokal

Prasyarat: PHP ≥ 8.4 (ekstensi `gd`, `zip`, `mbstring`, `pdo_sqlite`, `intl`, `fileinfo`, `curl`, `openssl`),
Composer, dan (opsional) Node.js 20+ bila ingin membangun ulang aset.

```bash
composer install
cp .env.example .env
php artisan key:generate

# database lokal (SQLite)
touch database/database.sqlite
php artisan migrate --seed      # akun demo + data dummy
# atau: cp database/demo.sqlite database/database.sqlite

php artisan serve
```

Buka `http://localhost:8000` — `DEMO_MODE=true` sudah ada di `.env.example`. Aset Vite hasil build sudah
di-commit di `public/build`; bila mengubah CSS/JS jalankan `npm ci && npm run build`.

Menjalankan tes:

```bash
php artisan test                         # tes bawaan
php artisan test -c phpunit.demo.xml     # smoke test alur demo (DEMO_MODE=true)
```

## Deploy ke Vercel

Aplikasi dijalankan sebagai satu fungsi PHP (`api/index.php`) memakai runtime komunitas
[`vercel-php`](https://github.com/vercel-community/php) (lihat `vercel.json`). `api/index.php` mengalihkan seluruh
jalur tulis Laravel (storage, cache bootstrap, view terkompilasi, SQLite) ke `/tmp`, karena filesystem Vercel
read-only.

1. Buat project Vercel dari repo ini (framework preset: *Other*; tanpa build command — aset sudah di-commit).
2. Isi *Environment Variables* mengikuti contoh di [`.env.vercel.example`](.env.vercel.example).
   Buat `APP_KEY` **baru** dengan `php artisan key:generate --show`. Jangan mengisi variabel `GOOGLE_*`.
3. Deploy. Setelah selesai, set `APP_URL` ke URL deployment (HTTPS) lalu deploy ulang bila perlu.

Catatan: `vercel-php` adalah runtime komunitas; periksa statusnya sebelum deploy karena repositorinya dapat
berubah/diarsipkan. Alternatifnya adalah menjalankan aplikasi di container.

## Arsitektur demo

- **`DEMO_MODE`** (`config('demo.enabled')`): satu-satunya saklar. `false` = perilaku aplikasi asli (Google
  sungguhan); `true` = demo. Semua perubahan ditandai `// DEMO:` atau `{{-- DEMO: --}}`.
- **Fake service**: kelas `Fake*` di `app/Services/Demo/` *meng-extend* kelas asli (`GoogleOAuthService`,
  `GoogleDriveService`, `GoogleSheetService`, `DriveMonitoringSyncService`, `FolderGeneratorService`) dan
  di-bind lewat `AppServiceProvider` hanya ketika `DEMO_MODE=true`. Controller dan view asli tidak berubah alurnya.
- **Data dummy**: `DemoDriveTree` membangun pohon Drive yang deterministik; `DemoSeeder` mengisi database
  (monitoring, registry folder, log sinkronisasi) dari pohon yang sama sehingga angka di dasbor, monitoring, dan
  Drive Explorer konsisten. `DemoDriveStore` menyimpan overlay mutasi per sesi.
- **Simulasi progres**: `DemoProgressSimulator` menghitung progres berbasis waktu saat model log dibaca.
- **Konfigurasi**: `config/demo.php` (akun, durasi, jumlah bulan, banner, nama penandatangan dummy).

## Stack

Laravel 13 · PHP 8.4+ · Blade · Tailwind CSS 4 · Vite 8 · SQLite · PhpSpreadsheet · dompdf ·
google/apiclient (hanya dipakai pada `DEMO_MODE=false`; paket layanan dipangkas ke Drive dan Sheets).
