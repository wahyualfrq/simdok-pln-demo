# 11_MONITORING_GOOGLE_SHEET.md

# Smart Document Automation Dashboard

## Monitoring Google Sheet Specification

Version: 1.0

------------------------------------------------------------------------

## 1. Purpose

Dokumen ini menjadi spesifikasi implementasi Monitoring terbaru.

Mulai versi ini:

-   Monitoring **tidak melakukan scan Google Drive** untuk menentukan
    status.
-   Google Drive tetap menjadi tempat penyimpanan dokumen.
-   Google Sheet menjadi sumber data Monitoring.
-   Website hanya menampilkan dashboard yang lebih modern.

------------------------------------------------------------------------

## 2. Arsitektur Baru

``` text
User Upload
    │
    ▼
Google Drive
    │
    ▼
Auto Update Google Sheet
    │
    ▼
Monitoring Website
```

Monitoring membaca **Google Sheet** secara real-time.

------------------------------------------------------------------------

## 3. Source of Truth

  Modul            Sumber Data
  ---------------- --------------
  Drive Explorer   Google Drive
  Upload           Google Drive
  Monitoring       Google Sheet
  Laporan          Google Sheet

------------------------------------------------------------------------

## 4. Monitoring Flow

1.  User memilih Semester.
2.  Monitoring membaca Google Sheet.
3.  Mapping kolom bulan.
4.  Mapping baris unit.
5.  Render tabel.
6.  Klik status merah membuka Drive Explorer.

------------------------------------------------------------------------

## 5. Semester Mapping

### Semester 1

-   Januari
-   Februari
-   Maret
-   April
-   Mei
-   Juni

### Semester 2

-   Juli
-   Agustus
-   September
-   Oktober
-   November
-   Desember

Jika urutan pada Google Sheet berbeda, lakukan mapping di
`MonitoringService`, bukan mengubah tampilan UI.

------------------------------------------------------------------------

## 6. Tampilan Monitoring

``` text
Semester
[ Semester 1 ▼ ]

----------------------------------------------------------

Nama Unit        Jan   Feb   Mar   Apr   Mei   Jun

GM               🟢    🔴    🟢    🟢    🟢    🔴
SM Distribusi    🟢    🟢    🟢    🔴    🟢    🟢
MULP Kenten      🔴    🟢    🟢    🟢    🟢    🟢
```

------------------------------------------------------------------------

## 7. Aturan Status

  Nilai Google Sheet   Status
  -------------------- ------------
  ✓ / V / TRUE         Complete
  Kosong               Incomplete

UI:

-   Complete → Hijau
-   Incomplete → Merah

------------------------------------------------------------------------

## 8. Warna

Complete

-   Background: #DCFCE7

Incomplete

-   Background: #FEE2E2

------------------------------------------------------------------------

## 9. Interaksi

Klik status merah:

``` text
Monitoring
    ↓
Drive Explorer
    ↓
Folder terkait
```

------------------------------------------------------------------------

## 10. Dashboard Summary

Tampilkan:

-   Total Unit
-   Complete
-   Incomplete
-   Progress (%)

Progress dihitung dari Google Sheet.

------------------------------------------------------------------------

## 11. Service Layer

`MonitoringService` bertugas:

-   membaca Google Sheet
-   mapping unit
-   mapping bulan
-   menghitung progress
-   menyiapkan data untuk Blade

Controller hanya memanggil Service.

------------------------------------------------------------------------

## 12. Definition of Done

-   Membaca Google Sheet.
-   Menampilkan checklist.
-   Warna hijau/merah sesuai status.
-   Tidak mengubah format Google Sheet.
-   Dapat membuka Drive Explorer.

------------------------------------------------------------------------

## 13. Summary

Monitoring merupakan representasi visual Google Sheet resmi PLN sehingga
pengguna tidak perlu lagi membuka Google Sheet untuk melihat progres
kelengkapan dokumen.
