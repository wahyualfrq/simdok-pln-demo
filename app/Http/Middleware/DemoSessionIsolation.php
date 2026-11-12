<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * DemoSessionIsolation
 *
 * DEMO: setiap pengunjung yang sudah login mendapat SALINAN basis data demo dan direktori cache file
 * sendiri (kunci = hash ID session), sehingga perubahan status monitoring, log sinkronisasi, dan cache
 * tidak bocor ke pengunjung lain pada instance yang sama. Basis data dasar (demo.sqlite yang disalin ke
 * /tmp oleh api/index.php) tidak pernah diubah oleh sesi pengunjung. Semua kode asli (controller, service,
 * agregat dasbor) berjalan tanpa perubahan di atas basis data sesi.
 *
 * - Hanya aktif bila DEMO_MODE dan basis data SQLite berupa berkas (bukan :memory: seperti pada tes).
 * - Pengunjung yang belum login memakai basis data dasar (tidak membuat salinan).
 * - Berkas milik sesi yang lebih tua dari config('demo.drive.overlay_ttl_hours') dibersihkan saat salinan baru
 *   dibuat; bila hilang, salinan baru dibuat dari basis data dasar (perubahan sementara — diterima untuk demo).
 * - Lokasi: storage_path('app/demo/sessions/<kunci>/') (di Vercel = /tmp).
 */
class DemoSessionIsolation
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('demo.enabled') || ! $request->hasSession() || config('database.default') !== 'sqlite') {
            return $next($request);
        }

        $base = (string) config('database.connections.sqlite.database');
        if ($base === '' || $base === ':memory:' || ! is_file($base)) {
            return $next($request);
        }

        if (! $request->session()->has(Auth::guard()->getName())) {
            return $next($request);
        }

        $root = storage_path('app/demo/sessions');
        $dir  = $root . DIRECTORY_SEPARATOR . substr(sha1($request->session()->getId()), 0, 16);
        $db   = $dir . DIRECTORY_SEPARATOR . 'database.sqlite';

        if (! is_file($db)) {
            $this->prune($root);
            @mkdir($dir, 0775, true);

            // Salin ke berkas sementara lalu rename (atomik) agar request bersamaan tidak membaca DB setengah jadi.
            $partial = $db . '.' . getmypid() . '.tmp';
            if (copy($base, $partial) && ! @rename($partial, $db)) {
                @unlink($partial);
            }
        }

        if (is_file($db)) {
            config(['database.connections.sqlite.database' => $db]);
            DB::purge('sqlite');

            if (config('cache.default') === 'file') {
                $cache = $dir . DIRECTORY_SEPARATOR . 'cache';
                @mkdir($cache, 0775, true);
                config(['cache.stores.file.path' => $cache]);
                Cache::forgetDriver('file');
            }
        }

        return $next($request);
    }

    /**
     * Hapus direktori sesi yang tidak disentuh lebih lama dari TTL.
     */
    private function prune(string $root): void
    {
        $threshold = time() - ((int) config('demo.drive.overlay_ttl_hours', 24) * 3600);

        foreach (glob($root . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [] as $dir) {
            $db = $dir . DIRECTORY_SEPARATOR . 'database.sqlite';
            if ((is_file($db) ? filemtime($db) : filemtime($dir)) < $threshold) {
                $this->removeDirectory($dir);
            }
        }
    }

    private function removeDirectory(string $dir): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
