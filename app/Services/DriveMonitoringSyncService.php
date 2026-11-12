<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MonitoringStatus;
use App\Models\MonitoringSyncLog;
use App\Services\Google\GoogleApiService;
use App\Services\Google\GoogleDriveService;
use App\Services\Google\GoogleSheetService;
use Google\Service\Drive as GoogleDrive;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * DriveMonitoringSyncService
 *
 * Sync Engine khusus untuk menyinkronkan data Google Sheet & Google Drive ke database SQLite.
 * Halaman MonitoringTIDAK PERNAH memanggil API Google — hanya membaca hasil sync dari DB SQLite.
 *
 * Fitur Utama:
 * 1. Semester Scoping: Memindai HANYA 6 bulan sesuai semester yang diproses (tidak 12 bulan sekaligus).
 * 2. Optimized API Query: Memanfaatkan listing terpadu & DriveLeafScannerService.
 * 3. Matriks Status:
 *    - COMPLETE   : Sheet = ✓ AND Drive = screenshot ditemukan
 *    - INCOMPLETE : Sheet = ✗ AND Drive = screenshot tidak ada
 *    - MISMATCH   : Sheet = ✓ (Drive tidak ada) ATAU Sheet = ✗ (Drive ada)
 * 4. High-Performance Persistensi: Batch upsert() ke tabel `monitoring_statuses`.
 * 5. Audit Logging: Catat setiap sesi sync ke tabel `monitoring_sync_logs`.
 */
class DriveMonitoringSyncService
{
    private const SHEET_NAME = 'UNIT';

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
        private readonly GoogleApiService $apiService,
        private readonly GoogleDriveService $driveService,
        private readonly GoogleSheetService $sheetService,
        private readonly DriveLeafScannerService $leafScanner,
        private readonly FolderRegistryService $registryService,
        private readonly UnitNameNormalizerService $normalizer,
        private readonly UnitAliasService $aliasService,
        private readonly SyncDiagnosticLoggerService $diagLogger,
        private readonly EvidenceMatchingService $matchingEngine,
        private readonly FolderStructureService $structureService,
    ) {}

    /**
     * Menjalankan proses sync untuk Semester tertentu secara menyeluruh.
     *
     * @param  string    $semesterName  'Semester 1' atau 'Semester 2'
     * @param  int|null  $syncLogId     ID log sync jika sudah dibuat oleh controller (opsional)
     * @return MonitoringSyncLog
     * @throws Exception
     */
    public function syncSemester(string $semesterName, ?int $syncLogId = null, bool $forceDiscovery = false): MonitoringSyncLog
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        $startTime = microtime(true);

        if (! isset(self::SEMESTER_MONTHS[$semesterName])) {
            throw new Exception("Semester tidak valid: {$semesterName}");
        }

        $activeMonths = self::SEMESTER_MONTHS[$semesterName];
        $totalTargetUnits = max(1, $this->structureService->getTotalUnits() * count($activeMonths));

        // 1. Dapatkan atau Buat Log Sesi Sync
        $syncLog = $syncLogId ? MonitoringSyncLog::find($syncLogId) : null;
        if (! $syncLog) {
            $syncLog = MonitoringSyncLog::create([
                'semester'   => $semesterName,
                'started_at' => now(),
                'status'     => 'RUNNING',
                'progress'   => 0,
                'processed'  => 0,
                'total'      => $totalTargetUnits,
            ]);
        } else {
            $syncLog->update([
                'status'    => 'RUNNING',
                'progress'  => 5,
                'processed' => 0,
                'total'     => $totalTargetUnits,
            ]);
        }

        try {
            $spreadsheetId = $this->apiService->getSpreadsheetId();
            $settings      = $this->apiService->getSettings();
            $rootId        = $settings['root_folder_id'] ?? null;

            if (empty($rootId)) {
                throw new Exception('Root Folder Google Drive belum dipilih. Silakan buka menu Pengaturan Google dan klik "Pilih Root Folder" terlebih dahulu.');
            }

            if (empty($spreadsheetId)) {
                throw new Exception('Spreadsheet ID belum dikonfigurasi. Silakan buka menu Pengaturan Google, lalu klik "Ganti Root Folder" atau "Refresh Metadata" untuk mengidentifikasi file spreadsheet di Google Drive.');
            }

            $activeMonths = self::SEMESTER_MONTHS[$semesterName];

            // 2. Membaca Google Sheet (Selalu bersihkan cache sheet agar data terbaca langsung dari API)
            $syncLog->update(['progress' => 10]);
            $this->sheetService->invalidateSheetCache();
            $range = self::SHEET_NAME . '!A1:P500';
            $rows  = $this->sheetService->readRange($spreadsheetId, $range);

            // 3. Memindai Google Drive (HANYA 6 Bulan Semester Terkait) & Registrasi Folder
            $driveData = $this->scanDriveForSemester($rootId, $activeMonths, $semesterName, function (int $processed, int $total, int $progress) use ($syncLog) {
                $syncLog->update([
                    'processed' => $processed,
                    'total'     => $total,
                    'progress'  => min(90, max(10, $progress)),
                ]);
            }, $forceDiscovery);

            // 4. Hitung total unit slots target (e.g. 72 unit * 6 bulan = 432)
            $validUnitCount = 0;
            $tmpParent = '';
            foreach ($rows as $rIdx => $rData) {
                if ($rIdx < 5) continue;
                $cB = trim($rData[1] ?? '');
                $cC = trim($rData[2] ?? '');
                if (str_contains(strtolower($cC), 'disusun oleh') || str_contains(strtolower($cC), 'mengetahui')) break;
                if ($cB !== '') $tmpParent = $cB;
                if ($cC === '' || $tmpParent === '') continue;
                $validUnitCount++;
            }
            $totalTargetUnits = max(1, $validUnitCount * count($activeMonths));

            $syncLog->update([
                'processed'      => 0,
                'total'          => $totalTargetUnits,
                'progress'       => 0,
                'current_region' => 'Menyiapkan Data',
                'current_unit'   => 'Memulai Rekonsiliasi Sheet vs Drive...',
                'status'         => 'RUNNING',
            ]);

            // 5. Rekonsiliasi & Membangun Records Status dengan Normalizer Engine
            $now = now();
            $statusRecords   = [];
            $completeCount   = 0;
            $incompleteCount = 0;
            $mismatchCount   = 0;
            $foldersScanned  = $driveData['folders_scanned'];
            $filesScanned    = $driveData['files_scanned'];
            $driveMap        = $driveData['map'];
            $processedCount  = 0;

            $this->diagLogger->reset();
            $currentParent = '';
            $pendingSheetUpdates = [];

            foreach ($rows as $rowIndex => $rowData) {
                if ($rowIndex < 5) {
                    continue;
                }

                $colB = trim($rowData[1] ?? ''); // Region / Unit Induk
                $colC = trim($rowData[2] ?? ''); // Unit Kerja / Sub-unit

                if (
                    str_contains(strtolower($colC), 'disusun oleh') ||
                    str_contains(strtolower($colC), 'mengetahui')
                ) {
                    break;
                }

                if ($colB !== '') {
                    $currentParent = $colB;
                }

                if ($colC === '' || $currentParent === '') {
                    continue;
                }

                $regionName    = strtoupper(trim($currentParent));
                $cleanedUnit   = $this->cleanUnitName($colC);
                $unitName      = strtoupper($cleanedUnit);
                $unitOriginal  = trim($colC);

                $normRegion    = $this->normalizer->normalizeRegion($currentParent);
                $normUnit      = $this->normalizer->normalize($colC);

                foreach ($activeMonths as $mName) {
                    $colIdx     = self::MONTH_MAPPING[$mName]['col'];
                    $rawCellVal = strtolower(trim($rowData[$colIdx] ?? ''));

                    // Cek kelengkapan Sheet
                    $sheetIsComplete = in_array($rawCellVal, ['v', '✓', 'true', '1'], true);
                    $sheetStatusStr  = $sheetIsComplete ? 'v' : '';

                    // Cek kelengkapan Drive via Alias-Aware Lookup
                    $monthKey  = strtolower(trim($mName));

                    // Ambil semua alias untuk unit ini dari Google Sheet (dengan pembersihan prefix a. b. c.)
                    $unitAliases = array_unique(array_merge(
                        $this->aliasService->getAliases($colC),
                        $this->aliasService->getAliases($cleanedUnit)
                    ));

                    // Cari di driveMap menggunakan semua alias
                    $driveInfo = $this->findInDriveMapWithAliases(
                        driveMonthMap: $driveMap[$monthKey] ?? [],
                        parentName:    $currentParent,
                        sheetUnitName: $colC,
                        unitAliases:   $unitAliases,
                        normRegion:    $normRegion,
                        normUnit:      $normUnit,
                        monthKey:      $monthKey,
                    );

                    // Fallback dari Folder Registry Service jika masih belum ditemukan
                    if (! $driveInfo) {
                        $registeredFolderId = $this->registryService->findFolderId(
                            $semesterName,
                            $mName,
                            $currentParent,
                            $colC
                        );
                        if ($registeredFolderId) {
                            $driveInfo = [
                                'folder_id'        => $registeredFolderId,
                                'folder_name'      => $colC,
                                'folder_path'      => "{$currentParent} / {$colC}",
                                'image_file_count' => 1,
                                'sample_file'      => null,
                                'sub_unit'         => null,
                            ];
                        }
                    }

                    $driveHasScreenshot = $driveInfo && ($driveInfo['image_file_count'] >= 1);
                    $driveStatusStr     = $driveHasScreenshot ? 'COMPLETE' : 'INCOMPLETE';

                    // ── EVALUASI INTERNAL STATUS & DASHBOARD MAPPING ────────
                    $monthDriveMap     = $driveMap[$monthKey] ?? [];
                    $parentExists      = false;
                    foreach ($monthDriveMap as $regK => $uMap) {
                        if ($this->normalizer->isMatch($regK, $currentParent)) {
                            $parentExists = true;
                            break;
                        }
                    }

                    $unitFolderMatched = ($driveInfo !== null);

                    $internalStatus  = $this->diagLogger->evaluateInternalStatus(
                        sheetIsComplete:    $sheetIsComplete,
                        driveHasScreenshot: $driveHasScreenshot,
                        parentExists:       $parentExists,
                        unitFolderMatched:  $unitFolderMatched
                    );

                    $dashboardStatus = $this->diagLogger->mapToDashboardStatus($internalStatus);

                    if ($dashboardStatus === SyncDiagnosticLoggerService::DASHBOARD_COMPLETE) {
                        $completeCount++;
                    } elseif ($dashboardStatus === SyncDiagnosticLoggerService::DASHBOARD_INCOMPLETE) {
                        $incompleteCount++;
                    } else {
                        $mismatchCount++;
                    }

                    $parentMode = $this->matchingEngine->getParentMode($currentParent);

                    // Record & Log per unit
                    $this->diagLogger->recordUnitDiagnostic(
                        region:          $currentParent,
                        sheetUnit:       $colC,
                        aliases:         $unitAliases,
                        folderFound:     $driveInfo['folder_name'] ?? null,
                        evidenceFound:   $driveInfo['sample_file'] ?? ($driveHasScreenshot ? 'Evidence.png' : null),
                        sheetIsComplete: $sheetIsComplete,
                        internalStatus:  $internalStatus,
                        mode:            $parentMode,
                        matchedKeyword:  $unitAliases[0] ?? null
                    );

                    // ── TWO-WAY WRITE-BACK KE GOOGLE SHEET ───────────
                    // User meminta sync dua arah: Drive ada bukti -> centang 'v', Drive kosong -> hapus centang ''
                    $colLetter = $this->getColumnLetter($colIdx);
                    $rowNum    = $rowIndex + 1; // 1-indexed A1 notation
                    $cellRange = self::SHEET_NAME . "!{$colLetter}{$rowNum}";
                    $newSheetVal = $driveHasScreenshot ? 'v' : '';

                    if ($rawCellVal !== strtolower($newSheetVal)) {
                        $pendingSheetUpdates[$cellRange] = $newSheetVal;
                    }

                    $statusRecords[] = [
                        'semester'             => $semesterName,
                        'bulan'                => $mName,
                        'region'               => $regionName,
                        'unit'                 => $unitName,
                        'sub_unit'             => $driveInfo['sub_unit'] ?? null,
                        'unit_original'        => $unitOriginal,
                        'sheet_status'         => $newSheetVal, // Update local record status sheet juga
                        'drive_status'         => $driveStatusStr,
                        'status'               => $dashboardStatus,
                        'evidence_count'       => $driveInfo['image_file_count'] ?? 0,
                        'sample_evidence_file' => $driveInfo['sample_file'] ?? null,
                        'folder_id'            => $driveInfo['folder_id'] ?? null,
                        'folder_name'          => $driveInfo['folder_name'] ?? null,
                        'folder_path'          => $driveInfo['folder_path'] ?? null,
                        'last_sync'            => $now,
                        'created_at'           => $now,
                        'updated_at'           => $now,
                    ];

                    $processedCount++;

                    // Update progress real-time per unit yang selesai diproses
                    $progressPct = (int) round(($processedCount / $totalTargetUnits) * 100);
                    $syncLog->update([
                        'processed'      => $processedCount,
                        'total'          => $totalTargetUnits,
                        'progress'       => min(99, max(1, $progressPct)),
                        'current_region' => $currentParent,
                        'current_unit'   => $cleanedUnit,
                        'status'         => 'RUNNING',
                    ]);
                }
            }

            // ── RUN BATCH WRITE-BACK KE GOOGLE SHEET API ──────────────────
            if (! empty($pendingSheetUpdates)) {
                try {
                    $this->sheetService->batchUpdate($spreadsheetId, $pendingSheetUpdates);
                    Log::info('DriveMonitoringSyncService: Berhasil melakukan batch write-back ke Google Sheet', [
                        'updatedCellCount' => count($pendingSheetUpdates),
                    ]);
                } catch (\Exception $e) {
                    Log::error('DriveMonitoringSyncService: Gagal batch write-back ke Google Sheet', [
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $totalSlots = max(1, count($statusRecords));

            // 5. High-Speed Batch Upsert ke SQLite
            if (! empty($statusRecords)) {
                $chunks = array_chunk($statusRecords, 100);
                $inserted = 0;
                foreach ($chunks as $chunk) {
                    MonitoringStatus::upsert(
                        $chunk,
                        ['semester', 'bulan', 'region', 'unit'], // Unique composite key
                        [
                            'sub_unit',
                            'unit_original',
                            'sheet_status',
                            'drive_status',
                            'status',
                            'evidence_count',
                            'sample_evidence_file',
                            'folder_id',
                            'folder_name',
                            'folder_path',
                            'last_sync',
                            'updated_at',
                        ]
                    );
                    $inserted += count($chunk);
                    $syncLog->update([
                        'processed' => $inserted,
                        'total'     => $totalSlots,
                        'progress'  => min(99, 90 + (int) round(($inserted / $totalSlots) * 9)),
                    ]);
                }
            }

            // 6. Update Log Sukses (COMPLETED) & Print Summary Detail
            $summaryReport = $this->diagLogger->generateSummaryReport();
            $duration      = round(microtime(true) - $startTime, 2);

            $syncLog->update([
                'finished_at'      => now(),
                'duration_seconds' => $duration,
                'folders_scanned'  => $foldersScanned,
                'files_scanned'    => $filesScanned,
                'complete_count'   => $completeCount,
                'incomplete_count' => $incompleteCount,
                'mismatch_count'   => $mismatchCount,
                'processed'        => $totalSlots,
                'total'            => $totalSlots,
                'progress'         => 100,
                'current_region'   => null,
                'current_unit'     => null,
                'status'           => 'COMPLETED',
            ]);

            Log::info("DriveMonitoringSyncService: Sync berhasil untuk {$semesterName}", [
                'durationSec' => $duration,
                'complete'    => $completeCount,
                'incomplete'  => $incompleteCount,
                'mismatch'    => $mismatchCount,
            ]);

            return $syncLog;

        } catch (\Exception $e) {
            $duration = round(microtime(true) - $startTime, 2);
            $errorMessage = $e->getMessage();

            if (str_contains($errorMessage, '404') || str_contains($errorMessage, 'notFound') || str_contains($errorMessage, 'Requested entity was not found')) {
                $connectedEmail = $this->apiService->getConnectedEmail() ?? 'akun Google Anda';
                $errorMessage = "File Google Sheet (ID: {$spreadsheetId}) tidak ditemukan atau tidak memiliki izin akses untuk {$connectedEmail}. Pastikan file Google Sheet telah dibagikan (Share) ke email tersebut.";
            }

            $syncLog->update([
                'finished_at'      => now(),
                'duration_seconds' => $duration,
                'status'           => 'FAILED',
                'error_message'    => $errorMessage,
            ]);

            Log::error("DriveMonitoringSyncService: Sync gagal untuk {$semesterName}", [
                'error' => $e->getMessage(),
            ]);

            throw new Exception($errorMessage);
        }
    }

    /**
     * Memindai Google Drive HANYA untuk 6 bulan yang berada di bawah semester tertentu.
     *
     * @param  string         $rootId
     * @param  array          $activeMonths  Daftar nama bulan dalam semester (e.g. ['Desember', 'Januari', ...])
     * @param  string         $semesterName
     * @param  callable|null  $onProgress    Callback (processed, total, progressPercentage)
     * @return array{map: array, folders_scanned: int, files_scanned: int}
     */
    private function scanDriveForSemester(
        string $rootId,
        array $activeMonths,
        string $semesterName,
        ?callable $onProgress = null,
        bool $forceRefresh = false
    ): array {
        $client = new GoogleDrive($this->apiService->getClient());

        // 1. Ambil folder langsung di bawah Root
        $response = $client->files->listFiles([
            'q'        => "'{$rootId}' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
            'fields'   => 'files(id, name)',
            'pageSize' => 200,
        ]);

        $rootFolders = [];
        foreach ($response->getFiles() as $file) {
            $rootFolders[$file->getId()] = $file->getName();
        }

        // 2. Filter folder bulan yang HANYA termasuk dalam $activeMonths
        $targetMonthFolders = [];

        foreach ($rootFolders as $folderId => $folderName) {
            $stdMonth = $this->resolveStandardMonthName($folderName);
            if ($stdMonth !== null && in_array($stdMonth, $activeMonths, true)) {
                $targetMonthFolders[$folderId] = [
                    'name'          => $folderName,
                    'standardMonth' => $stdMonth,
                ];
            }
        }

        // 3. Scan rekursif menggunakan DriveLeafScannerService per bulan target
        $map = [];
        $foldersScanned = count($targetMonthFolders);
        $filesScanned   = 0;
        $totalMonthFolders = max(1, count($targetMonthFolders));
        $completedMonths = 0;

        foreach ($targetMonthFolders as $monthFolderId => $mInfo) {
            $monthNameRaw  = $mInfo['name'];
            $standardMonth = $mInfo['standardMonth'];
            $monthPeriod   = $this->resolveMonthPeriod($monthNameRaw);

            // Jalankan scanner rekursif untuk bulan ini
            $leafResults = $this->leafScanner->scanMonthFolder(
                monthFolderId: $monthFolderId,
                monthPeriod:   $monthPeriod,
                forceRefresh:  $forceRefresh
            );

            $monthKey = strtolower(trim($standardMonth));

            foreach ($leafResults as $leaf) {
                $rawRegion = $leaf['region_name'];
                $rawUnit   = $leaf['unit_name'];

                $normRegion = $this->normalizer->normalizeRegion($rawRegion);
                $normUnit   = $this->normalizer->normalize($rawUnit);

                if ($normRegion === '' || $normUnit === '') {
                    continue;
                }

                // Daftarkan ke Folder Registry DB
                if (! empty($leaf['drive_folder_id'])) {
                    $this->registryService->registerFolder(
                        semester: $semesterName,
                        bulan: $standardMonth,
                        region: $rawRegion,
                        originalName: $rawUnit,
                        folderId: $leaf['drive_folder_id'],
                        folderPath: "{$rawRegion} / {$rawUnit}"
                    );
                }

                $imgCount = $leaf['image_file_count'] ?? 0;
                $filesScanned += $imgCount;

                $map[$monthKey][$normRegion][$normUnit] = [
                    'folder_id'        => $leaf['drive_folder_id'],
                    'folder_name'      => $rawUnit,
                    'folder_path'      => "{$rawRegion} / {$rawUnit}",
                    'image_file_count' => $imgCount,
                    'sample_file'      => $leaf['sample_file'] ?? null,
                    'sub_unit'         => $leaf['sub_unit'] ?? null,
                ];
            }

            $completedMonths++;
            if ($onProgress) {
                $totalUnitsPerMonth = $this->structureService->getTotalUnits();
                $totalSemUnits = $totalUnitsPerMonth * count($activeMonths);
                $processed = min($totalSemUnits, $completedMonths * $totalUnitsPerMonth);
                $progressPercent = (int) round(($completedMonths / $totalMonthFolders) * 80) + 10;
                $onProgress($processed, $totalSemUnits, $progressPercent);
            }
        }

        return [
            'map'             => $map,
            'folders_scanned' => $foldersScanned,
            'files_scanned'   => $filesScanned,
        ];
    }

    private function cleanUnitName(string $name): string
    {
        return preg_replace('/^[a-z0-9]+\.\s*/i', '', trim($name));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE: ALIAS-AWARE DRIVE MAP LOOKUP
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Cari entry driveMap menggunakan seluruh alias unit.
     *
     * Urutan pencarian:
     * 1. Lookup persis menggunakan normalized key (existing behavior, paling cepat)
     * 2. Loop seluruh alias → normalize → cari di semua region & unit di driveMap
     *    a. Partial match: normAlias ada di dalam normUnit Drive
     *    b. Reverse partial: normUnit Drive ada di dalam normAlias
     * 3. Jika semua gagal → return null (caller akan coba FolderRegistry)
     *
     * @param  array   $driveMonthMap  driveMap[$monthKey] — map region → unit → driveInfo
     * @param  string  $parentName     Nama parent dari Sheet (untuk match region)
     * @param  string  $sheetUnitName  Nama unit dari Sheet
     * @param  array   $unitAliases    Daftar alias dari UnitAliasService
     * @param  string  $normRegion     Normalized region name
     * @param  string  $normUnit       Normalized unit name (dari UnitNameNormalizerService)
     * @param  string  $monthKey       Bulan lowercase (untuk log)
     * @return array|null              DriveInfo array atau null
     */
    private function findInDriveMapWithAliases(
        array  $driveMonthMap,
        string $parentName,
        string $sheetUnitName,
        array  $unitAliases,
        string $normRegion,
        string $normUnit,
        string $monthKey,
    ): ?array {
        // ── Langkah 1: Lookup persis (existing behavior) ──────────────────────
        $driveInfo = $driveMonthMap[$normRegion][$normUnit] ?? null;
        if ($driveInfo) {
            return $driveInfo;
        }

        // ── Langkah 2: Fallback normalizer isMatch (existing behavior) ────────
        foreach ($driveMonthMap as $regK => $unitsMap) {
            if ($this->normalizer->isMatch($regK, $parentName)) {
                foreach ($unitsMap as $unK => $uInfo) {
                    $folderName = $uInfo['folder_name'] ?? '';
                    if ($this->normalizer->isMatch($unK, $sheetUnitName) || ($folderName !== '' && $this->normalizer->isMatch($folderName, $sheetUnitName))) {
                        return $uInfo;
                    }
                }
            }
        }

        // ── Langkah 3: Alias-based lookup ────────────────────────────────────
        // Siapkan normalized aliases (lowercase, tanpa spasi/dash/underscore)
        $normalizedAliases = array_map(
            fn ($a) => $this->normForDriveMatch($a),
            $unitAliases
        );
        $normalizedAliases = array_filter($normalizedAliases, fn ($a) => $a !== '');

        foreach ($driveMonthMap as $regK => $unitsMap) {
            // Cek apakah region cocok dengan parent (longgar)
            $regionMatches = $this->normalizer->isMatch($regK, $parentName)
                || str_contains($this->normForDriveMatch($regK), $this->normForDriveMatch($parentName))
                || str_contains($this->normForDriveMatch($parentName), $this->normForDriveMatch($regK));

            if (! $regionMatches) {
                continue;
            }

            foreach ($unitsMap as $driveUnitKey => $uInfo) {
                $normDriveUnit  = $this->normForDriveMatch($driveUnitKey);
                $folderName     = $uInfo['folder_name'] ?? '';
                $normFolderName = $this->normForDriveMatch($folderName);

                foreach ($normalizedAliases as $normAlias) {
                    // Partial match: alias ada di dalam nama unit Drive atau folder_name
                    if (str_contains($normDriveUnit, $normAlias) || ($normFolderName !== '' && str_contains($normFolderName, $normAlias))) {
                        return $uInfo;
                    }
                    // Reverse partial: nama unit Drive/folder_name ada di dalam alias
                    if (strlen($normDriveUnit) >= 2 && str_contains($normAlias, $normDriveUnit)) {
                        return $uInfo;
                    }
                    if ($normFolderName !== '' && strlen($normFolderName) >= 2 && str_contains($normAlias, $normFolderName)) {
                        return $uInfo;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Normalisasi string untuk keperluan matching di driveMap.
     * Lowercase, hapus spasi, dash, underscore, titik.
     *
     * @param  string  $s
     * @return string
     */
    private function normForDriveMatch(string $s): string
    {
        $s = strtolower(trim($s));
        $s = preg_replace('/[\s\-_\.]+/', '', $s);
        return $s;
    }

    /**
     * Log hasil pencarian alias untuk satu unit dalam satu bulan.
     * Hanya log jika NOT FOUND atau ada alias yang digunakan (alias match).
     *
     * @param  string      $unitName
     * @param  array       $aliases
     * @param  array|null  $driveInfo
     * @param  string      $monthName
     * @param  string      $parentName
     */
    private function logAliasSearchResult(
        string $unitName,
        array  $aliases,
        ?array $driveInfo,
        string $monthName,
        string $parentName,
    ): void {
        // Hanya log jika: (1) tidak ditemukan, atau (2) nama alias ≠ nama asli (alias benar-benar dipakai)
        $found        = $driveInfo !== null && ($driveInfo['image_file_count'] ?? 0) >= 1;
        $hasAliases   = count($aliases) > 1 || (count($aliases) === 1 && strtoupper(trim($unitName)) !== strtoupper($aliases[0]));

        if (! $found || $hasAliases) {
            Log::info('[UnitAlias] Pencarian evidence', [
                'bulan'        => $monthName,
                'parent'       => $parentName,
                'unit'         => $unitName,
                'aliases'      => implode(', ', $aliases),
                'folder_found' => $driveInfo ? ($driveInfo['folder_name'] ?? '-') : 'TIDAK DITEMUKAN',
                'evidence'     => $driveInfo ? ($driveInfo['sample_file'] ?? '-') : '-',
                'img_count'    => $driveInfo['image_file_count'] ?? 0,
                'status'       => $found ? 'FOUND' : 'NOT FOUND',
            ]);
        }
    }

    private function resolveStandardMonthName(string $folderName): ?string
    {
        $folderNameLower = strtolower($folderName);

        $textPatterns = [
            'Januari'   => ['januari', 'jan'],
            'Februari'  => ['februari', 'feb'],
            'Maret'     => ['maret', 'mar'],
            'April'     => ['april', 'apr'],
            'Mei'       => ['mei'],
            'Juni'      => ['juni', 'jun'],
            'Juli'      => ['juli', 'jul'],
            'Agustus'   => ['agustus', 'agu', 'agt'],
            'September' => ['september', 'sept', 'sep'],
            'Oktober'   => ['oktober', 'okt'],
            'November'  => ['november', 'nov'],
            'Desember'  => ['desember', 'des'],
        ];

        foreach ($textPatterns as $monthName => $searches) {
            foreach ($searches as $search) {
                if (str_contains($folderNameLower, $search)) {
                    return $monthName;
                }
            }
        }

        $numericPatterns = [
            'Oktober'   => ['10.'],
            'November'  => ['11.'],
            'Desember'  => ['12.'],
            'Januari'   => ['1.'],
            'Februari'  => ['2.'],
            'Maret'     => ['3.'],
            'April'     => ['4.'],
            'Mei'       => ['5.'],
            'Juni'      => ['6.'],
            'Juli'      => ['7.'],
            'Agustus'   => ['8.'],
            'September' => ['9.'],
        ];

        foreach ($numericPatterns as $monthName => $searches) {
            foreach ($searches as $search) {
                if (str_contains($folderNameLower, $search)) {
                    return $monthName;
                }
            }
        }

        return null;
    }

    private function resolveMonthPeriod(string $folderName): string
    {
        preg_match('/\b(20\d{2})\b/', $folderName, $yearMatch);
        $year = $yearMatch[1] ?? now()->format('Y');

        $monthNumbers = [
            'januari' => '01', 'jan' => '01',
            'februari' => '02', 'feb' => '02',
            'maret' => '03', 'mar' => '03',
            'april' => '04', 'apr' => '04',
            'mei' => '05',
            'juni' => '06', 'jun' => '06',
            'juli' => '07', 'jul' => '07',
            'agustus' => '08', 'agu' => '08', 'agt' => '08',
            'september' => '09', 'sept' => '09', 'sep' => '09',
            'oktober' => '10', 'okt' => '10',
            'november' => '11', 'nov' => '11',
            'desember' => '12', 'des' => '12',
        ];

        $lower = strtolower($folderName);
        foreach ($monthNumbers as $keyword => $num) {
            if (str_contains($lower, $keyword)) {
                return "{$year}-{$num}";
            }
        }

        if (preg_match('/^(\d{1,2})\./', $folderName, $m)) {
            $monthNum = str_pad($m[1], 2, '0', STR_PAD_LEFT);
            return "{$year}-{$monthNum}";
        }

        return now()->format('Y-m');
    }

    private function getColumnLetter(int $colIndex): string
    {
        $letter = '';
        while ($colIndex >= 0) {
            $letter = chr(($colIndex % 26) + 65) . $letter;
            $colIndex = intval($colIndex / 26) - 1;
        }
        return $letter;
    }
}
