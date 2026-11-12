<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * CacheService
 *
 * Pusat manajemen seluruh cache kunci SIMDOK.
 * Didesain driver-agnostic: berjalan di database, redis, maupun memcached.
 *
 * Cache Keys:
 *  - dashboard_cache            → ringkasan dashboard (folders + summary)
 *  - monitoring_cache_{slug}    → data monitoring per semester
 *  - folder_meta_{id}           → metadata satu folder/file Drive
 *  - folder_list_{parentId}     → daftar subfolder dalam parent
 *  - sheet_rows_cache           → baris data Google Sheet (UNIT!A1:P500)
 *
 * TTL:
 *  - Dashboard    : 30 menit
 *  - Monitoring   : 30 menit
 *  - Folder meta  : 1 jam
 *  - Folder list  : 1 jam
 *  - Sheet rows   : 30 menit
 *
 * Invalidation: event-driven (upload, delete, rename, create folder, root change).
 */
class CacheService
{
    // ── TTL Constants ──────────────────────────────────────────────────────────

    public const TTL_DASHBOARD        = 1800;  // 30 menit
    public const TTL_MONITORING       = 1800;  // 30 menit
    public const TTL_FOLDER           = 3600;  // 1 jam
    public const TTL_SHEET            = 1800;  // 30 menit
    public const TTL_FILE_LIST        = 300;   // 5 menit
    public const TTL_BREADCRUMB       = 86400; // 24 jam
    public const TTL_FOLDER_HIERARCHY = 86400; // 24 jam

    /**
     * TTL untuk DB cache leaf scan — bulan yang masih aktif (upload mungkin terjadi).
     * 24 jam: cukup lama untuk performa, cukup pendek untuk akurasi.
     */
    public const TTL_LEAF_SCAN_ACTIVE = 86400;  // 24 jam

    /**
     * TTL untuk DB cache leaf scan — bulan yang sudah lewat (tidak ada upload baru).
     * 7 hari: hampir tidak pernah berubah, jadi bisa cache lama.
     */
    public const TTL_LEAF_SCAN_CLOSED = 604800; // 7 hari

    // ── Key Generators ─────────────────────────────────────────────────────────

    public static function keyDashboard(): string
    {
        return 'simdok:dashboard_cache';
    }

    public static function keyMonitoring(string $semester): string
    {
        $slug = strtolower(str_replace(' ', '_', $semester));
        return "simdok:monitoring_cache_{$slug}";
    }

    public static function keyFolderMeta(string $id): string
    {
        return "simdok:folder_meta_{$id}";
    }

    public static function keyFolderList(string $parentId): string
    {
        return "simdok:folder_list_{$parentId}";
    }

    public static function keyFileList(string $folderId): string
    {
        return "simdok:file_list_{$folderId}";
    }

    public static function keyBreadcrumb(string $folderId): string
    {
        return "simdok:breadcrumb_{$folderId}";
    }

    public static function keyFolderHierarchy(string $folderId): string
    {
        return "simdok:folder_hierarchy_{$folderId}";
    }

    public static function keySheetRows(): string
    {
        return 'simdok:sheet_rows_cache';
    }

    public static function keyLeafScan(string $folderId): string
    {
        return "simdok:leaf_scan_{$folderId}";
    }

    // ── Dashboard ──────────────────────────────────────────────────────────────

    public function getDashboard(): ?array
    {
        return Cache::get(self::keyDashboard());
    }

    public function putDashboard(array $data): void
    {
        Cache::put(self::keyDashboard(), $data, self::TTL_DASHBOARD);
    }

    public function invalidateDashboard(): void
    {
        Cache::forget(self::keyDashboard());
    }

    // ── Monitoring ─────────────────────────────────────────────────────────────

    public function getMonitoring(string $semester): ?array
    {
        return Cache::get(self::keyMonitoring($semester));
    }

    public function putMonitoring(string $semester, array $data): void
    {
        Cache::put(self::keyMonitoring($semester), $data, self::TTL_MONITORING);
    }

    /** Invalidate cache untuk satu semester tertentu. */
    public function invalidateMonitoring(string $semester): void
    {
        Cache::forget(self::keyMonitoring($semester));
        try {
            app(PdfExportService::class)->clearFilledExcelCache($semester);
            app(MonitoringDatasetBuilder::class)->clearCache($semester);
        } catch (\Throwable) {}
    }

    /** Invalidate cache untuk semua semester sekaligus. */
    public function invalidateAllMonitoring(): void
    {
        Cache::forget(self::keyMonitoring('Semester 1'));
        Cache::forget(self::keyMonitoring('Semester 2'));
        Cache::forget('simdok:monitoring_cache_overall');
        try {
            app(PdfExportService::class)->clearFilledExcelCache();
            app(MonitoringDatasetBuilder::class)->clearCache();
        } catch (\Throwable) {}
    }

    // ── Folder Metadata ────────────────────────────────────────────────────────

    public function getFolderMeta(string $id): ?array
    {
        return Cache::get(self::keyFolderMeta($id));
    }

    public function putFolderMeta(string $id, array $data): void
    {
        Cache::put(self::keyFolderMeta($id), $data, self::TTL_FOLDER);
    }

    public function invalidateFolderMeta(string $id): void
    {
        Cache::forget(self::keyFolderMeta($id));
    }

    // ── Folder List ────────────────────────────────────────────────────────────

    public function getFolderList(string $parentId): ?array
    {
        return Cache::get(self::keyFolderList($parentId));
    }

    public function putFolderList(string $parentId, array $data): void
    {
        Cache::put(self::keyFolderList($parentId), $data, self::TTL_FOLDER);
    }

    public function invalidateFolderList(string $parentId): void
    {
        Cache::forget(self::keyFolderList($parentId));
    }

    // ── File List (TTL 5 Menit) ───────────────────────────────────────────────

    public function getFileList(string $folderId): ?array
    {
        return Cache::get(self::keyFileList($folderId));
    }

    public function putFileList(string $folderId, array $data): void
    {
        Cache::put(self::keyFileList($folderId), $data, self::TTL_FILE_LIST);
    }

    public function invalidateFileList(string $folderId): void
    {
        Cache::forget(self::keyFileList($folderId));
    }

    // ── Breadcrumb (TTL 24 Jam) ───────────────────────────────────────────────

    public function getBreadcrumb(string $folderId): ?array
    {
        return Cache::get(self::keyBreadcrumb($folderId));
    }

    public function putBreadcrumb(string $folderId, array $data): void
    {
        Cache::put(self::keyBreadcrumb($folderId), $data, self::TTL_BREADCRUMB);
    }

    public function invalidateBreadcrumb(string $folderId): void
    {
        Cache::forget(self::keyBreadcrumb($folderId));
    }

    // ── Folder Hierarchy (TTL 24 Jam) ─────────────────────────────────────────

    public function getFolderHierarchy(string $folderId): ?array
    {
        return Cache::get(self::keyFolderHierarchy($folderId));
    }

    public function putFolderHierarchy(string $folderId, array $data): void
    {
        Cache::put(self::keyFolderHierarchy($folderId), $data, self::TTL_FOLDER_HIERARCHY);
    }

    public function invalidateFolderHierarchy(string $folderId): void
    {
        Cache::forget(self::keyFolderHierarchy($folderId));
    }

    // ── Sheet Rows ─────────────────────────────────────────────────────────────

    public function getSheetRows(): ?array
    {
        return Cache::get(self::keySheetRows());
    }

    public function putSheetRows(array $rows): void
    {
        Cache::put(self::keySheetRows(), $rows, self::TTL_SHEET);
    }

    public function invalidateSheetRows(): void
    {
        Cache::forget(self::keySheetRows());
    }

    // ── Bulk Invalidation ──────────────────────────────────────────────────────

    /**
     * Invalidate semua cache yang berkaitan dengan data dokumen.
     * Dipanggil saat: upload, delete, root folder berubah, atau disconnect Google.
     */
    public function invalidateAll(): void
    {
        $this->invalidateDashboard();
        $this->invalidateAllMonitoring();
        $this->invalidateSheetRows();
        \Illuminate\Support\Facades\Cache::forget('simdok:drive_status_map');
    }

    /**
     * Invalidate cache yang berkaitan dengan konten folder tertentu.
     * Dipanggil saat: upload atau delete file di dalam folder.
     */
    public function invalidateAfterFileChange(string $folderId): void
    {
        $this->invalidateDashboard();
        $this->invalidateAllMonitoring();
        $this->invalidateSheetRows();
        $this->invalidateFolderList($folderId);
        $this->invalidateFileList($folderId);
        \Illuminate\Support\Facades\Cache::forget('simdok:drive_status_map');
        try {
            \Illuminate\Support\Facades\DB::table('monitoring_folder_cache')->where('drive_folder_id', $folderId)->delete();
        } catch (\Throwable) {}
    }
}
