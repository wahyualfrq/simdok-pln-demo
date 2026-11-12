<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | PLN Official Folder Structure Template (Single Source of Truth)
    |--------------------------------------------------------------------------
    |
    | Struktur hirarki resmi folder Google Drive PLN UID S2JB.
    | Digunakan oleh modul: Auto Generate Folder, Monitoring, Upload,
    | Folder Resolver, Export PDF, dan Export Excel.
    |
    */

    'parents' => [
        'UNIT INDUK DISTRIBUSI S2JB' => [
            'has_subfolder' => false,
            'mode'          => 'file',
            'aliases'       => ['KANTOR UID', 'K UID', 'UID', 'KANTOR'],
            'units'         => [
                'GM',
                'SM PERENCANAAN',
                'SM DISTRIBUSI',
                'SM NIAGA DAN MANJ PELANGGAN',
                'SM KKU',
                'PJ REN DAN',
                'MAN LAKSDA',
            ],
        ],

        'PLN UP3 PALEMBANG' => [
            'has_subfolder' => true,
            'mode'          => 'folder',
            'aliases'       => ['UP3 PALEMBANG', 'PALEMBANG'],
            'units'         => [
                'MUP3 PALEMBANG',
                'TL LAKDAN',
                'a. MULP KENTEN',
                'b. MULP SUKARAMI',
                'c. MULP RIVAI',
                'd. MULP AMPERA',
                'e. MULP MARIANA',
                'f. MULP PANGKALAN BALAI',
                'g. MULP SEKAYU',
                'h. MULP SUNGAI LILIN',
            ],
        ],

        'PLN UP3 JAMBI' => [
            'has_subfolder' => true,
            'mode'          => 'folder',
            'aliases'       => ['UP3 JAMBI', 'JAMBI'],
            'units'         => [
                'MUP3 JAMBI',
                'MULP TELANAIPURA',
                'MULP KOTA BARU',
                'MULP SEBERANG KOTA',
                'MULP KUALA TUNGKAL',
                'MULP MUARA BULIAN',
            ],
        ],

        'PLN UP3 MUARA BUNGO' => [
            'has_subfolder' => true,
            'mode'          => 'folder',
            'aliases'       => ['UP3 MUARA BUNGO', 'MUARA BUNGO', 'MBO'],
            'units'         => [
                'MULP BANGKO',
                'MULP KOTA BUNGO',
                'MULP KERSIK TUO',
                'MULP RIMBO BUJANG',
                'MULP SUNGAI PENUH',
                'MULP SAROLANGUN',
                'MULP MUARA TEBO',
            ],
        ],

        'PLN UP3 LUBUK LINGGAU' => [
            'has_subfolder' => true,
            'mode'          => 'folder',
            'aliases'       => ['UP3 LUBUK LINGGAU', 'LUBUKLINGGAU', 'LLG'],
            'units'         => [
                'MUP3 LUBUKLINGGAU',
                'a. MULP MUARA BELITI',
                'b. MULP MURATARA',
                'c. MULP PENDOPO',
                'd. MULP TEBING TINGGI',
                'e. MULP LUBUKLINGGAU',
            ],
        ],

        'PLN UP3 LAHAT' => [
            'has_subfolder' => true,
            'mode'          => 'folder',
            'aliases'       => ['UP3 LAHAT', 'LAHAT'],
            'units'         => [
                'MULP BATURAJA',
                'MULP LEMBAYUNG',
                'MULP MUARADUA',
                'MULP MUARA ENIM',
                'MULP MARTAPURA',
                'MULP PAGAR ALAM',
            ],
        ],

        'PLN UP3 OGAN ILIR' => [
            'has_subfolder' => true,
            'mode'          => 'folder',
            'aliases'       => ['UP3 OGAN ILIR', 'OGAN ILIR', 'OGI'],
            'units'         => [
                'MUP3 OGAN ILIR',
                'MULP INDRALAYA',
                'MULP KAYU AGUNG',
                'MULP PRABUMULIH',
                'MULP TUGU MULYO',
            ],
        ],

        'PLN UP3 BENGKULU' => [
            'has_subfolder' => true,
            'mode'          => 'folder',
            'aliases'       => ['UP3 BENGKULU', 'BENGKULU'],
            'units'         => [
                'MUP3 BENGKULU',
                'MULP TELUK SEGARA',
                'MULP NALA',
                'MULP ARGA MAKMUR',
                'MULP CURUP',
                'MULP MANNA',
                'MULP MUKOMUKO',
            ],
        ],

        'PLN UP2D S2JB' => [
            'has_subfolder' => false,
            'mode'          => 'file',
            'aliases'       => ['UP2D S2JB', 'UP2D'],
            'units'         => [
                'MUP2D S2JB',
                'TL LAKDAN',
            ],
        ],

        'PLN UP2K SUMSEL' => [
            'has_subfolder' => false,
            'mode'          => 'file',
            'aliases'       => ['UP2K SUMSEL'],
            'units'         => [
                'MUP2K SUMSEL',
            ],
        ],

        'PLN UP2K JAMBI' => [
            'has_subfolder' => false,
            'mode'          => 'file',
            'aliases'       => ['UP2K JAMBI'],
            'units'         => [
                'MUP2K JAMBI',
            ],
        ],

        'PLN UP2K BENGKULU' => [
            'has_subfolder' => false,
            'mode'          => 'file',
            'aliases'       => ['UP2K BENGKULU', 'UP2K BKL'],
            'units'         => [
                'MUP2K BENGKULU',
            ],
        ],
    ],
];
