<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\FolderGeneratorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class GenerateFolderJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 600; // 10 menit
    public int $tries = 1;

    public function __construct(
        public readonly string $folderId,
        public readonly ?int $logId = null,
        public readonly ?string $semester = null,
        public readonly ?string $bulan = null,
    ) {}

    public function handle(FolderGeneratorService $generatorService): void
    {
        @set_time_limit(600);
        @ini_set('max_execution_time', '600');

        Log::info("[AUTO-GENERATE 5/6 QUEUE] GenerateFolderJob::handle dipanggil oleh Queue Worker", [
            'folderId' => $this->folderId,
            'logId'    => $this->logId,
        ]);

        if (! empty($this->folderId)) {
            $generatorService->generateContextAwareFolders($this->folderId, $this->logId);
        } else {
            $generatorService->generateSemesterMonthFolders($this->semester ?? 'Semester 1', $this->bulan ?? 'Januari', $this->logId);
        }

        Log::info("[AUTO-GENERATE 5/6 QUEUE] GenerateFolderJob::handle selesai untuk folderId: {$this->folderId}");
    }

    public function failed(Throwable $exception): void
    {
        Log::error("[AUTO-GENERATE 5/6 QUEUE FAILED] GenerateFolderJob gagal untuk folderId: {$this->folderId}", [
            'error' => $exception->getMessage(),
        ]);

        if ($this->logId) {
            \App\Models\FolderGenerationLog::where('id', $this->logId)->update([
                'status'        => 'FAILED',
                'error_message' => $exception->getMessage(),
            ]);
        }
    }
}
