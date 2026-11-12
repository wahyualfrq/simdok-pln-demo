<?php

/**
 * DEMO: bootstrap khusus tes demo (phpunit.demo.xml).
 *
 * Mengarahkan storage ke direktori sementara SEBELUM aplikasi boot (Laravel membaca
 * LARAVEL_STORAGE_PATH dari $_ENV / $_SERVER), agar tes tidak menulis ke storage/ di repo
 * (overlay Drive demo, berkas export sementara, cache font dompdf).
 */
$storage = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'simdok_demo_tests';

foreach (['app', 'app/temp', 'app/temp/preview', 'app/demo', 'framework/cache/data', 'framework/sessions', 'framework/views', 'logs'] as $directory) {
    $path = $storage . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $directory);
    if (! is_dir($path)) {
        @mkdir($path, 0775, true);
    }
}

$_ENV['LARAVEL_STORAGE_PATH'] = $storage;
$_SERVER['LARAVEL_STORAGE_PATH'] = $storage;
putenv('LARAVEL_STORAGE_PATH=' . $storage);

require __DIR__ . '/../../vendor/autoload.php';
