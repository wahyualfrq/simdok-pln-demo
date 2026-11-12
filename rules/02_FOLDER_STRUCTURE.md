# 02_FOLDER_STRUCTURE.md

# Smart Document Automation Dashboard

## Folder Structure Specification

Version: 1.0

------------------------------------------------------------------------

# 1. Purpose

Dokumen ini menjadi standar struktur folder Google Drive yang digunakan
oleh seluruh modul aplikasi.

Seluruh fitur seperti Drive Explorer, Monitoring, dan Create Folder
wajib mengikuti struktur ini.

Google Drive adalah **Single Source of Truth**, sehingga struktur folder
harus konsisten.

------------------------------------------------------------------------

# 2. Design Principles

-   Struktur mudah dipahami.
-   Konsisten setiap semester.
-   Konsisten setiap bulan.
-   Tidak mengubah struktur yang telah digunakan pengguna.
-   Mudah dipindai oleh sistem.

------------------------------------------------------------------------

# 3. Hierarchy

``` text
Root Folder
│
├── Semester 1
│   ├── Januari
│   ├── Februari
│   ├── Maret
│   ├── April
│   ├── Mei
│   └── Juni
│
└── Semester 2
    ├── Juli
    ├── Agustus
    ├── September
    ├── Oktober
    ├── November
    └── Desember
```

------------------------------------------------------------------------

# 4. Month Structure

Setiap folder bulan memiliki struktur yang sama.

``` text
Januari
│
├── Unit Induk Distribusi S2JB
├── PLN UP3 PALEMBANG
├── PLN UP3 JAMBI
├── PLN UP3 MUARA BUNGO
├── PLN UP3 LUBUK LINGGAU
├── PLN UP3 LAHAT
├── PLN UP3 OGAN ILIR
├── PLN UP3 BENGKULU
├── PLN UP2D S2JB
├── PLN UP2K SUMSEL
├── PLN UP2K JAMBI
└── PLN UP2K BENGKULU
```

------------------------------------------------------------------------

# 5. Example Region Structure

Contoh:

``` text
PLN UP3 PALEMBANG
│
├── MUP3 PALEMBANG
├── TL LAKDAN
├── MULP KENTEN
├── MULP SUKARAMI
├── MULP RIVAI
├── MULP AMPERA
├── MULP MARIANA
├── MULP PANGKALAN BALAI
├── MULP SEKAYU
└── MULP SUNGAI LILIN
```

Setiap daerah memiliki struktur unit sesuai template organisasi.

------------------------------------------------------------------------

# 6. Folder Content

Folder unit merupakan lokasi penyimpanan dokumen.

Contoh:

``` text
MULP KENTEN
│
├── Dokumentasi-1.jpg
├── Dokumentasi-2.pdf
└── Dokumentasi-3.png
```

Status monitoring:

-   Folder memiliki ≥ 1 file → Hijau
-   Folder kosong → Merah

------------------------------------------------------------------------

# 7. Folder Naming Rules

Gunakan nama resmi organisasi.

Contoh:

-   PLN UP3 PALEMBANG
-   MULP KENTEN
-   SM DISTRIBUSI

Hindari:

-   nama singkatan yang tidak baku
-   karakter khusus
-   duplikasi nama

------------------------------------------------------------------------

# 8. Folder Creation

Create Folder dilakukan melalui website.

Alur:

``` text
Semester
   ↓
Bulan
   ↓
Generate Folder
   ↓
Google Drive
```

Website membuat seluruh struktur secara otomatis berdasarkan template.

------------------------------------------------------------------------

# 9. Folder Template

Template tidak disimpan di database.

Gunakan file konfigurasi Laravel.

``` text
config/
└── folder_template.php
```

Contoh:

``` php
return [
    'PLN UP3 PALEMBANG' => [
        'MUP3 PALEMBANG',
        'TL LAKDAN',
        'MULP KENTEN'
    ]
];
```

Perubahan struktur organisasi cukup dilakukan pada file konfigurasi.

------------------------------------------------------------------------

# 10. Monitoring Rules

Monitoring membaca struktur folder secara rekursif.

``` text
Semester
    ↓
Bulan
    ↓
Daerah
    ↓
Unit
    ↓
Jumlah File
```

Jika jumlah file lebih dari nol:

Status = Complete (Hijau)

Jika tidak ada file:

Status = Incomplete (Merah)

------------------------------------------------------------------------

# 11. Drive Explorer Rules

Drive Explorer wajib mendukung:

-   Navigasi folder tanpa batas
-   Breadcrumb
-   Upload ke folder aktif
-   Create Folder pada folder aktif
-   Refresh isi folder

Semua operasi dilakukan langsung pada Google Drive.

------------------------------------------------------------------------

# 12. Future Compatibility

Struktur ini harus tetap kompatibel apabila di masa depan ditambahkan:

-   Google Sheet Sync
-   Activity Log
-   Export Report
-   Search Engine
-   File Preview

Karena seluruh modul menggunakan struktur folder yang sama, tidak
diperlukan perubahan arsitektur.

------------------------------------------------------------------------

# 13. Summary

Folder Structure merupakan fondasi sistem.

Seluruh modul harus mengikuti hierarki berikut:

``` text
Semester
    ↓
Bulan
    ↓
Daerah
    ↓
Unit
    ↓
Dokumen
```

Tidak ada struktur alternatif atau penyimpanan ganda. Google Drive tetap
menjadi sumber data utama yang digunakan oleh seluruh aplikasi.
