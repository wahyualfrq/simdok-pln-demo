<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\DriveMonitoringSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * MonitoringSyncJob
 *
 * Background Job untuk menyinkronkan status kelengkapan dokumen Google Drive & Sheet ke SQLite.
 * Dijalankan secara async / queued agar HTTP web request user langsung kembali tanpa delay.
 */
class MonitoringSyncJob implements ShouldQueue
{
    use Queueable;

    /**
     * Timeout job dalam detik (5 menit)
     */
    public int $timeout = 300;

    /**
     * Berapa kali job di-retry jika gagal
     */
    public int $tries = 1;

    public function __construct(
        public readonly string $semesterName,
        public readonly ?int $syncLogId = null
    ) {}

    public function handle(DriveMonitoringSyncService $syncService): void
    {
        Log::info("MonitoringSyncJob: Memulai job sync background untuk {$this->semesterName} (Log ID: {$this->syncLogId})");

        $syncService->syncSemester($this->semesterName, $this->syncLogId, forceDiscovery: true);

        Log::info("MonitoringSyncJob: Job sync background selesai untuk {$this->semesterName}");
    }

    public function failed(Throwable $exception): void
    {
        Log::error("MonitoringSyncJob: Job sync gagal untuk {$this->semesterName}", [
            'error' => $exception->getMessage(),
        ]);
    }
}
