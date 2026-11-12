<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Models\MonitoringSyncLog;
use App\Services\DriveMonitoringSyncService;
use Exception;

/**
 * FakeDriveMonitoringSyncService
 *
 * DEMO: Pengganti DriveMonitoringSyncService untuk versi demo. TIDAK memindai apa pun
 * (tidak ada DriveLeafScannerService, GoogleSheetService asli, maupun Google\Client) dan
 * tidak memakai sleep(): syncSemester() hanya menandai MonitoringSyncLog sebagai RUNNING
 * dengan started_at, lalu kembali. Progres, penyelesaian, dan perubahan status unit dihitung
 * berdasarkan waktu oleh DemoProgressSimulator lewat listener `retrieved` (lihat AppServiceProvider).
 *
 * Satu-satunya method publik yang dipakai pemanggil (MonitoringSyncJob) adalah syncSemester().
 */
class FakeDriveMonitoringSyncService extends DriveMonitoringSyncService
{
    /** Nama semester yang dikenali (sama dengan SEMESTER_MONTHS di service asli). */
    private const SEMESTERS = ['Semester 1', 'Semester 2'];

    /**
     * DEMO: sengaja tidak memanggil parent::__construct() (tanpa scanner, sheet, dan Google).
     */
    public function __construct(private readonly DemoDriveTree $demoTree) {}

    /**
     * @param  string    $semesterName  'Semester 1' atau 'Semester 2'
     * @param  int|null  $syncLogId     ID log yang sudah dibuat controller (opsional)
     * @param  bool      $forceDiscovery  Diabaikan pada demo
     * @throws Exception bila semester tidak valid
     */
    public function syncSemester(string $semesterName, ?int $syncLogId = null, bool $forceDiscovery = false): MonitoringSyncLog
    {
        if (! in_array($semesterName, self::SEMESTERS, true)) {
            throw new Exception("Semester tidak valid: {$semesterName}");
        }

        $total = count(array_filter($this->demoTree->slots(), function (array $slot) use ($semesterName) {
            $month = (int) substr($slot['period'], 5, 2);

            return (($month === 12 || $month <= 5) ? 'Semester 1' : 'Semester 2') === $semesterName;
        }));

        $attributes = [
            'status'         => 'RUNNING',
            'started_at'     => now(),
            'finished_at'    => null,
            'progress'       => 0,
            'processed'      => 0,
            'total'          => $total,
            'current_region' => null,
            'current_unit'   => null,
        ];

        $log = $syncLogId ? MonitoringSyncLog::find($syncLogId) : null;

        if ($log === null) {
            return MonitoringSyncLog::create(['semester' => $semesterName] + $attributes);
        }

        $log->update($attributes);

        return $log;
    }
}
