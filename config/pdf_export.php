<?php

return [

    /*
    |--------------------------------------------------------------------------
    | LibreOffice CLI Path Configuration
    |--------------------------------------------------------------------------
    |
    | Path ke executable LibreOffice / soffice CLI.
    | Di lingkungan Linux Render, default path adalah '/usr/bin/soffice'.
    | Di lingkungan Windows Lokal, diset via ENV LIBREOFFICE_PATH.
    |
    */

    'libreoffice_path' => env(
        'LIBREOFFICE_PATH',
        PHP_OS_FAMILY === 'Windows'
            ? 'C:\Program Files\LibreOffice\program\soffice.exe'
            : '/usr/bin/soffice'
    ),

];
