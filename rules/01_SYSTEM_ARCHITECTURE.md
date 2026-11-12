# 01_SYSTEM_ARCHITECTURE.md

# Smart Document Automation Dashboard

## System Architecture

Version: 1.0

------------------------------------------------------------------------

# 1. Purpose

Dokumen ini menjelaskan arsitektur sistem Smart Document Automation
Dashboard sebagai acuan implementasi selama proses pengembangan.

Arsitektur dirancang sederhana, mudah dipelihara, dan tidak mengubah
proses bisnis yang sudah berjalan.

------------------------------------------------------------------------

# 2. Architecture Principle

Prinsip utama:

-   Google Drive adalah penyimpanan utama dokumen.
-   Google Sheet tetap menjadi media pelaporan resmi.
-   Website menjadi portal kerja.
-   Laravel menjadi pusat business logic.
-   Semua komunikasi Google dilakukan melalui Service Layer.

------------------------------------------------------------------------

# 3. High Level Architecture

``` text
                    USER
                      │
                      ▼
        Smart Document Automation Dashboard
                      │
          Laravel (Controller + Service)
             │                 │
             │                 ├──────────────┐
             ▼                 ▼              ▼
      Dashboard         Google Drive API   Google Sheet API*
                              │
                              ▼
                        Google Drive

*Google Sheet hanya untuk pengembangan berikutnya.
```

------------------------------------------------------------------------

# 4. System Components

## Frontend

Menggunakan:

-   Blade Template
-   Tailwind CSS

Fungsi:

-   Dashboard
-   Drive Explorer
-   Monitoring

Tidak menggunakan:

-   React
-   Vue
-   Livewire
-   Inertia

------------------------------------------------------------------------

## Backend

Menggunakan Laravel.

Tanggung jawab:

-   Routing
-   Validasi
-   Business Logic
-   Integrasi Google API

------------------------------------------------------------------------

## Google Drive

Berfungsi sebagai:

-   Penyimpanan Folder
-   Penyimpanan Dokumen
-   Sumber Status Monitoring

Google Drive adalah **Single Source of Truth**.

------------------------------------------------------------------------

## Google Sheet

Tetap digunakan oleh pengguna sebagai laporan.

Website tidak mengubah format spreadsheet yang sudah ada.

------------------------------------------------------------------------

# 5. Laravel Architecture

``` text
routes/
app/
├── Http/
│   ├── Controllers/
│   └── Requests/
├── Services/
│   ├── GoogleDriveService.php
│   └── MonitoringService.php
├── Support/
config/
resources/
└── views/
```

------------------------------------------------------------------------

# 6. Controller Responsibility

Controller hanya bertugas:

-   menerima request
-   validasi
-   memanggil Service
-   mengembalikan response

Controller tidak boleh berisi business logic.

------------------------------------------------------------------------

# 7. Service Layer

## GoogleDriveService

Tanggung jawab:

-   Login Service Account
-   List Folder
-   List File
-   Create Folder
-   Upload File
-   Download File
-   Get Metadata

------------------------------------------------------------------------

## MonitoringService

Tanggung jawab:

-   Scan Folder
-   Menghitung jumlah file
-   Menentukan status
-   Menghasilkan data dashboard

------------------------------------------------------------------------

# 8. Module Communication

``` text
Dashboard Controller
        │
        ▼
Monitoring Service
        │
        ▼
Google Drive Service
        │
        ▼
Google Drive API
```

Drive Explorer menggunakan pola yang sama.

------------------------------------------------------------------------

# 9. Request Flow

## Upload File

``` text
User
 ↓
Drive Explorer
 ↓
Upload Controller
 ↓
GoogleDriveService
 ↓
Google Drive API
 ↓
File berhasil tersimpan
```

------------------------------------------------------------------------

## Create Folder

``` text
User
 ↓
Drive Explorer
 ↓
Folder Controller
 ↓
GoogleDriveService
 ↓
Google Drive
```

------------------------------------------------------------------------

## Monitoring

``` text
Monitoring Page
 ↓
Monitoring Controller
 ↓
Monitoring Service
 ↓
GoogleDriveService
 ↓
Google Drive
 ↓
Status Hijau / Merah
```

------------------------------------------------------------------------

# 10. Folder Navigation

Website harus mendukung navigasi folder tanpa batas.

``` text
Semester
   ↓
Bulan
   ↓
Daerah
   ↓
Unit
   ↓
File
```

Gunakan breadcrumb pada setiap halaman.

------------------------------------------------------------------------

# 11. Security

-   Menggunakan Google Service Account.
-   Credentials dibaca dari `.env`.
-   Tidak menyimpan private key di source code.
-   Validasi semua upload file.
-   Batasi ukuran file sesuai kebutuhan.

------------------------------------------------------------------------

# 12. Error Handling

Jika Google Drive gagal diakses:

-   tampilkan pesan yang jelas
-   jangan menampilkan stack trace
-   catat ke Laravel log

------------------------------------------------------------------------

# 13. Scalability

Arsitektur harus memungkinkan penambahan modul tanpa mengubah modul yang
sudah ada, misalnya:

-   Google Sheet Sync
-   Export PDF
-   Search
-   Activity Log

------------------------------------------------------------------------

# 14. Summary

Arsitektur proyek mengikuti pola:

``` text
User
   │
   ▼
Laravel
   │
Service Layer
   │
Google Drive API
   │
Google Drive
```

Dengan pendekatan ini:

-   Controller tetap tipis.
-   Business logic terpusat pada Service.
-   Google Drive menjadi sumber data utama.
-   Website hanya menjadi portal untuk mengelola dan memonitor dokumen.
