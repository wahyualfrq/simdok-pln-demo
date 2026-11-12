<?php

// DEMO: Konfigurasi tunggal versi DEMO (portofolio). Semua angka & teks demo
// ditaruh di sini agar tidak ada magic number di kode.
// Saat DEMO_MODE=false, aplikasi berperilaku persis seperti aslinya.

return [

    /*
    |--------------------------------------------------------------------------
    | Flag Mode Demo
    |--------------------------------------------------------------------------
    |
    | Satu-satunya saklar perilaku demo. Baca lewat config('demo.enabled').
    |
    */

    'enabled' => (bool) env('DEMO_MODE', false),

    /*
    |--------------------------------------------------------------------------
    | Akun Demo (publik, memang untuk demonstrasi)
    |--------------------------------------------------------------------------
    */

    'name'     => 'Pengguna Demo',
    'username' => env('DEMO_USERNAME', 'demo'),
    'password' => env('DEMO_PASSWORD', 'demo12345'),
    'email'    => 'demo.user@example.com',

    /*
    |--------------------------------------------------------------------------
    | Google Drive & Sheet Simulasi
    |--------------------------------------------------------------------------
    */

    'root_folder_id'   => 'demo_root_0001',
    'root_folder_name' => 'SIMDOK DEMO — Dokumen Bulanan',
    'spreadsheet_id'   => 'demo_sheet_0001',

    /*
    |--------------------------------------------------------------------------
    | Pohon Google Drive Dummy (DemoDriveTree)
    |--------------------------------------------------------------------------
    |
    | Pohon dibangun deterministik di kode dari config/folder_structure.php
    | (nama region/unit resmi) + daftar bulan di bawah. Nama file, ukuran, dan
    | tanggal murni dummy. Tidak membaca data/ maupun Google.
    |
    */

    'drive' => [
        // Folder bulan di bawah Root (nama mengikuti pola asli, mis. "12. DES 2025").
        'months' => [
            ['name' => '12. DES 2025',  'period' => '2025-12'],
            ['name' => '1. JAN 2026',   'period' => '2026-01'],
            ['name' => '2. FEB 2026',   'period' => '2026-02'],
            ['name' => '3. MAR 2026',   'period' => '2026-03'],
            ['name' => '4. APR 2026',   'period' => '2026-04'],
            ['name' => '5. MEI 2026',   'period' => '2026-05'],
            ['name' => '6. JUNI 2026',  'period' => '2026-06'],
            ['name' => '7. JULI 2026',  'period' => '2026-07'],
            // Bulan berjalan: kelengkapan unit menurun mendekati akhir (fill_percent per bulan).
            ['name' => '8. AGUSTUS 2026',   'period' => '2026-08', 'fill_percent' => 80],
            ['name' => '9. SEPTEMBER 2026', 'period' => '2026-09', 'fill_percent' => 75],
            ['name' => '10. OKTOBER 2026',  'period' => '2026-10', 'fill_percent' => 60],
            ['name' => '11. NOVEMBER 2026', 'period' => '2026-11', 'fill_percent' => 30],
        ],

        // Nama folder region di Drive bila berbeda dari nama kanonikal config/folder_structure.php.
        'region_folder_names' => [
            'UNIT INDUK DISTRIBUSI S2JB' => 'KANTOR UID',
        ],

        // Folder lain di "Drive Saya" agar daftar Root Folder punya pilihan.
        'decoy_folders' => ['Arsip Dokumen (Demo)', 'Template Laporan (Demo)'],

        'spreadsheet_name'    => 'Monitoring_Template_Demo.xlsx',
        'spreadsheet_size'    => 81920,
        'root_created_at'     => '2025-11-15T08:00:00.000Z',
        'fill_percent'        => 78,       // % unit yang sudah punya file (bisa ditimpa per bulan di 'months')
        'pending_percent'     => 12,       // % unit berisi file yang BELUM difinalisasi (checklist kosong → Pending)
        'files_per_leaf'      => [1, 3],   // min, maks file per unit yang terisi
        'file_size_kb'        => [48, 900], // min, maks ukuran file dummy
        'overlay_ttl_hours'   => 24,       // berkas overlay mutasi lebih lama dari ini dihapus
    ],

    /*
    |--------------------------------------------------------------------------
    | Durasi Simulasi (detik) & Batas Upload
    |--------------------------------------------------------------------------
    */

    'sync_flip_count'           => 4,    // unit merah → hijau per sinkronisasi (deterministik, dari bulan terbaru)
    'sync_duration_seconds'     => 12,
    'generate_duration_seconds' => 6,
    'upload_max_kb'             => 4096, // batas body fungsi Vercel ±4,5 MB

    /*
    |--------------------------------------------------------------------------
    | PDF Demo (DemoPdfConverter)
    |--------------------------------------------------------------------------
    | Runtime demo memakai PDF STATIS (resources/demo/pdf/monitoring_semester_{1|2}.pdf); hanya
    | animasi konversi yang disimulasikan lewat jeda fake_delay_ms.
    | Parameter lain dipakai renderWithDompdf() (pembuatan ulang PDF statis): skala fit-to-width
    | = fit_target × (lebar halaman − margin kiri/kanan); font_trim / row_height_trim = pengali
    | tambahan (metrik Helvetica dompdf ≠ font template).
    */

    'pdf' => [
        'fake_delay_ms'          => 1200,
        'margin_left_right_inch' => 0.3,
        'margin_top_bottom_inch' => 0.35,
        'fit_target'             => 0.94,
        'font_trim'              => 0.93,
        'row_height_trim'        => 0.93,
    ],

    /*
    |--------------------------------------------------------------------------
    | Teks Catatan "Demonstrasi"
    |--------------------------------------------------------------------------
    */

    'banner' => [
        'title'   => 'MODE DEMONSTRASI',
        'message' => 'Ini versi demo untuk portofolio. Data bersifat dummy, integrasi Google Drive disimulasikan, dan aplikasi ini bukan sistem resmi PT PLN. Perubahan data bersifat sementara.',
    ],

    'footer_label' => 'Versi Demo',

    /*
    |--------------------------------------------------------------------------
    | Footer Laporan PDF/Excel (dummy) — di-seed ke tabel settings oleh DemoSeeder
    |--------------------------------------------------------------------------
    */

    'footer' => [
        'lokasi'           => 'Palembang',
        'tanggal'          => '',
        'disusun_oleh'     => 'Nama Penyusun (Demo)',
        'jabatan_penyusun' => 'JABATAN PENYUSUN (DEMO)',
        'mengetahui'       => 'Nama Pejabat (Demo)',
        'jabatan'          => 'JABATAN PEJABAT (DEMO)',
    ],

];
