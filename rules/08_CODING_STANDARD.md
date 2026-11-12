# 08_CODING_STANDARD.md

# Smart Document Automation Dashboard

## Laravel Coding Standard

Version: 1.0

------------------------------------------------------------------------

# 1. Purpose

Dokumen ini menjadi standar implementasi source code agar seluruh
pengembangan memiliki struktur yang konsisten, mudah dipelihara, dan
siap dikembangkan.

------------------------------------------------------------------------

# 2. Technology Stack

-   Laravel
-   Blade
-   Tailwind CSS
-   Google Drive API
-   SQLite (internal Laravel)

Tidak menggunakan:

-   React
-   Vue
-   Livewire
-   Inertia
-   Repository Pattern (belum diperlukan untuk MVP)

------------------------------------------------------------------------

# 3. Architecture

``` text
Route
  │
Controller
  │
Service
  │
Google Drive API
```

Business logic **wajib** berada di Service.

------------------------------------------------------------------------

# 4. Folder Structure

``` text
app/
├── Http/
│   ├── Controllers/
│   └── Requests/
├── Services/
├── Support/
└── View/
```

------------------------------------------------------------------------

# 5. Controller Rules

Controller hanya bertugas:

-   menerima request
-   validasi
-   memanggil service
-   mengembalikan response

Tidak boleh:

-   query Google API
-   business logic
-   manipulasi data kompleks

------------------------------------------------------------------------

# 6. Service Rules

Setiap service memiliki satu tanggung jawab.

Contoh:

-   GoogleDriveService
-   MonitoringService

Service dapat dipanggil oleh beberapa controller.

------------------------------------------------------------------------

# 7. Validation

Gunakan Form Request.

``` text
app/Http/Requests/
```

Jangan melakukan validasi langsung di controller.

------------------------------------------------------------------------

# 8. Blade Rules

Gunakan Blade Components untuk elemen yang berulang.

Contoh:

``` text
resources/views/components/

card.blade.php
button.blade.php
badge.blade.php
table.blade.php
modal.blade.php
```

------------------------------------------------------------------------

# 9. Tailwind Rules

-   Utility-first.
-   Tidak menggunakan inline style.
-   Tidak menggunakan CSS berlebihan.
-   Komponen harus konsisten.

------------------------------------------------------------------------

# 10. Naming Convention

Controller

    DriveController
    MonitoringController
    DashboardController

Service

    GoogleDriveService
    MonitoringService

Request

    UploadFileRequest
    CreateFolderRequest

------------------------------------------------------------------------

# 11. Routes

Gunakan route yang jelas.

``` text
/dashboard
/drive
/drive/{folder}
/monitoring
```

Hindari route yang panjang dan ambigu.

------------------------------------------------------------------------

# 12. Error Handling

Gunakan:

-   try/catch
-   Laravel Log
-   Flash message

Jangan menampilkan exception mentah ke pengguna.

------------------------------------------------------------------------

# 13. Response Standard

Semua operasi harus mengembalikan:

-   Success
-   Error Message
-   Redirect yang jelas

------------------------------------------------------------------------

# 14. File Upload Rules

-   Validasi ukuran file.
-   Validasi tipe file.
-   Simpan langsung ke Google Drive.
-   Tidak menyimpan file di server.

------------------------------------------------------------------------

# 15. Code Quality

-   SOLID
-   DRY
-   KISS
-   Readable Code

Prioritaskan keterbacaan dibanding optimasi prematur.

------------------------------------------------------------------------

# 16. Git Convention

Branch:

-   main
-   develop
-   feature/`<nama-fitur>`{=html}

Commit:

    feat:
    fix:
    refactor:
    docs:
    style:

------------------------------------------------------------------------

# 17. Documentation

Setiap service wajib memiliki PHPDoc singkat.

Method penting harus memiliki deskripsi parameter dan return.

------------------------------------------------------------------------

# 18. Future Standard

Saat aplikasi berkembang dapat ditambahkan:

-   Unit Test
-   Feature Test
-   CI/CD
-   Static Analysis
-   Laravel Pint

------------------------------------------------------------------------

# 19. Summary

Standar coding ini memastikan seluruh implementasi Laravel tetap
sederhana, konsisten, dan sesuai dengan arsitektur Smart Document
Automation Dashboard. Fokus utama adalah menjaga controller tetap tipis,
seluruh logika bisnis berada di Service Layer, dan semua interaksi
dengan Google Drive dilakukan melalui `GoogleDriveService`.
