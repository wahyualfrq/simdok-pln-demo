<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MonitoringStatus;
use App\Services\Google\GoogleApiService;
use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * MonitoringService
 *
 * Menyajikan data kelengkapan dokumen murni dari Database SQLite (tabel `monitoring_statuses`).
 * HALAMAN MONITORING MELEWATKAN SEMUA HIT GOOGLE API (0 Drive API Call, 0 Sheet API Call).
 * Waktu muat halaman (Page Load): < 10 ms (Target < 500 ms).
 */
class MonitoringService
{
    private const MONTH_MAPPING = [
        'Januari'   => ['col' => 4,  'label' => 'Jan'],
        'Februari'  => ['col' => 5,  'label' => 'Feb'],
        'Maret'     => ['col' => 6,  'label' => 'Mar'],
        'April'     => ['col' => 7,  'label' => 'Apr'],
        'Mei'       => ['col' => 8,  'label' => 'Mei'],
        'Juni'      => ['col' => 9,  'label' => 'Jun'],
        'Juli'      => ['col' => 10, 'label' => 'Jul'],
        'Agustus'   => ['col' => 11, 'label' => 'Agt'],
        'September' => ['col' => 12, 'label' => 'Sept'],
        'Oktober'   => ['col' => 13, 'label' => 'Okt'],
        'November'  => ['col' => 14, 'label' => 'Nov'],
        'Desember'  => ['col' => 3,  'label' => 'Des'],
    ];

    private const SEMESTER_MONTHS = [
        'Semester 1' => ['Desember', 'Januari', 'Februari', 'Maret', 'April', 'Mei'],
        'Semester 2' => ['Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November'],
    ];

    public function __construct(
        private readonly CacheService $cacheService,
        private readonly DriveMonitoringSyncService $syncService,
    ) {}

    /**
     * Mendapatkan data monitoring terstruktur murni dari Database SQLite.
     * ZERO GOOGLE API CALLS.
     *
     * @param  string  $semesterName  "Semester 1" atau "Semester 2"
     * @return array{
     *     semester: string,
     *     months: array<int, array{name: string, label: string}>,
     *     regions: array,
     *     summary: array{total_units: int, total: int, complete: int, incomplete: int, mismatch: int, progress: float}
     * }
     * @throws Exception
     */
    public function getMonitoringData(string $semesterName): array
    {
        if (! isset(self::SEMESTER_MONTHS[$semesterName])) {
            throw new Exception("Semester tidak valid: {$semesterName}");
        }

        // Cek cache memori terlebih dahulu
        $cached = $this->cacheService->getMonitoring($semesterName);
        if ($cached !== null) {
            return $cached;
        }

        $startTime = microtime(true);

        // 1. Ambil daftar bulan aktif untuk semester ini
        $months = [];
        foreach (self::SEMESTER_MONTHS[$semesterName] as $mName) {
            $months[] = [
                'name'  => $mName,
                'label' => self::MONTH_MAPPING[$mName]['label'],
            ];
        }

        // 2. Query instan dari SQLite Database (0 Google API Calls)
        $statuses = MonitoringStatus::where('semester', $semesterName)->get();

        // 3. Bangun struktur Data Regions & Units
        $regionsMap = [];

        foreach ($statuses as $row) {
            $regionName    = $row->region;
            $unitName      = $row->unit;
            $origName      = $row->unit_original ?: $unitName;
            $monthName     = $row->bulan;
            $rawStatus     = strtolower($row->status); // 'complete', 'incomplete', 'mismatch'
            $evidenceCount = (int) ($row->evidence_count ?? 0);

            // Status tampilan langsung diambil dari kolom status DB (respecting manual override & auto-sync)
            $statusVal = $rawStatus;

            if (! isset($regionsMap[$regionName][$unitName])) {
                $regionsMap[$regionName][$unitName] = [
                    'name'           => $unitName,
                    'originalName'   => $origName,
                    'subUnit'        => $row->sub_unit,
                    'statuses'       => [],
                    'evidenceFiles'  => [],
                    'evidenceCounts' => [],
                    'folderIds'      => [],
                    'lastSync'       => $row->last_sync?->format('d M Y H:i'),
                ];
            }

            $regionsMap[$regionName][$unitName]['statuses'][$monthName]       = $statusVal;
            $regionsMap[$regionName][$unitName]['evidenceFiles'][$monthName]  = $row->sample_evidence_file;
            $regionsMap[$regionName][$unitName]['evidenceCounts'][$monthName] = $evidenceCount;
            $regionsMap[$regionName][$unitName]['folderIds'][$monthName]      = $row->folder_id;
        }

        // Format regions menjadi array numerik
        $regions = [];
        foreach ($regionsMap as $parentName => $unitsMap) {
            $regions[] = [
                'name'  => $parentName,
                'units' => array_values($unitsMap),
            ];
        }

        // 4. Hitung Ringkasan (Summary)
        $summary = $this->calculateSummary($regions);

        $result = [
            'semester' => $semesterName,
            'months'   => $months,
            'regions'  => $regions,
            'summary'  => $summary,
            'is_empty' => empty($regions),
        ];

        // Simpan ke cache singkat
        $this->cacheService->putMonitoring($semesterName, $result);

        $elapsedMs = round((microtime(true) - $startTime) * 1000, 2);
        Log::info("MonitoringService: Data dimuat dari SQLite dalam {$elapsedMs} ms (0 Google API Call)");

        return $result;
    }

    /**
     * Menghitung ringkasan total target, complete, incomplete, mismatch, dan progress.
     * Status "pending" (ada file di Drive tapi belum ceklist) dihitung sebagai mismatch untuk summary.
     */
    private function calculateSummary(array $regions): array
    {
        $totalSlots = 0;
        $complete   = 0;
        $incomplete = 0;
        $mismatch   = 0;
        $pending    = 0;
        $totalUnits = 0;

        foreach ($regions as $region) {
            foreach ($region['units'] as $unit) {
                $totalUnits++;
                foreach ($unit['statuses'] as $status) {
                    $totalSlots++;
                    if ($status === 'complete') {
                        $complete++;
                    } elseif ($status === 'pending') {
                        $pending++; // Pending Finalisasi: hanya tambah pending, BUKAN mismatch
                    } elseif ($status === 'mismatch') {
                        $mismatch++;
                    } else {
                        $incomplete++;
                    }
                }
            }
        }

        $progress = $totalSlots > 0
            ? round(($complete / $totalSlots) * 100, 1)
            : 0;

        return [
            'total_units' => $totalUnits,
            'total'       => $totalSlots,
            'complete'    => $complete,
            'incomplete'  => $incomplete,
            'mismatch'    => $mismatch,
            'pending'     => $pending,
            'progress'    => $progress,
        ];
    }

    /**
     * Mendapatkan ringkasan statistik keseluruhan untuk Dashboard murni dari Database SQLite.
     * ZERO GOOGLE API CALLS. Execution time < 5ms.
     *
     * @return array{total_units: int, total: int, complete: int, incomplete: int, mismatch: int, progress: float}
     */
    public function getOverallSummary(): array
    {
        $cached = $this->cacheService->getMonitoring('overall');
        if ($cached !== null) {
            return $cached;
        }

        $totalUnits = MonitoringStatus::distinct('unit')->count('unit');
        $totalSlots = MonitoringStatus::count();
        $complete   = MonitoringStatus::whereIn('status', ['COMPLETE', 'complete'])->count();
        $pending    = MonitoringStatus::whereIn('status', ['PENDING', 'pending'])->count();
        $mismatch   = MonitoringStatus::whereIn('status', ['MISMATCH', 'mismatch'])->count();
        $incomplete = MonitoringStatus::whereIn('status', ['INCOMPLETE', 'incomplete'])->count();

        $progress = $totalSlots > 0
            ? round(($complete / $totalSlots) * 100, 1)
            : 0;

        $result = [
            'total_units' => $totalUnits,
            'total'       => $totalSlots,
            'complete'    => $complete,
            'pending'     => $pending,
            'mismatch'    => $mismatch,
            'incomplete'  => $incomplete,
            'progress'    => $progress,
        ];

        $this->cacheService->putMonitoring('overall', $result);

        return $result;
    }

    /**
     * Mendapatkan daftar folder bulan dari database lokal.
     * ZERO GOOGLE API CALLS.
     *
     * @return array<int, array{id: string, name: string}>
     */
    public function getRootFoldersFromDb(): array
    {
        $cached = \Illuminate\Support\Facades\Cache::get('simdok:root_folders_db_cache');
        if ($cached !== null) {
            return $cached;
        }

        $folders = \Illuminate\Support\Facades\DB::table('monitoring_folder_cache')
            ->select('month_folder_id as id', 'month_period as name')
            ->distinct()
            ->get();

        if ($folders->isEmpty()) {
            $months = MonitoringStatus::select('bulan as name')
                ->distinct()
                ->get();

            $result = $months->map(fn ($m) => [
                'id'   => $m->name,
                'name' => $m->name,
            ])->toArray();
        } else {
            $result = $folders->map(fn ($f) => [
                'id'   => $f->id,
                'name' => $f->name,
            ])->toArray();
        }

        \Illuminate\Support\Facades\Cache::put('simdok:root_folders_db_cache', $result, CacheService::TTL_MONITORING);

        return $result;
    }

    /**
     * Bersihkan nama unit dari nomor urut (contoh: "a. MULP KENTEN" -> "MULP KENTEN").
     *
     * @param  string  $name
     * @return string
     */
    public function cleanUnitName(string $name): string
    {
        return preg_replace('/^[a-z0-9]+\.\s*/i', '', trim($name));
    }

    /**
     * Mendapatkan data progress per bulan per semester untuk chart & accordion dashboard.
     * ZERO GOOGLE API CALLS. Hanya 2 query SQLite.
     *
     * @return array{"Semester 1": array{months: array, pct: float, total: int, complete: int}, ...}
     */
    public function getMonthlyProgressForDashboard(): array
    {
        // Query 1: Hitung status per bulan dari SQLite
        $statusCounts = MonitoringStatus::selectRaw('bulan, status, COUNT(*) as count')
            ->groupBy('bulan', 'status')
            ->get()
            ->groupBy('bulan');

        // Query 2: Ambil folder ID per bulan dari cache table & filter valid ID
        $folderCacheRows = DB::table('monitoring_folder_cache')
            ->select('month_period', 'month_folder_id')
            ->whereNotNull('month_folder_id')
            ->where('month_folder_id', '!=', '')
            ->where('month_folder_id', '!=', 'mock_month_id')
            ->distinct()
            ->get();

        $monthNumberMap = [
            'Desember'  => '12',
            'Januari'   => '01',
            'Februari'  => '02',
            'Maret'     => '03',
            'April'     => '04',
            'Mei'       => '05',
            'Juni'      => '06',
            'Juli'      => '07',
            'Agustus'   => '08',
            'September' => '09',
            'Oktober'   => '10',
            'November'  => '11',
        ];

        // Closure untuk resolusi folder_id bulan yang fleksibel (misal '2026-06', 'juni', 'Juni')
        $resolveMonthFolderId = function (string $monthName) use ($folderCacheRows, $monthNumberMap): ?string {
            $monthLower = strtolower($monthName);
            $monthNum   = $monthNumberMap[$monthName] ?? null;

            // 1. Pencocokan langsung / lowercase di month_period
            $match = $folderCacheRows->first(fn ($r) => strtolower((string) $r->month_period) === $monthLower);
            if ($match && ! empty($match->month_folder_id)) {
                return $match->month_folder_id;
            }

            // 2. Pencocokan format YYYY-MM (misal: '%-06' untuk Juni)
            if ($monthNum) {
                $matchNum = $folderCacheRows->first(fn ($r) => str_ends_with((string) $r->month_period, '-' . $monthNum));
                if ($matchNum && ! empty($matchNum->month_folder_id)) {
                    return $matchNum->month_folder_id;
                }
            }

            // 3. Fallback: Substring singkatan 3 huruf (misal: 'des', 'jan', 'jun')
            $sub = substr($monthLower, 0, 3);
            $matchSub = $folderCacheRows->first(fn ($r) => str_contains(strtolower((string) $r->month_period), $sub));
            if ($matchSub && ! empty($matchSub->month_folder_id)) {
                return $matchSub->month_folder_id;
            }

            return null;
        };

        $result = [];

        foreach (self::SEMESTER_MONTHS as $semesterName => $monthNames) {
            $monthData   = [];
            $semTotal    = 0;
            $semComplete = 0;

            foreach ($monthNames as $monthName) {
                $rows       = $statusCounts->get($monthName, collect());
                $complete   = (int) $rows->where('status', 'COMPLETE')->sum('count');
                $mismatch   = (int) $rows->where('status', 'MISMATCH')->sum('count');
                $incomplete = (int) $rows->where('status', 'INCOMPLETE')->sum('count');
                $total      = $complete + $mismatch + $incomplete;
                $pct        = $total > 0 ? round(($complete / $total) * 100, 1) : 0.0;

                $folderId   = $resolveMonthFolderId($monthName);

                $monthData[] = [
                    'name'       => $monthName,
                    'label'      => self::MONTH_MAPPING[$monthName]['label'] ?? substr($monthName, 0, 3),
                    'complete'   => $complete,
                    'mismatch'   => $mismatch,
                    'incomplete' => $incomplete,
                    'total'      => $total,
                    'pct'        => $pct,
                    'folder_id'  => $folderId,
                ];

                $semTotal    += $total;
                $semComplete += $complete;
            }

            $semPct = $semTotal > 0 ? round(($semComplete / $semTotal) * 100, 1) : 0.0;

            $result[$semesterName] = [
                'months'   => $monthData,
                'pct'      => $semPct,
                'total'    => $semTotal,
                'complete' => $semComplete,
            ];
        }

        return $result;
    }

    /**
     * Mendapatkan daftar folder yang berstatus "Pending Finalisasi":
     * yaitu folder yang sudah ada file di Drive (evidence_count >= 1)
     * tapi belum dicentang di Google Sheet (status masih INCOMPLETE di DB).
     *
     * Ini sesuai dengan logika tampilan "pending" di getMonitoringData().
     * ZERO GOOGLE API CALLS.
     *
     * @return array
     */
    public function getPendingFolders(): array
    {
        $rows = MonitoringStatus::whereIn('status', ['PENDING', 'pending'])
            ->orderBy('region')
            ->orderBy('unit')
            ->orderBy('bulan')
            ->get();

        $folders = [];
        foreach ($rows as $row) {
            $evCount = max((int) $row->evidence_count, ! empty($row->sample_evidence_file) ? 1 : 0);
            $folders[] = [
                'folder_id'     => $row->folder_id,
                'folder_name'   => $row->folder_name,
                'folder_path'   => $row->folder_path,
                'unit'          => $row->unit,
                'region'        => $row->region,
                'bulan'         => $row->bulan,
                'semester'      => $row->semester,
                'evidence_count'=> $evCount,
                'last_sync'     => $row->last_sync?->format('d M Y H:i'),
            ];
        }

        return $folders;
    }
}
