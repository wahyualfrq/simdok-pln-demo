<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FolderGenerationLog;
use App\Services\Google\GoogleApiService;
use App\Services\Google\GoogleDriveService;
use Exception;
use Illuminate\Support\Facades\Log;

class FolderGeneratorService
{
    public function __construct(
        private readonly GoogleApiService $apiService,
        private readonly GoogleDriveService $driveService,
        private readonly FolderStructureService $structureService,
        private readonly CacheService $cacheService,
    ) {}

    /**
     * Mendeteksi level konteks lokasi folder aktif (ROOT, MONTH, PARENT, UNIT).
     *
     * @param  string  $folderId
     * @return array
     */
    public function resolveContextInfo(string $folderId): array
    {
        $settings = $this->apiService->getSettings();
        $rootId   = $settings['root_folder_id'] ?? null;

        if (empty($rootId)) {
            return [
                'context_level' => 'ROOT',
                'can_generate'  => false,
                'message'       => 'Root Folder Google Drive belum dikonfigurasi. Silakan atur di Pengaturan Google.',
            ];
        }

        if ($folderId === $rootId) {
            return [
                'context_level' => 'ROOT',
                'can_generate'  => false,
                'message'       => 'Silakan masuk ke folder bulan terlebih dahulu.',
            ];
        }

        $currentFolder = $this->driveService->getFolder($folderId);
        $parents       = $currentFolder['parents'] ?? [];
        $parentId      = ! empty($parents) ? $parents[0] : null;

        // Parent is Root -> Month Folder
        if ($parentId === $rootId) {
            return [
                'context_level' => 'MONTH',
                'can_generate'  => true,
                'folder'        => $currentFolder,
                'location_path' => $currentFolder['name'],
            ];
        }

        // Check if current folder itself matches one of the 12 official parents
        $structure = $this->structureService->getStructure();
        $normCurrentName = $this->normalizeName($currentFolder['name']);
        $matchedOfficialParent = null;

        foreach ($structure as $officialParentName => $pInfo) {
            if ($this->normalizeName($officialParentName) === $normCurrentName) {
                $matchedOfficialParent = $officialParentName;
                break;
            }
            foreach ($pInfo['aliases'] ?? [] as $alias) {
                if ($this->normalizeName($alias) === $normCurrentName) {
                    $matchedOfficialParent = $officialParentName;
                    break 2;
                }
            }
        }

        if ($matchedOfficialParent) {
            // Parent level folder
            $parentFolderMeta = $parentId ? $this->driveService->getFolder($parentId) : null;
            $locationPath = ($parentFolderMeta ? $parentFolderMeta['name'] . ' > ' : '') . $currentFolder['name'];

            $parentConfig = $structure[$matchedOfficialParent] ?? null;
            $hasUnits = ! empty($parentConfig['has_subfolder']) && ! empty($parentConfig['units']);

            return [
                'context_level'          => 'PARENT',
                'can_generate'           => $hasUnits,
                'message'                => $hasUnits ? null : 'Folder ini tidak memiliki subfolder unit.',
                'folder'                 => $currentFolder,
                'official_parent_name'   => $matchedOfficialParent,
                'location_path'          => $locationPath,
            ];
        }

        // If parent's parent is Root -> Parent Level Folder
        if ($parentId) {
            $parentFolderMeta = $this->driveService->getFolder($parentId);
            $grandParents = $parentFolderMeta['parents'] ?? [];
            $grandParentId = ! empty($grandParents) ? $grandParents[0] : null;

            if ($grandParentId === $rootId) {
                // Current folder is under Month -> PARENT level
                return [
                    'context_level' => 'PARENT',
                    'can_generate'  => true,
                    'folder'        => $currentFolder,
                    'location_path' => $parentFolderMeta['name'] . ' > ' . $currentFolder['name'],
                ];
            }
        }

        // Level 3 or higher -> Unit Subfolder / Leaf
        return [
            'context_level' => 'UNIT',
            'can_generate'  => false,
            'message'       => 'Folder ini sudah merupakan folder unit akhir. Tidak ada struktur yang perlu dibuat.',
            'location_path' => $currentFolder['name'],
        ];
    }

    /**
     * Menghasilkan data preview lokasi dan daftar folder (Sudah Ada vs Akan Dibuat).
     *
     * @param  string  $folderId
     * @return array
     */
    public function generatePreviewData(string $folderId): array
    {
        $contextInfo = $this->resolveContextInfo($folderId);

        if (! $contextInfo['can_generate']) {
            return [
                'can_generate'  => false,
                'context_level' => $contextInfo['context_level'],
                'message'       => $contextInfo['message'] ?? 'Tidak dapat melakukan generate folder.',
                'location_path' => $contextInfo['location_path'] ?? null,
                'items'         => [],
                'total_folders' => 0,
                'existing_count'=> 0,
                'new_count'     => 0,
            ];
        }

        $structure = $this->structureService->getStructure();
        $items = [];
        $existingCount = 0;
        $newCount = 0;

        if ($contextInfo['context_level'] === 'MONTH') {
            // CASE 2: Month Folder -> Preview 12 Parents & 59 Units
            $existingParents = $this->driveService->listFolders($folderId);
            $existingParentMap = [];
            foreach ($existingParents as $pFolder) {
                $norm = $this->normalizeName($pFolder['name']);
                $existingParentMap[$norm] = $pFolder;
            }

            foreach ($structure as $officialParentName => $parentInfo) {
                $normParent = $this->normalizeName($officialParentName);
                $parentFolder = $existingParentMap[$normParent] ?? null;

                if (! $parentFolder) {
                    foreach ($parentInfo['aliases'] ?? [] as $alias) {
                        $normAlias = $this->normalizeName($alias);
                        if (isset($existingParentMap[$normAlias])) {
                            $parentFolder = $existingParentMap[$normAlias];
                            break;
                        }
                    }
                }

                $parentExists = (bool) $parentFolder;
                if ($parentExists) {
                    $existingCount++;
                } else {
                    $newCount++;
                }

                $items[] = [
                    'name'        => $officialParentName,
                    'type'        => 'parent',
                    'exists'      => $parentExists,
                    'parent_name' => null,
                ];

                if (! empty($parentInfo['has_subfolder'])) {
                    $units = $parentInfo['units'] ?? [];
                    $existingUnitMap = [];

                    if ($parentExists) {
                        $existingUnits = $this->driveService->listFolders($parentFolder['id']);
                        foreach ($existingUnits as $uFolder) {
                            $existingUnitMap[$this->normalizeName($uFolder['name'])] = $uFolder;
                        }
                    }

                    foreach ($units as $officialUnitName) {
                        $normTarget = $this->normalizeName($officialUnitName);
                        $cleanTarget = $this->normalizeName(preg_replace('/^[a-z0-9]+\.\s*/i', '', $officialUnitName));
                        $unitExists = false;

                        if ($parentExists) {
                            if (isset($existingUnitMap[$normTarget]) || isset($existingUnitMap[$cleanTarget])) {
                                $unitExists = true;
                            } else {
                                foreach ($existingUnitMap as $existNorm => $existItem) {
                                    if (str_contains($existNorm, $cleanTarget) || str_contains($cleanTarget, $existNorm)) {
                                        $unitExists = true;
                                        break;
                                    }
                                }
                            }
                        }

                        if ($unitExists) {
                            $existingCount++;
                        } else {
                            $newCount++;
                        }

                        $items[] = [
                            'name'        => $officialUnitName,
                            'type'        => 'unit',
                            'exists'      => $unitExists,
                            'parent_name' => $officialParentName,
                        ];
                    }
                }
            }
        } elseif ($contextInfo['context_level'] === 'PARENT') {
            // CASE 3: Parent Folder -> Preview HANYA subfolder unit di bawah Parent ini
            $officialParentName = $contextInfo['official_parent_name'] ?? null;
            if (! $officialParentName) {
                // Find matching parent from structure
                $folderMeta = $contextInfo['folder'];
                $normCurrent = $this->normalizeName($folderMeta['name']);
                foreach ($structure as $pName => $pInfo) {
                    if ($this->normalizeName($pName) === $normCurrent) {
                        $officialParentName = $pName;
                        break;
                    }
                }
            }

            $parentConfig = $structure[$officialParentName] ?? null;
            $units = $parentConfig['units'] ?? [];

            $existingUnits = $this->driveService->listFolders($folderId);
            $existingUnitMap = [];
            foreach ($existingUnits as $uFolder) {
                $existingUnitMap[$this->normalizeName($uFolder['name'])] = $uFolder;
            }

            foreach ($units as $officialUnitName) {
                $normTarget = $this->normalizeName($officialUnitName);
                $cleanTarget = $this->normalizeName(preg_replace('/^[a-z0-9]+\.\s*/i', '', $officialUnitName));
                $unitExists = false;

                if (isset($existingUnitMap[$normTarget]) || isset($existingUnitMap[$cleanTarget])) {
                    $unitExists = true;
                } else {
                    foreach ($existingUnitMap as $existNorm => $existItem) {
                        if (str_contains($existNorm, $cleanTarget) || str_contains($cleanTarget, $existNorm)) {
                            $unitExists = true;
                            break;
                        }
                    }
                }

                if ($unitExists) {
                    $existingCount++;
                } else {
                    $newCount++;
                }

                $items[] = [
                    'name'        => $officialUnitName,
                    'type'        => 'unit',
                    'exists'      => $unitExists,
                    'parent_name' => $officialParentName,
                ];
            }
        }

        return [
            'can_generate'   => true,
            'context_level'  => $contextInfo['context_level'],
            'location_path'  => $contextInfo['location_path'],
            'total_folders'  => count($items),
            'existing_count' => $existingCount,
            'new_count'      => $newCount,
            'items'          => $items,
        ];
    }

    /**
     * Mengoperasikan Auto Generate Folder secara Context-Aware (CASE 2: Month, CASE 3: Parent).
     * Pembuatan bersifat idempotent.
     *
     * @param  string    $folderId
     * @param  int|null  $logId
     * @return FolderGenerationLog
     * @throws Exception
     */
    public function generateContextAwareFolders(string $folderId, ?int $logId = null): FolderGenerationLog
    {
        @set_time_limit(600);
        @ini_set('max_execution_time', '600');

        $startTime = microtime(true);
        $contextInfo = $this->resolveContextInfo($folderId);

        if (! $contextInfo['can_generate']) {
            throw new Exception($contextInfo['message'] ?? 'Generasi folder tidak diizinkan pada lokasi ini.');
        }

        $log = $logId ? FolderGenerationLog::find($logId) : null;
        if (! $log) {
            $log = FolderGenerationLog::create([
                'folder_id'         => $folderId,
                'context_level'     => $contextInfo['context_level'],
                'status'            => 'RUNNING',
                'progress'          => 5,
                'processed_folders' => 0,
                'total_folders'     => 0,
                'current_folder'    => "5% Mendeteksi posisi folder ({$contextInfo['context_level']})",
            ]);
        } else {
            $log->update([
                'folder_id'         => $folderId,
                'context_level'     => $contextInfo['context_level'],
                'status'            => 'RUNNING',
                'progress'          => 5,
                'processed_folders' => 0,
                'current_folder'    => "5% Mendeteksi posisi folder ({$contextInfo['context_level']})",
            ]);
        }

        Log::info("[AUTO-GENERATE CONTEXT-AWARE] Memulai proses generate", [
            'folderId'      => $folderId,
            'context_level' => $contextInfo['context_level'],
            'logId'         => $log->id,
        ]);

        try {
            // STEP 2 (15%): Load Master Template
            $structure = $this->structureService->getStructure();
            $log->update([
                'progress'       => 15,
                'current_folder' => '15% Membaca template master config/folder_structure.php',
            ]);

            // STEP 3 (30%): Memeriksa folder yang sudah ada & hitung total
            $preview = $this->generatePreviewData($folderId);
            $totalFolders  = $preview['total_folders'];
            $existingCount = $preview['existing_count'];
            $newCount      = $preview['new_count'];

            $log->update([
                'total_folders'     => $totalFolders,
                'existing_count'    => $existingCount,
                'new_count'         => $newCount,
                'progress'          => 30,
                'current_folder'    => "30% Memeriksa folder (Ada: {$existingCount}, Baru: {$newCount})",
            ]);

            // STEP 4 (55%): Membuat folder baru (Idempotent Engine)
            $processedCount = 0;
            $createdCount   = 0;

            if ($contextInfo['context_level'] === 'MONTH') {
                // CASE 2: Month Folder -> Iterasi 12 Parent + 59 Unit
                $existingParents = $this->driveService->listFolders($folderId);
                $existingParentMap = [];
                foreach ($existingParents as $pFolder) {
                    $existingParentMap[$this->normalizeName($pFolder['name'])] = $pFolder;
                }

                $parentMap = [];
                foreach ($structure as $officialParentName => $parentInfo) {
                    $normParent = $this->normalizeName($officialParentName);
                    $parentFolder = $existingParentMap[$normParent] ?? null;

                    if (! $parentFolder) {
                        foreach ($parentInfo['aliases'] ?? [] as $alias) {
                            $normAlias = $this->normalizeName($alias);
                            if (isset($existingParentMap[$normAlias])) {
                                $parentFolder = $existingParentMap[$normAlias];
                                break;
                            }
                        }
                    }

                    if (! $parentFolder) {
                        $parentFolder = $this->driveService->createFolder($officialParentName, $folderId);
                        $createdCount++;
                        $this->cacheService->invalidateFolderList($folderId);
                    }

                    $parentMap[$officialParentName] = $parentFolder;
                    $processedCount++;

                    $log->update([
                        'processed_folders' => $processedCount,
                        'progress'          => 30 + (int) round(($processedCount / max(1, $totalFolders)) * 25),
                        'current_folder'    => "55% Memuat Parent: {$officialParentName}",
                    ]);
                }

                foreach ($structure as $officialParentName => $parentInfo) {
                    if (empty($parentInfo['has_subfolder'])) continue;

                    $parentFolder = $parentMap[$officialParentName] ?? null;
                    if (! $parentFolder) continue;

                    $units = $parentInfo['units'] ?? [];
                    if (empty($units)) continue;

                    $existingUnits = $this->driveService->listFolders($parentFolder['id']);
                    $existingUnitMap = [];
                    foreach ($existingUnits as $uFolder) {
                        $existingUnitMap[$this->normalizeName($uFolder['name'])] = $uFolder;
                    }

                    foreach ($units as $officialUnitName) {
                        $normTarget = $this->normalizeName($officialUnitName);
                        $cleanTarget = $this->normalizeName(preg_replace('/^[a-z0-9]+\.\s*/i', '', $officialUnitName));
                        $unitFolder = null;

                        if (isset($existingUnitMap[$normTarget])) {
                            $unitFolder = $existingUnitMap[$normTarget];
                        } elseif (isset($existingUnitMap[$cleanTarget])) {
                            $unitFolder = $existingUnitMap[$cleanTarget];
                        } else {
                            foreach ($existingUnitMap as $existNorm => $existItem) {
                                if (str_contains($existNorm, $cleanTarget) || str_contains($cleanTarget, $existNorm)) {
                                    $unitFolder = $existItem;
                                    break;
                                }
                            }
                        }

                        if (! $unitFolder) {
                            $unitFolder = $this->driveService->createFolder($officialUnitName, $parentFolder['id']);
                            $createdCount++;
                            $this->cacheService->invalidateFolderList($parentFolder['id']);
                        }

                        $processedCount++;

                        $log->update([
                            'processed_folders' => $processedCount,
                            'progress'          => 30 + (int) round(($processedCount / max(1, $totalFolders)) * 25),
                            'current_folder'    => "55% Memuat Unit: {$officialParentName} / {$officialUnitName}",
                        ]);
                    }
                }
            } elseif ($contextInfo['context_level'] === 'PARENT') {
                // CASE 3: Parent Folder -> HANYA unit subfolder di bawah Parent ini
                $officialParentName = $contextInfo['official_parent_name'] ?? null;
                if (! $officialParentName) {
                    $folderMeta = $contextInfo['folder'];
                    $normCurrent = $this->normalizeName($folderMeta['name']);
                    foreach ($structure as $pName => $pInfo) {
                        if ($this->normalizeName($pName) === $normCurrent) {
                            $officialParentName = $pName;
                            break;
                        }
                    }
                }

                $parentConfig = $structure[$officialParentName] ?? null;
                $units = $parentConfig['units'] ?? [];

                $existingUnits = $this->driveService->listFolders($folderId);
                $existingUnitMap = [];
                foreach ($existingUnits as $uFolder) {
                    $existingUnitMap[$this->normalizeName($uFolder['name'])] = $uFolder;
                }

                foreach ($units as $officialUnitName) {
                    $normTarget = $this->normalizeName($officialUnitName);
                    $cleanTarget = $this->normalizeName(preg_replace('/^[a-z0-9]+\.\s*/i', '', $officialUnitName));
                    $unitFolder = null;

                    if (isset($existingUnitMap[$normTarget])) {
                        $unitFolder = $existingUnitMap[$normTarget];
                    } elseif (isset($existingUnitMap[$cleanTarget])) {
                        $unitFolder = $existingUnitMap[$cleanTarget];
                    } else {
                        foreach ($existingUnitMap as $existNorm => $existItem) {
                            if (str_contains($existNorm, $cleanTarget) || str_contains($cleanTarget, $existNorm)) {
                                $unitFolder = $existItem;
                                break;
                            }
                        }
                    }

                    if (! $unitFolder) {
                        $unitFolder = $this->driveService->createFolder($officialUnitName, $folderId);
                        $createdCount++;
                        $this->cacheService->invalidateFolderList($folderId);
                    }

                    $processedCount++;

                    $log->update([
                        'processed_folders' => $processedCount,
                        'progress'          => 30 + (int) round(($processedCount / max(1, $totalFolders)) * 25),
                        'current_folder'    => "55% Memuat Unit: {$officialUnitName}",
                    ]);
                }
            }

            // STEP 5 (85%): Refresh Cache System
            $log->update([
                'progress'       => 85,
                'current_folder' => '85% Refresh cache sistem SIMDOK...',
            ]);

            $this->cacheService->invalidateAll();

            // STEP 6 (100%): Selesai
            $duration = round(microtime(true) - $startTime, 2);

            $log->update([
                'processed_folders' => $totalFolders,
                'new_count'         => $newCount,
                'existing_count'    => $existingCount,
                'duration_seconds'  => $duration,
                'progress'          => 100,
                'current_folder'    => 'Generate selesai',
                'status'            => 'COMPLETED',
            ]);

            Log::info("[AUTO-GENERATE CONTEXT-AWARE] Berhasil membuat folder", [
                'folderId'     => $folderId,
                'totalFolders' => $totalFolders,
                'duration'     => $duration,
            ]);

            return $log;

        } catch (Exception $e) {
            $log->update([
                'status'        => 'FAILED',
                'error_message' => $e->getMessage(),
            ]);

            Log::error("[AUTO-GENERATE CONTEXT-AWARE] Gagal membuat folder", [
                'folderId' => $folderId,
                'error'    => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Mereplikasi 100% struktur folder resmi PLN ke Google Drive secara idempotent (Fallback Legacy).
     */
    public function generateSemesterMonthFolders(string $semester, string $bulan, ?int $logId = null): FolderGenerationLog
    {
        @set_time_limit(600);
        @ini_set('max_execution_time', '600');

        $log = $logId ? FolderGenerationLog::find($logId) : null;
        if (! $log) {
            $log = FolderGenerationLog::create([
                'semester'          => $semester,
                'bulan'             => $bulan,
                'status'            => 'RUNNING',
                'progress'          => 5,
                'processed_folders' => 0,
                'total_folders'     => 60,
                'current_folder'    => "Memeriksa folder bulan: {$bulan}",
            ]);
        } else {
            $log->update([
                'status'            => 'RUNNING',
                'progress'          => 5,
                'processed_folders' => 0,
                'current_folder'    => "Memeriksa folder bulan: {$bulan}",
            ]);
        }

        try {
            $settings = $this->apiService->getSettings();
            $rootId   = $settings['root_folder_id'] ?? null;

            if (empty($rootId)) {
                throw new Exception('Root Folder Google Drive belum dikonfigurasi. Silakan pilih Root Folder di Pengaturan Google.');
            }

            $monthFolder = $this->resolveOrCreateMonthFolder($rootId, $bulan);
            $monthFolderId = $monthFolder['id'];
            
            return $this->generateContextAwareFolders($monthFolderId, $log->id);
        } catch (Exception $e) {
            $log->update([
                'status'        => 'FAILED',
                'error_message' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Resolusi folder bulan di bawah Root Folder. Buat baru jika belum ada.
     *
     * @param  string  $rootId
     * @param  string  $bulan
     * @return array
     */
    private function resolveOrCreateMonthFolder(string $rootId, string $bulan): array
    {
        $existingFolders = $this->driveService->listFolders($rootId);
        $monthNameLower = strtolower(trim($bulan));

        foreach ($existingFolders as $folder) {
            $nameLower = strtolower(trim($folder['name']));
            if (
                $nameLower === $monthNameLower ||
                str_contains($nameLower, $monthNameLower)
            ) {
                return $folder;
            }
        }

        // Buat folder bulan baru jika belum ada
        return $this->driveService->createFolder($bulan, $rootId);
    }

    /**
     * Normalisasi nama untuk pencocokan string bebas spasi/kasus.
     *
     * @param  string  $name
     * @return string
     */
    private function normalizeName(string $name): string
    {
        $s = strtolower(trim($name));
        $s = preg_replace('/^[a-z0-9]+\.\s*/i', '', $s); // Hapus a. b. c. 01. 1.
        $s = preg_replace('/[^a-z0-9]/i', '', $s);
        return $s;
    }
}
