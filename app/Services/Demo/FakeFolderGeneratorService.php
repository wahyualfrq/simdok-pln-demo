<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Models\FolderGenerationLog;
use App\Services\FolderGeneratorService;

/**
 * FakeFolderGeneratorService
 *
 * DEMO: FolderGeneratorService asli tetap berjalan apa adanya di atas Drive palsu (folder baru
 * masuk overlay sesi). Satu-satunya perbedaan: pembuatan dijalankan SINKRON di dalam request
 * oleh DemoProgressSimulator::runGenerationNow() (saat log PENDING dibuat), sehingga eksekusi
 * pasca-respons dari GenerateFolderJob::dispatchAfterResponse — yang tidak andal di serverless —
 * menjadi no-op (log sudah tidak PENDING).
 */
class FakeFolderGeneratorService extends FolderGeneratorService
{
    public function generateContextAwareFolders(string $folderId, ?int $logId = null): FolderGenerationLog
    {
        $log = $logId ? FolderGenerationLog::find($logId) : null;

        if ($log !== null && strtoupper((string) $log->status) !== 'PENDING') {
            return $log; // sudah dijalankan sinkron oleh simulator (atau sudah selesai)
        }

        return parent::generateContextAwareFolders($folderId, $logId);
    }
}
