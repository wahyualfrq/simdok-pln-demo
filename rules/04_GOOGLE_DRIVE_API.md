# 04_GOOGLE_DRIVE_API.md

# Smart Document Automation Dashboard

## Google Drive API Integration Specification

Version: 1.0

------------------------------------------------------------------------

# 1. Purpose

Dokumen ini menjadi acuan implementasi integrasi Google Drive API pada
aplikasi.

Seluruh akses ke Google Drive wajib dilakukan melalui Service Layer agar
kode tetap bersih, mudah diuji, dan mudah dikembangkan.

------------------------------------------------------------------------

# 2. Objectives

Integrasi Google Drive digunakan untuk:

-   Membaca struktur folder
-   Membaca daftar file
-   Membuat folder
-   Upload file
-   Download file
-   Mendapatkan metadata file
-   Mendukung monitoring dokumen

Google Drive adalah **Single Source of Truth**.

------------------------------------------------------------------------

# 3. Authentication

Metode autentikasi menggunakan **Google Service Account**.

Konfigurasi berasal dari `.env`.

``` env
GOOGLE_CREDENTIALS_PATH=storage/app/google/service-account.json
```

Jangan menyimpan private key di source code.

------------------------------------------------------------------------

# 4. Required Package

Gunakan library resmi Google.

``` bash
composer require google/apiclient
```

------------------------------------------------------------------------

# 5. Service Layer

Semua komunikasi dilakukan melalui:

``` text
app/
└── Services/
    └── GoogleDriveService.php
```

Controller tidak boleh memanggil Google API secara langsung.

------------------------------------------------------------------------

# 6. Responsibilities

GoogleDriveService bertanggung jawab atas:

-   Authentication
-   List Folder
-   List File
-   Create Folder
-   Upload File
-   Download File
-   Search Folder
-   Get Metadata

------------------------------------------------------------------------

# 7. Public Methods

Method yang direkomendasikan:

``` php
authenticate()

listFolders(string $parentId)

listFiles(string $parentId)

getFolder(string $folderId)

searchFolder(string $name, string $parentId)

createFolder(string $name, string $parentId)

uploadFile(string $folderId, UploadedFile $file)

downloadFile(string $fileId)

deleteFile(string $fileId)

getMetadata(string $id)
```

Semua method mengembalikan object atau collection yang konsisten.

------------------------------------------------------------------------

# 8. Upload Flow

``` text
User
   │
Drive Explorer
   │
UploadController
   │
GoogleDriveService
   │
Google Drive API
   │
Google Drive
```

Upload dilakukan langsung ke folder aktif.

Tidak ada penyimpanan sementara pada database.

------------------------------------------------------------------------

# 9. Create Folder Flow

``` text
User
    │
Drive Explorer
    │
FolderController
    │
GoogleDriveService
    │
Google Drive API
```

Folder dibuat berdasarkan parent folder yang dipilih.

------------------------------------------------------------------------

# 10. Folder Navigation

Navigasi menggunakan Parent Folder ID.

``` text
Root
 │
 ├── Semester
 │      │
 │      ├── Bulan
 │      │      │
 │      │      ├── Daerah
 │      │      │      │
 │      │      │      └── Unit
```

Setiap klik folder akan meminta daftar child folder dari Google Drive.

------------------------------------------------------------------------

# 11. File Metadata

Minimal metadata yang digunakan:

-   id
-   name
-   mimeType
-   parents
-   createdTime
-   modifiedTime
-   size
-   webViewLink

------------------------------------------------------------------------

# 12. Monitoring Integration

Monitoring tidak membaca database.

Monitoring memanggil:

``` text
MonitoringService
        │
        ▼
GoogleDriveService
        │
        ▼
Google Drive
```

Kemudian menghitung jumlah file pada folder unit.

------------------------------------------------------------------------

# 13. Error Handling

Tangani kondisi berikut:

-   Folder tidak ditemukan
-   File gagal upload
-   Token tidak valid
-   Akses ditolak
-   Koneksi internet gagal
-   Google API timeout

Semua exception dicatat ke Laravel Log.

------------------------------------------------------------------------

# 14. Performance Strategy

Untuk mengurangi request:

-   Ambil field yang diperlukan saja.
-   Gunakan pagination jika diperlukan.
-   Hindari request berulang pada folder yang sama.
-   Pisahkan proses monitoring dan browsing.

------------------------------------------------------------------------

# 15. Security Rules

-   Validasi tipe file.
-   Validasi ukuran file.
-   Jangan mengekspos Folder ID ke pengguna jika tidak diperlukan.
-   Semua request melalui Service Layer.
-   Jangan hardcode credential.

------------------------------------------------------------------------

# 16. Future Extension

GoogleDriveService harus mudah dikembangkan untuk:

-   Rename Folder
-   Move File
-   Copy File
-   Batch Upload
-   Batch Create Folder
-   Preview File
-   Trash Management

------------------------------------------------------------------------

# 17. Summary

Arsitektur integrasi mengikuti pola berikut:

``` text
Controller
     │
     ▼
GoogleDriveService
     │
     ▼
Google Drive API
     │
     ▼
Google Drive
```

Dengan pendekatan ini:

-   Business logic terpusat.
-   Controller tetap tipis.
-   Integrasi mudah dipelihara.
-   Seluruh modul (Dashboard, Drive Explorer, Monitoring) menggunakan
    service yang sama sehingga tidak terjadi duplikasi kode.
