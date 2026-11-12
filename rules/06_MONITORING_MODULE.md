# 06_MONITORING_MODULE.md

# Smart Document Automation Dashboard

## Monitoring Module Specification

Version: 1.0

------------------------------------------------------------------------

# 1. Purpose

Monitoring adalah modul utama yang membantu administrator mengetahui
status kelengkapan dokumen tanpa harus membuka Google Drive satu per
satu.

Monitoring hanya **membaca** data dari Google Drive dan **tidak
mengubah** Google Sheet maupun struktur folder.

------------------------------------------------------------------------

# 2. Objectives

Monitoring harus mampu:

-   Menampilkan progres upload secara visual.
-   Menunjukkan unit yang belum upload.
-   Mengurangi proses pengecekan manual.
-   Menjadi titik awal menuju Drive Explorer.

------------------------------------------------------------------------

# 3. Business Rules

## Rule 1

Folder unit memiliki minimal 1 file.

Status:

🟢 Complete

------------------------------------------------------------------------

## Rule 2

Folder unit kosong.

Status:

🔴 Incomplete

------------------------------------------------------------------------

## Rule 3

Monitoring selalu membaca kondisi terbaru dari Google Drive.

Tidak menggunakan cache bisnis maupun database lokal.

------------------------------------------------------------------------

# 4. Monitoring Scope

Monitoring dilakukan berdasarkan:

-   Semester
-   Bulan
-   Daerah
-   Unit

Hierarki:

``` text
Semester
    ↓
Bulan
    ↓
Daerah
    ↓
Unit
    ↓
Status
```

------------------------------------------------------------------------

# 5. Monitoring Flow

``` text
User
    │
Monitoring Page
    │
MonitoringController
    │
MonitoringService
    │
GoogleDriveService
    │
Google Drive API
    │
Render Status
```

------------------------------------------------------------------------

# 6. Dashboard Summary

Dashboard Monitoring menampilkan:

-   Total Unit
-   Sudah Upload
-   Belum Upload
-   Persentase Progress

Contoh:

  Informasi        Nilai
  -------------- -------
  Total Unit          87
  Sudah Upload        75
  Belum Upload        12
  Progress           86%

------------------------------------------------------------------------

# 7. Monitoring Table

Contoh tampilan:

  Unit             Jan   Feb   Mar   Apr   Mei   Jun
  --------------- ----- ----- ----- ----- ----- -----
  GM               🟢    🔴    🟢    🟢    🟢    🔴
  SM DISTRIBUSI    🟢    🟢    🟢    🔴    🟢    🟢
  MULP KENTEN      🔴    🟢    🟢    🟢    🟢    🟢

------------------------------------------------------------------------

# 8. Color Rules

Hijau

-   Folder memiliki dokumen.

Merah

-   Folder kosong.

Gunakan warna yang konsisten di seluruh aplikasi.

------------------------------------------------------------------------

# 9. Filter

Minimal filter:

-   Semester
-   Bulan
-   Daerah

Filter bekerja tanpa mengubah data di Google Drive.

------------------------------------------------------------------------

# 10. Progress Calculation

Rumus:

``` text
Progress (%) =
(Jumlah Unit Complete / Total Unit) × 100
```

Progress dihitung setiap proses monitoring dijalankan.

------------------------------------------------------------------------

# 11. Navigation

Klik status merah:

``` text
Monitoring
      │
Klik Sel
      │
Drive Explorer
      │
Folder Tujuan
```

Tujuannya agar pengguna dapat langsung mengunggah dokumen.

------------------------------------------------------------------------

# 12. Refresh Strategy

Monitoring memiliki tombol:

    Refresh Monitoring

Saat ditekan:

1.  Membaca ulang Google Drive.
2.  Menghitung ulang status.
3.  Memperbarui tampilan.

Tidak ada penyimpanan hasil monitoring di database.

------------------------------------------------------------------------

# 13. Error Handling

Tangani kondisi berikut:

-   Folder tidak ditemukan.
-   Google Drive tidak dapat diakses.
-   Struktur folder tidak sesuai.
-   Permission ditolak.

Berikan pesan yang jelas kepada pengguna.

------------------------------------------------------------------------

# 14. Performance

-   Gunakan Service Layer.
-   Ambil metadata seperlunya.
-   Hindari request berulang pada folder yang sama.
-   Pisahkan proses browsing dan monitoring.

------------------------------------------------------------------------

# 15. Functional Requirements

Monitoring harus dapat:

-   Membaca struktur folder.
-   Menghitung jumlah file.
-   Menentukan status.
-   Menghitung progres.
-   Memfilter data.
-   Menavigasi ke Drive Explorer.

------------------------------------------------------------------------

# 16. Future Enhancement

Versi berikutnya dapat menambahkan:

-   Last Upload Time
-   Jumlah File
-   Nama uploader (jika tersedia)
-   Export PDF
-   Export Excel
-   Grafik progres

------------------------------------------------------------------------

# 17. Summary

Monitoring merupakan inti aplikasi. Modul ini memberikan gambaran
kondisi dokumen secara real-time dengan membaca Google Drive secara
langsung. Hasil monitoring bersifat informatif dan tidak mengubah Google
Drive maupun Google Sheet. Pengguna dapat segera mengetahui unit yang
belum mengunggah dokumen dan langsung diarahkan ke Drive Explorer untuk
melakukan tindakan.

Dokumen ini menjelaskan konsep umum Monitoring. Implementasi teknis terbaru mengacu pada 11_MONITORING_GOOGLE_SHEET.md, yang menjadi spesifikasi utama modul Monitoring.
