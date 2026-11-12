<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SIMDOK Excel Report Template Configuration (Single Source of Truth)
    |--------------------------------------------------------------------------
    |
    | File template Excel ini adalah SATU-SATUNYA sumber layout laporan.
    | Seluruh penataan sel, merge cell, border, warna, font, dan posisi
    | tanda tangan ditentukan dari file Excel ini.
    |
    */

    'template_path' => resource_path('templates/Monitoring_Template.xlsx'),

    'temp_dir' => storage_path('app/temp'),

    /*
    |--------------------------------------------------------------------------
    | Cell Mapping Data Monitoring
    |--------------------------------------------------------------------------
    */
    'unit_col' => 'D',
    'row_start' => 6,
    'row_end'   => 80,

    'month_columns' => [
        'Semester 1' => [
            'Desember'  => 'E',
            'Januari'   => 'F',
            'Februari'  => 'G',
            'Maret'     => 'H',
            'April'     => 'I',
            'Mei'       => 'J',
        ],
        'Semester 2' => [
            'Juni'      => 'E',
            'Juli'      => 'F',
            'Agustus'   => 'G',
            'September' => 'H',
            'Oktober'   => 'I',
            'November'  => 'J',
        ],
    ],

    'status_symbols' => [
        'complete'   => '✓',
        'mismatch'   => '!',
        'incomplete' => '-',
    ],

    /*
    |--------------------------------------------------------------------------
    | Cell Mapping Footer Tanda Tangan
    |--------------------------------------------------------------------------
    */
    'footer_cells' => [
        'lokasi_tanggal'     => 'H82',
        'jabatan_penyusun'   => 'C84',
        'jabatan_mengetahui' => 'H84',
        'nama_penyusun'      => 'C90',
        'nama_mengetahui'    => 'H90',
    ],
];
