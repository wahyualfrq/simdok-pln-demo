# 03_DATABASE_STRATEGY.md

# Smart Document Automation Dashboard

## Database Strategy

Version: 1.0

------------------------------------------------------------------------

# 1. Purpose

Dokumen ini menjelaskan strategi penyimpanan data yang digunakan pada
Smart Document Automation Dashboard.

Berbeda dengan aplikasi web pada umumnya, sistem ini **tidak menggunakan
database relasional sebagai sumber data utama**. Seluruh data bisnis
berasal dari Google Drive.

------------------------------------------------------------------------

# 2. Philosophy

Prinsip utama proyek adalah:

> **Google Drive merupakan Single Source of Truth.**

Website tidak menyimpan salinan struktur folder maupun metadata dokumen
ke dalam database.

Website hanya membaca dan menampilkan informasi yang tersedia di Google
Drive.

------------------------------------------------------------------------

# 3. Why Not MySQL?

Pada umumnya aplikasi menggunakan database seperti MySQL atau PostgreSQL
untuk menyimpan:

-   Struktur folder
-   Daftar file
-   Status upload
-   Relasi antar unit

Pada proyek ini pendekatan tersebut tidak digunakan karena:

-   Data sudah tersedia di Google Drive.
-   Akan terjadi duplikasi data.
-   Berpotensi menimbulkan inkonsistensi.
-   Membutuhkan sinkronisasi tambahan.
-   Menambah kompleksitas tanpa manfaat yang signifikan.

------------------------------------------------------------------------

# 4. Source of Truth

Seluruh informasi berasal dari Google Drive.

Contoh:

``` text
Semester 1
└── Januari
    └── PLN UP3 PALEMBANG
        └── MULP KENTEN
            ├── Foto 1.jpg
            └── Bukti.pdf
```

Monitoring memperoleh status langsung dari struktur tersebut.

Tidak ada tabel database yang menyimpan status upload.

------------------------------------------------------------------------

# 5. Business Data

Data bisnis yang dibaca dari Google Drive meliputi:

-   Struktur semester
-   Struktur bulan
-   Struktur daerah
-   Struktur unit
-   Daftar file
-   Jumlah file
-   Waktu modifikasi
-   Nama folder

Semua dibaca secara real-time melalui Google Drive API.

------------------------------------------------------------------------

# 6. Role of SQLite

SQLite tetap digunakan oleh Laravel, tetapi **bukan sebagai database
bisnis**.

Fungsinya hanya untuk:

-   Session
-   Cache
-   Queue
-   Laravel internal feature

SQLite tidak menyimpan:

-   Folder
-   Dokumen
-   Status upload
-   Struktur organisasi

------------------------------------------------------------------------

# 7. Read Strategy

Setiap halaman membaca data langsung dari Google Drive.

``` text
User
   │
   ▼
Laravel
   │
Monitoring Service
   │
Google Drive Service
   │
Google Drive API
   │
Google Drive
```

Website selalu menampilkan data terbaru.

------------------------------------------------------------------------

# 8. Write Strategy

Operasi tulis dilakukan langsung ke Google Drive.

Contoh:

## Create Folder

``` text
Website
    │
    ▼
GoogleDriveService
    │
    ▼
Google Drive
```

## Upload File

``` text
Website
    │
    ▼
GoogleDriveService
    │
    ▼
Google Drive
```

Tidak ada proses penyimpanan metadata ke database lokal.

------------------------------------------------------------------------

# 9. Monitoring Strategy

Monitoring tidak menggunakan tabel status.

Logika yang digunakan:

-   Folder memiliki minimal satu file → Complete
-   Folder kosong → Incomplete

Status dihitung setiap kali halaman dibuka atau saat pengguna melakukan
refresh.

------------------------------------------------------------------------

# 10. Data Consistency

Karena hanya ada satu sumber data:

-   Tidak ada konflik sinkronisasi.
-   Tidak ada data ganda.
-   Tidak ada proses import atau export data.

Semua modul membaca sumber yang sama.

------------------------------------------------------------------------

# 11. Advantages

Pendekatan ini memberikan beberapa keuntungan:

-   Arsitektur sederhana.
-   Tidak ada duplikasi data.
-   Mudah dipelihara.
-   Mudah dikembangkan.
-   Selalu menampilkan kondisi terbaru.
-   Mengikuti SOP yang sudah berjalan.

------------------------------------------------------------------------

# 12. Limitations

Beberapa konsekuensi dari pendekatan ini:

-   Membutuhkan koneksi ke Google Drive.
-   Performa bergantung pada Google Drive API.
-   Monitoring dilakukan secara real-time.
-   Struktur folder harus konsisten.

Semua keterbatasan tersebut dapat diterima karena sesuai dengan
kebutuhan aplikasi internal.

------------------------------------------------------------------------

# 13. Future Strategy

Jika di masa depan dibutuhkan fitur:

-   Activity Log
-   Statistik
-   Dashboard historis

Maka database lokal dapat digunakan sebagai **cache** atau **analytics
database**, bukan menggantikan Google Drive sebagai sumber data utama.

------------------------------------------------------------------------

# 14. Summary

Strategi penyimpanan data proyek ini dapat diringkas sebagai berikut.

  Komponen       Fungsi
  -------------- --------------------------------------
  Google Drive   Sumber data utama (folder & dokumen)
  Google Sheet   Laporan resmi yang sudah berjalan
  Laravel        Business logic & integrasi
  SQLite         Kebutuhan internal Laravel

Dengan pendekatan ini, aplikasi tetap sederhana, tidak mengubah proses
kerja pengguna, dan menghindari duplikasi data.
