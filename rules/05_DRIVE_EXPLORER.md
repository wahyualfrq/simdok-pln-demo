# 05_DRIVE_EXPLORER.md

# Smart Document Automation Dashboard

## Drive Explorer Module Specification

Version: 1.0

------------------------------------------------------------------------

# 1. Purpose

Drive Explorer adalah modul utama aplikasi yang menggantikan kebutuhan
pengguna membuka Google Drive secara langsung.

Modul ini menyediakan pengalaman seperti file manager, namun seluruh
operasi dilakukan melalui Google Drive API.

------------------------------------------------------------------------

# 2. Objectives

Drive Explorer harus memungkinkan pengguna untuk:

-   Menelusuri struktur folder.
-   Masuk ke subfolder tanpa batas.
-   Membuat folder baru.
-   Mengunggah dokumen.
-   Mengunduh dokumen.
-   Melihat informasi folder dan file.
-   Menjadi tujuan navigasi dari halaman Monitoring.

------------------------------------------------------------------------

# 3. User Flow

``` text
Dashboard
    │
    ▼
Drive Explorer
    │
    ├── Browse Folder
    ├── Open Folder
    ├── Upload File
    ├── Create Folder
    └── Download File
```

------------------------------------------------------------------------

# 4. Page Layout

``` text
+---------------------------------------------------------+
| Header                                                  |
+----------------------+----------------------------------+
| Sidebar              | Breadcrumb                      |
|                      +----------------------------------+
| Semester             | Toolbar                         |
| Bulan                +----------------------------------+
|                      | Folder & File List              |
|                      |                                 |
|                      |                                 |
|                      |                                 |
+----------------------+----------------------------------+
```

------------------------------------------------------------------------

# 5. Components

## Header

Menampilkan:

-   Nama aplikasi
-   Nama pengguna (opsional)
-   Tombol Refresh

------------------------------------------------------------------------

## Sidebar

Sidebar hanya berisi menu utama:

-   Dashboard
-   Drive Explorer
-   Monitoring

------------------------------------------------------------------------

## Breadcrumb

Contoh:

``` text
Semester 1
/
Januari
/
PLN UP3 PALEMBANG
/
MULP KENTEN
```

Setiap bagian breadcrumb dapat diklik.

------------------------------------------------------------------------

## Toolbar

Toolbar minimal memiliki:

-   Upload File
-   Create Folder
-   Refresh

Pencarian file dapat ditambahkan pada versi berikutnya.

------------------------------------------------------------------------

# 6. Folder Navigation

Navigasi menggunakan Parent Folder ID.

Alur:

``` text
Klik Folder
    │
GoogleDriveService
    │
List Child Folder
    │
Render Halaman
```

Tidak ada batasan jumlah level folder.

------------------------------------------------------------------------

# 7. Folder View

Folder ditampilkan sebagai daftar (table) agar mudah dibaca.

Kolom yang direkomendasikan:

  Kolom             Keterangan
  ----------------- -----------------------
  Nama              Nama folder/file
  Tipe              Folder / File
  Ukuran            File saja
  Terakhir Diubah   Metadata Google Drive
  Aksi              Open / Download

------------------------------------------------------------------------

# 8. Upload File

Alur upload:

``` text
Masuk Folder
      │
Klik Upload
      │
Pilih File
      │
Upload
      │
Google Drive
```

Upload selalu mengikuti folder yang sedang aktif.

------------------------------------------------------------------------

# 9. Create Folder

Alur:

``` text
Masuk Folder
      │
Klik Create Folder
      │
Isi Nama Folder
      │
Simpan
      │
Google Drive
```

Folder dibuat pada parent folder yang sedang dibuka.

------------------------------------------------------------------------

# 10. File Actions

Versi MVP:

-   Open Folder
-   Upload File
-   Download File
-   Refresh

Versi berikutnya:

-   Rename
-   Move
-   Delete
-   Preview

------------------------------------------------------------------------

# 11. Empty State

Jika folder kosong tampilkan:

``` text
📁

Folder ini belum memiliki dokumen.

[ Upload File ]
```

------------------------------------------------------------------------

# 12. Loading State

Saat membaca Google Drive tampilkan:

-   Spinner
-   Skeleton table

Hindari halaman kosong.

------------------------------------------------------------------------

# 13. Error State

Contoh:

-   Folder tidak ditemukan.
-   Gagal membaca Google Drive.
-   Upload gagal.
-   Koneksi terputus.

Seluruh pesan menggunakan bahasa Indonesia yang mudah dipahami.

------------------------------------------------------------------------

# 14. Integration

Drive Explorer menggunakan:

``` text
DriveController
      │
GoogleDriveService
      │
Google Drive API
```

Tidak mengakses Google API secara langsung dari Controller.

------------------------------------------------------------------------

# 15. Navigation From Monitoring

Jika pengguna menekan status merah pada Monitoring:

``` text
Monitoring
      │
Klik Status
      │
Drive Explorer
      │
Folder terkait langsung terbuka
```

Pengguna dapat langsung mengunggah dokumen tanpa mencari folder secara
manual.

------------------------------------------------------------------------

# 16. UI Guidelines

-   Tabel responsif.
-   Ikon folder dan file berbeda.
-   Gunakan warna biru untuk aksi utama.
-   Tombol Upload selalu terlihat.
-   Breadcrumb selalu tampil.

------------------------------------------------------------------------

# 17. Functional Requirements

-   Browse folder.
-   Open subfolder.
-   Create folder.
-   Upload file.
-   Download file.
-   Refresh data.
-   Tampilkan metadata.

------------------------------------------------------------------------

# 18. Summary

Drive Explorer adalah pusat aktivitas aplikasi.

Semua operasi terhadap dokumen dilakukan melalui modul ini sehingga
pengguna tidak perlu lagi membuka Google Drive secara langsung. Modul
ini harus sederhana, cepat, dan menyerupai pengalaman menggunakan file
manager modern dengan tetap mengikuti struktur folder Google Drive yang
sudah ada.
