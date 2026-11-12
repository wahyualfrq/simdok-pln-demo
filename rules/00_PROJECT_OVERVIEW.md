# 00_PROJECT_OVERVIEW.md

# Smart Document Automation Dashboard

Version: 1.0

------------------------------------------------------------------------

# 1. Executive Summary

Smart Document Automation Dashboard adalah aplikasi web internal
berbasis Laravel yang bertujuan mempermudah proses pengelolaan dokumen
di lingkungan PLN tanpa mengubah alur kerja yang telah berjalan.

Aplikasi ini tidak menggantikan Google Drive maupun Google Sheet.
Sebaliknya, aplikasi menjadi portal tunggal (single portal) untuk
membuat folder, mengunggah dokumen, menelusuri folder, dan memonitor
kelengkapan dokumen yang tersimpan di Google Drive.

Google Drive tetap menjadi sumber data utama (Single Source of Truth),
sedangkan Google Sheet tetap digunakan sebagai media pelaporan resmi.

------------------------------------------------------------------------

# 2. Background

Saat ini proses pengumpulan dokumen dilakukan menggunakan Google Drive.
Setiap bulan setiap Unit mengunggah dokumen ke folder yang telah
ditentukan.

Setelah proses upload selesai, petugas harus:

1.  Membuka Google Drive.
2.  Masuk ke folder bulan.
3.  Membuka setiap folder daerah.
4.  Membuka setiap folder unit.
5.  Memastikan folder telah berisi dokumen.
6.  Berpindah ke Google Sheet.
7.  Memberikan checklist secara manual.

Semakin banyak unit, semakin lama waktu yang dibutuhkan.

------------------------------------------------------------------------

# 3. Existing Workflow

Unit ↓ Upload File ↓ Google Drive ↓ Petugas membuka folder satu per satu
↓ Mengecek isi folder ↓ Membuka Google Sheet ↓ Checklist manual

Permasalahan:

-   Membuka ratusan folder setiap bulan.
-   Monitoring belum terpusat.
-   Upload dilakukan langsung di Google Drive.
-   Pembuatan folder dilakukan manual.
-   Sulit mengetahui unit yang belum upload.

------------------------------------------------------------------------

# 4. Proposed Solution

Membangun website internal bernama Smart Document Automation Dashboard.

Website menjadi portal utama untuk:

-   membuat folder
-   upload dokumen
-   menjelajahi Google Drive
-   monitoring status upload

Semua data tetap berada di Google Drive.

Google Sheet tetap dipakai sebagai laporan resmi.

------------------------------------------------------------------------

# 5. Objectives

-   Mengurangi pekerjaan manual.
-   Mempercepat monitoring.
-   Mempermudah upload dokumen.
-   Mempermudah pembuatan folder.
-   Menampilkan status upload secara visual.
-   Tidak mengubah SOP yang berjalan.

------------------------------------------------------------------------

# 6. Project Scope

## In Scope

-   Dashboard
-   Drive Explorer
-   Create Folder
-   Upload File
-   Browse Folder
-   Monitoring
-   Progress Semester
-   Progress Bulan
-   Status per Unit

## Out of Scope

-   Manajemen User
-   Role Permission
-   Workflow Approval
-   Digital Signature
-   Email Notification
-   OCR
-   AI Classification

------------------------------------------------------------------------

# 7. User

Administrator Internal PLN.

Pengguna bertugas:

-   membuat folder
-   upload dokumen
-   memonitor upload
-   memastikan kelengkapan dokumen

------------------------------------------------------------------------

# 8. Business Process

1.  Admin memilih Semester.
2.  Admin memilih Bulan.
3.  Sistem membuat struktur folder Google Drive.
4.  User mengunggah dokumen melalui website.
5.  File otomatis masuk ke folder Google Drive.
6.  Monitoring membaca Google Drive.
7.  Dashboard menampilkan status hijau atau merah.

------------------------------------------------------------------------

# 9. Main Features

## Dashboard

Menampilkan:

-   Total Unit
-   Sudah Upload
-   Belum Upload
-   Progress Semester
-   Progress Bulan

## Drive Explorer

-   Browse Folder
-   Breadcrumb
-   Upload File
-   Create Folder
-   Download File

## Monitoring

-   Status Upload
-   Warna Hijau
-   Warna Merah
-   Klik status menuju folder terkait

------------------------------------------------------------------------

# 10. Technology Stack

Backend - Laravel

Frontend - Blade - Tailwind CSS

Storage - Google Drive

Report - Google Sheet

Authentication Google - Service Account

------------------------------------------------------------------------

# 11. System Principles

-   Google Drive adalah Single Source of Truth.
-   Tidak menyimpan salinan data dokumen di database.
-   Tidak mengubah struktur Google Sheet.
-   Semua operasi Google Drive dilakukan melalui Service Layer.
-   Clean Architecture.
-   SOLID Principle.

------------------------------------------------------------------------

# 12. MVP

Versi pertama harus memiliki:

-   Dashboard
-   Drive Explorer
-   Monitoring
-   Create Folder
-   Upload File
-   Integrasi Google Drive

------------------------------------------------------------------------

# 13. Future Development

-   Sinkronisasi otomatis Google Sheet.
-   Export PDF.
-   Pencarian dokumen.
-   Preview dokumen.
-   Riwayat aktivitas.
-   Statistik per wilayah.

------------------------------------------------------------------------

# 14. Success Criteria

Proyek dianggap berhasil apabila:

-   Admin tidak perlu membuka Google Drive untuk membuat folder.
-   Admin dapat upload dari website.
-   Admin dapat melihat status upload dalam satu dashboard.
-   Monitoring mampu menunjukkan unit yang belum upload tanpa pengecekan
    manual.
-   Google Drive tetap menjadi penyimpanan utama.
-   Google Sheet tetap dapat digunakan tanpa perubahan format.

------------------------------------------------------------------------

# 15. Vision

Smart Document Automation Dashboard bukan pengganti Google Workspace,
tetapi menjadi portal kerja yang menyederhanakan proses operasional
dengan tetap mempertahankan Google Drive sebagai penyimpanan dokumen dan
Google Sheet sebagai media pelaporan resmi.
