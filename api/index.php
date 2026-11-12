<?php

/**
 * DEMO: Entry point Vercel (runtime vercel-php) untuk SIMDOK versi demo.
 *
 * Filesystem Vercel read-only kecuali /tmp, sehingga seluruh jalur tulis Laravel dialihkan ke /tmp:
 *   - storage (cache, session file, view terkompilasi, log, overlay demo, berkas export sementara)
 *   - cache bootstrap (packages/services/config/routes/events)
 *   - database SQLite (disalin dari database/demo.sqlite saat cold start)
 *
 * Laravel membaca LARAVEL_STORAGE_PATH dari $_ENV / $_SERVER (bukan dari getenv()), jadi
 * variabel ditulis ke ketiganya: $_ENV, $_SERVER, dan putenv().
 *
 * Tidak mengubah bootstrap/app.php untuk storage; meneruskan request ke public/index.php.
 */

$tmp     = rtrim(sys_get_temp_dir(), '/\\');
$storage = $tmp . '/storage';

// Direktori tulis (dibuat bila belum ada pada instance yang baru start).
foreach ([
    'app',
    'app/temp',
    'app/temp/preview',
    'app/demo',
    'framework/cache/data',
    'framework/sessions',
    'framework/views',
    'bootstrap-cache',
    'logs',
] as $directory) {
    $path = $storage . '/' . $directory;
    if (! is_dir($path)) {
        @mkdir($path, 0775, true);
    }
}

// Database demo: salin sekali per instance (cold start). Perubahan bersifat sementara & per-instance.
$database = $tmp . '/database.sqlite';
// Disalin ke berkas sementara lalu di-rename (atomik): request bersamaan tidak pernah melihat DB setengah tersalin.
if (! is_file($database)) {
    $partial = $database . '.' . getmypid() . '.tmp';
    if (copy(__DIR__ . '/../database/demo.sqlite', $partial) && ! @rename($partial, $database)) {
        @unlink($partial); // request lain sudah menyelesaikan salinan lebih dulu
    }
}

$environment = [
    'LARAVEL_STORAGE_PATH' => $storage,
    'DB_DATABASE'          => $database,
    'VIEW_COMPILED_PATH'   => $storage . '/framework/views',
    // Cache bootstrap Laravel (bootstrap/cache read-only di Vercel).
    'APP_PACKAGES_CACHE'   => $storage . '/bootstrap-cache/packages.php',
    'APP_SERVICES_CACHE'   => $storage . '/bootstrap-cache/services.php',
    'APP_CONFIG_CACHE'     => $storage . '/bootstrap-cache/config.php',
    'APP_ROUTES_CACHE'     => $storage . '/bootstrap-cache/routes.php',
    'APP_EVENTS_CACHE'     => $storage . '/bootstrap-cache/events.php',
];

foreach ($environment as $key => $value) {
    $_ENV[$key]    = $value;
    $_SERVER[$key] = $value;
    putenv($key . '=' . $value);
}

require __DIR__ . '/../public/index.php';
