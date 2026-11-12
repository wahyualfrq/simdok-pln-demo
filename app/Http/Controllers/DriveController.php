<?php

namespace App\Http\Controllers;

use App\Exceptions\GoogleAuthException;
use App\Http\Requests\CreateFolderRequest;
use App\Http\Requests\UploadFileRequest;
use App\Http\Requests\RenameItemRequest;
use App\Http\Requests\DeleteItemRequest;
use App\Services\CacheService;
use App\Services\Google\GoogleOAuthService;
use App\Services\Google\GoogleDriveService;
use App\Services\Google\GoogleSheetService;
use App\Services\MonitoringService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;

/**
 * DriveController
 *
 * Mengelola navigasi Drive Explorer.
 * Menerima request, memanggil GoogleDriveService, dan mengembalikan view/redirect.
 * Tidak berisi business logic — semua delegasi ke service.
 * Cache di-invalidate secara event-driven pada setiap operasi mutasi.
 *
 * Sesuai dengan: 05_DRIVE_EXPLORER.md & 08_CODING_STANDARD.md
 */
class DriveController extends Controller
{
    public function __construct(
        private readonly GoogleOAuthService $oAuthService,
        private readonly GoogleDriveService $driveService,
        private readonly GoogleSheetService $sheetService,
        private readonly CacheService $cacheService,
        private readonly MonitoringService $monitoringService,
        private readonly \App\Services\UnitNameNormalizerService $normalizer,
        private readonly \App\Services\UnitAliasService $aliasService,
    ) {}

    /**
     * Cari folder unit secara rekursif dari Google Drive (hingga max 4 level).
     * Menggunakan alias & normalizer matching untuk menemukan folder dengan variasi nama (misal: "MUP3 OGAN ILIR" -> "UP3 OGI", "TL LAKDAN" -> "LAKSDAN").
     *
     * @param  string  $name
     * @param  string  $parentId
     * @param  int     $maxDepth
     * @return array|null
     */
    private function findFolderRecursive(string $name, string $parentId, int $maxDepth = 4): ?array
    {
        if ($maxDepth <= 0) {
            return null;
        }

        try {
            $folders = $this->driveService->listFolders($parentId);
            $target = strtolower(trim($name));
            $cleanTarget = preg_replace('/^[a-z0-9]+\.\s*/i', '', $target);

            // Ambil daftar alias unit dari UnitAliasService
            $aliases = array_unique(array_merge(
                [$name, $cleanTarget],
                $this->aliasService->getAliases($name),
                $this->aliasService->getAliases($cleanTarget)
            ));
            $cleanAliases = array_filter(array_map(
                fn($a) => strtolower(trim(preg_replace('/^[a-z0-9]+\.\s*/i', '', $a))),
                $aliases
            ));

            // Pass 1: Exact Match (Nama persis atau clean persis)
            foreach ($folders as $folder) {
                $folderNameLower = strtolower(trim($folder['name']));
                $cleanFolderName = preg_replace('/^[a-z0-9]+\.\s*/i', '', $folderNameLower);

                if ($folderNameLower === $target || $cleanFolderName === $cleanTarget) {
                    return $folder;
                }
            }

            // Pass 2: Alias Match & Normalizer Match
            foreach ($folders as $folder) {
                $folderName = $folder['name'];
                $folderNameLower = strtolower(trim($folderName));
                $cleanFolderName = preg_replace('/^[a-z0-9]+\.\s*/i', '', $folderNameLower);

                if ($this->normalizer->isMatch($folderName, $name) || $this->normalizer->isMatch($cleanFolderName, $cleanTarget)) {
                    return $folder;
                }

                foreach ($cleanAliases as $alias) {
                    if ($alias !== '' && ($cleanFolderName === $alias || $this->aliasService->matchesAlias($alias, $folderName))) {
                        return $folder;
                    }
                }
            }

            // Pass 3: Substring Match
            foreach ($folders as $folder) {
                $folderNameLower = strtolower(trim($folder['name']));
                $cleanFolderName = preg_replace('/^[a-z0-9]+\.\s*/i', '', $folderNameLower);

                if (strlen($cleanTarget) >= 3 && (str_contains($cleanFolderName, $cleanTarget) || str_contains($cleanTarget, $cleanFolderName))) {
                    return $folder;
                }

                foreach ($cleanAliases as $alias) {
                    if (strlen($alias) >= 3 && (str_contains($cleanFolderName, $alias) || str_contains($alias, $cleanFolderName))) {
                        return $folder;
                    }
                }
            }

            // Pass 4: Telusuri subfolder rekursif
            foreach ($folders as $folder) {
                $found = $this->findFolderRecursive($name, $folder['id'], $maxDepth - 1);
                if ($found) {
                    return $found;
                }
            }
        } catch (Exception $e) {
            // Abaikan error
        }

        return null;
    }

    /**
     * Menampilkan root Drive Explorer.
     * Membuka root folder Google Drive yang dikonfigurasi.
     *
     * @return View|RedirectResponse
     */
    public function index(): View|RedirectResponse
    {
        $settings = $this->oAuthService->getSettings();
        $rootFolderId = $settings['root_folder_id'] ?? null;

        if (empty($rootFolderId)) {
            return view('drive.index', [
                'folders'    => [],
                'files'      => [],
                'breadcrumb' => [],
                'folderId'   => null,
                'error'      => 'Root Folder belum dikonfigurasi. Silakan hubungkan Google dan pilih Root Folder terlebih dahulu.',
            ]);
        }

        return $this->show($rootFolderId);
    }

    /**
     * Menampilkan isi folder berdasarkan Folder ID.
     * Breadcrumb dibangun dari path navigasi.
     *
     * @param  string  $folderId  Google Drive Folder ID
     * @return View
     */
    public function show(string $folderId): View|RedirectResponse
    {
        // Jika folderId adalah composite ID Mode 2 (misal: ID_hash), ekstrak ID Drive aslinya
        if (preg_match('/^([a-zA-Z0-9\-_]+)_[a-f0-9]{32}$/', $folderId, $matches)) {
            $folderId = $matches[1];
        }

        // Handles header refresh button (?refresh=...)
        if (request()->query('refresh')) {
            $this->cacheService->invalidateFolderList($folderId);
            $this->cacheService->invalidateFolderMeta($folderId);
            $this->cacheService->invalidateAll();
        }

        try {
            $contents    = $this->driveService->listContents($folderId);
            $currentFolder = $this->driveService->getFolder($folderId);
            $breadcrumb  = $this->buildBreadcrumb($currentFolder);

            $folderStatus = null;
            $isLeafFolder = false;

            $settings     = $this->oAuthService->getSettings();
            $rootFolderId = $settings['root_folder_id'] ?? null;

            // Status validasi HANYA ditampilkan pada folder terakhir (leaf folder tanpa subfolder lagi)
            $hasSubfolders = ! empty($contents['folders']);

            if (! $hasSubfolders && $folderId !== $rootFolderId) {
                $cacheRecord = \App\Models\MonitoringFolderCache::where('drive_folder_id', $folderId)->first();
                if ($cacheRecord && $cacheRecord->is_leaf) {
                    $isLeafFolder = true;
                } elseif (count($breadcrumb) >= 3) {
                    $isLeafFolder = true;
                }
            }

            if ($isLeafFolder) {
                // Realtime sync status untuk folder unit ini
                $this->syncFolderRealtimeStatus($folderId);

                $statusRecord = \App\Models\MonitoringStatus::where('folder_id', $folderId)
                    ->orWhere('folder_id', 'like', $folderId . '_%')
                    ->first();

                $hasFiles = ! empty($contents['files']);
                if (! $hasFiles) {
                    \Illuminate\Support\Facades\Cache::forget("drive.folder.manual_status.{$folderId}");
                    $folderStatus = 'incomplete';
                } else {
                    $manualStatus = \Illuminate\Support\Facades\Cache::get("drive.folder.manual_status.{$folderId}");
                    if ($manualStatus) {
                        $folderStatus = strtolower($manualStatus);
                    } elseif ($statusRecord) {
                        $folderStatus = strtolower($statusRecord->status);
                    } else {
                        $folderStatus = 'pending';
                    }
                }
            }

            return view('drive.index', [
                'folders'       => $contents['folders'],
                'files'         => $contents['files'],
                'breadcrumb'    => $breadcrumb,
                'folderId'      => $folderId,
                'currentFolder' => $currentFolder,
                'folderStatus'  => $folderStatus,
                'isLeafFolder'  => $isLeafFolder,
                'error'         => null,
            ]);
        } catch (GoogleAuthException $e) {
            return view('drive.index', [
                'folders'       => [],
                'files'         => [],
                'breadcrumb'    => [],
                'folderId'      => $folderId,
                'currentFolder' => null,
                'folderStatus'  => null,
                'error'         => 'Belum terhubung ke Google. Silakan hubungkan akun Google Anda terlebih dahulu.',
            ]);
        } catch (Exception $e) {
            $code = (int) $e->getCode();
            $msg = strtolower($e->getMessage());
            
            if ($code === 404 || str_contains($msg, 'not found') || str_contains($msg, 'notfound')) {
                $error = 'Folder tidak ditemukan atau telah dihapus di Google Drive.';
            } elseif ($code === 403 || str_contains($msg, 'access') || str_contains($msg, 'permission') || str_contains($msg, 'forbidden')) {
                $error = 'Anda tidak lagi memiliki akses ke folder ini.';
            } else {
                $error = 'Gagal membaca folder. Silakan coba lagi atau periksa koneksi Google Drive.';
            }

            return view('drive.index', [
                'folders'       => [],
                'files'         => [],
                'breadcrumb'    => [],
                'folderId'      => $folderId,
                'currentFolder' => null,
                'folderStatus'  => null,
                'error'         => $error,
            ]);
        }
    }

    /**
     * Membuat folder baru di dalam folder yang sedang aktif.
     *
     * @param  CreateFolderRequest  $request
     * @return RedirectResponse
     */
    public function createFolder(CreateFolderRequest $request): RedirectResponse
    {
        $folderId   = $request->input('folder_id');
        $folderName = $request->input('folder_name');

        try {
            $this->driveService->createFolder($folderName, $folderId);

            // Invalidate folder list cache untuk parent folder ini
            $this->cacheService->invalidateFolderList($folderId);

            return redirect()
                ->route('drive.show', $folderId)
                ->with('success', "Folder \"{$folderName}\" berhasil dibuat.");
        } catch (Exception $e) {
            return redirect()
                ->route('drive.show', $folderId)
                ->with('error', 'Gagal membuat folder. Silakan coba lagi.');
        }
    }

    /**
     * Mengunggah file ke folder yang sedang aktif.
     * File langsung diteruskan ke Google Drive tanpa disimpan di server.
     *
     * @param  UploadFileRequest  $request
     * @return RedirectResponse
     */
    public function uploadFile(UploadFileRequest $request): RedirectResponse
    {
        $folderId = $request->input('folder_id');
        $file     = $request->file('file');

        $treatment = $request->input('upload_treatment'); // revision or addition
        $statusOption = $request->input('upload_status'); // pending or finalize

        try {
            $this->driveService->uploadFile($folderId, $file);

            // 1. Invalidate cache LANGSUNG agar Drive API membaca file baru
            $this->cacheService->invalidateAfterFileChange($folderId);

            // 2. Simpan status sesuai pilihan user dari modal
            if ($statusOption === 'finalize') {
                \Illuminate\Support\Facades\Cache::put("drive.folder.manual_status.{$folderId}", 'COMPLETE', now()->addHours(24));
                $this->sheetService->setChecklistValue($folderId, 'v');
            } else {
                // User memilih "Masih akan upload dokumen lagi" -> simpan status PENDING
                \Illuminate\Support\Facades\Cache::put("drive.folder.manual_status.{$folderId}", 'PENDING', now()->addHours(24));
                $this->sheetService->setChecklistValue($folderId, '');
            }

            // 3. Realtime sync per-folder dengan cache yang sudah bersih
            $this->syncFolderRealtimeStatus($folderId);

            return redirect()
                ->route('drive.show', $folderId)
                ->with('success', "File \"{$file->getClientOriginalName()}\" berhasil diunggah.");
        } catch (Exception $e) {
            return redirect()
                ->route('drive.show', $folderId)
                ->with('error', 'Gagal mengunggah file. Silakan coba lagi.');
        }
    }

    /**
     * Mengunduh file dari Google Drive.
     *
     * @param  string  $fileId    Google Drive File ID
     * @param  string  $fileName  Nama file untuk download
     * @return \Illuminate\Http\Response|RedirectResponse
     */
    public function downloadFile(string $fileId, string $fileName): mixed
    {
        try {
            $response = $this->driveService->downloadFile($fileId);
            $content  = $response->getBody()->getContents();

            return response($content, 200, [
                'Content-Type'        => 'application/octet-stream',
                'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            ]);
        } catch (Exception $e) {
            return redirect()->back()->with('error', 'Gagal mengunduh file. Silakan coba lagi.');
        }
    }

    /**
     * Mengganti nama folder atau file.
     *
     * @param  RenameItemRequest  $request
     * @return RedirectResponse
     */
    public function renameItem(RenameItemRequest $request): RedirectResponse
    {
        $itemId   = $request->input('item_id');
        $newName  = $request->input('new_name');
        $folderId = $request->input('folder_id');

        try {
            $this->driveService->renameItem($itemId, $newName);

            // Invalidate hanya metadata item yang di-rename
            $this->cacheService->invalidateFolderMeta($itemId);

            return redirect()
                ->route('drive.show', $folderId)
                ->with('success', "Item berhasil diubah namanya menjadi \"{$newName}\".");
        } catch (Exception $e) {
            return redirect()
                ->route('drive.show', $folderId)
                ->with('error', 'Gagal mengganti nama. Silakan coba lagi.');
        }
    }

    /**
     * Menghapus folder atau file.
     *
     * @param  DeleteItemRequest  $request
     * @return RedirectResponse
     */
    public function deleteItem(DeleteItemRequest $request): RedirectResponse
    {
        $itemId   = $request->input('item_id');
        $folderId = $request->input('folder_id');

        try {
            $this->driveService->deleteFile($itemId);

            // Invalidate cache LANGSUNG agar file list terhapus dari cache
            if ($folderId) {
                $this->cacheService->invalidateAfterFileChange($folderId);
                $this->cacheService->invalidateFolderMeta($itemId);
                $this->sheetService->updateChecklistAfterDelete($folderId);
                $this->syncFolderRealtimeStatus($folderId);
            }

            return redirect()
                ->route('drive.show', $folderId)
                ->with('success', 'Item berhasil dihapus.');
        } catch (Exception $e) {
            return redirect()
                ->route('drive.show', $folderId)
                ->with('error', 'Gagal menghapus item. Silakan coba lagi.');
        }
    }

    /**
     * Menavigasi secara dinamis ke folder unit tertentu berdasarkan Semester, Bulan, Daerah, dan Unit.
     * Digunakan dari halaman Monitoring (klik sel).
     *
     * @param  Request  $request
     * @return RedirectResponse
     */
    public function navigateToFolder(Request $request): RedirectResponse
    {
        $semesterName = $request->query('semester');
        $monthName    = $request->query('month');
        $daerahName   = $request->query('daerah');
        $unitName     = $request->query('unit');

        if (empty($semesterName) || empty($monthName) || empty($daerahName) || empty($unitName)) {
            return redirect()->route('drive.index')
                ->with('error', 'Parameter navigasi tidak lengkap.');
        }

        try {
            $cleanedUnitName = $this->cleanUnitName($unitName);

            // ── 0 GOOGLE API CALL: Query SQLite DB monitoring_statuses langsung (< 50ms) ──
            $statusRecord = \App\Models\MonitoringStatus::where('semester', $semesterName)
                ->where('bulan', $monthName)
                ->where('region', strtoupper(trim($daerahName)))
                ->where(function ($q) use ($unitName, $cleanedUnitName) {
                    $q->where('unit', strtoupper(trim($cleanedUnitName)))
                      ->orWhere('unit_original', trim($unitName));
                })
                ->whereNotNull('folder_id')
                ->first();

            if ($statusRecord && ! empty($statusRecord->folder_id)) {
                $targetFolderId = $statusRecord->folder_id;
                if (preg_match('/^([a-zA-Z0-9\-_]+)_[a-f0-9]{32}$/', $targetFolderId, $matches)) {
                    $targetFolderId = $matches[1];
                }

                $folderNameLower = strtolower(trim($statusRecord->folder_name ?? ''));
                $regionNameLower = strtolower(trim($daerahName));

                // Hanya gunakan targetFolderId dari DB jika itu adalah folder unit (bukan folder daerah/parent)
                if (! empty($folderNameLower) && $folderNameLower !== $regionNameLower && ! str_contains($regionNameLower, $folderNameLower)) {
                    return redirect()->route('drive.show', $targetFolderId);
                }
            }

            // ── Fallback (jika data belum tersimpan di SQLite) ─────────────────
            $settings = $this->oAuthService->getSettings();
            $rootId = $settings['root_folder_id'] ?? null;
            if (empty($rootId)) {
                return redirect()->route('drive.index')
                    ->with('error', 'Root Folder belum dikonfigurasi. Silakan pilih Root Folder terlebih dahulu.');
            }

            // 1. Cari folder Bulan (misal: "1. JAN 2026") LANGSUNG di bawah Root Folder
            $monthFolder = $this->findMonthFolder($monthName, $rootId);
            if (! $monthFolder) {
                return redirect()->route('drive.show', $rootId)
                    ->with('warning', "Folder bulan \"{$monthName}\" belum dibuat di Google Drive. Silakan buat folder bulan terlebih dahulu.");
            }

            // 2. Cari folder Daerah (misal: "PLN UP3 PALEMBANG") under Bulan
            $daerahFolder = $this->findFolderCaseInsensitive($daerahName, $monthFolder['id']);
            if (! $daerahFolder) {
                return redirect()->route('drive.show', $monthFolder['id'])
                    ->with('warning', "Folder daerah \"{$daerahName}\" belum dibuat di Google Drive di bawah bulan {$monthName}.");
            }

            // 3. Cari folder Unit (misal: "MULP KENTEN" atau "TL LAKDAN JMB") under Daerah (rekursif hingga 4 level)
            $unitFolder = $this->findFolderRecursive($cleanedUnitName, $daerahFolder['id']);
            if (! $unitFolder) {
                return redirect()->route('drive.show', $daerahFolder['id'])
                    ->with('warning', "Folder unit \"{$cleanedUnitName}\" belum dibuat di Google Drive di bawah {$daerahName}. Silakan buat folder unit ini terlebih dahulu.");
            }

            // Redirect ke unit folder
            return redirect()->route('drive.show', $unitFolder['id']);

        } catch (Exception $e) {
            Log::error('DriveController::navigateToFolder gagal', [
                'semester' => $semesterName,
                'month'    => $monthName,
                'daerah'   => $daerahName,
                'unit'     => $unitName,
                'error'    => $e->getMessage()
            ]);
            return redirect()->route('drive.index')->with('error', 'Gagal menavigasi ke folder Google Drive.');
        }
    }



    /**
     * Cari folder secara case-insensitive dari Google Drive.
     *
     * @param  string  $name
     * @param  string  $parentId
     * @return array|null
     */
    private function findFolderCaseInsensitive(string $name, string $parentId): ?array
    {
        try {
            $folders = $this->driveService->listFolders($parentId);
            $target = strtolower(trim($name));

            foreach ($folders as $folder) {
                if (strtolower(trim($folder['name'])) === $target) {
                    return $folder;
                }
            }
        } catch (Exception $e) {
            // Abaikan error dan kembalikan null
        }
        return null;
    }

    /**
     * Bersihkan nama unit dari nomor urut (contoh: "a. MULP KENTEN" -> "MULP KENTEN").
     *
     * @param  string  $name
     * @return string
     */
    private function cleanUnitName(string $name): string
    {
        return preg_replace('/^[a-z0-9]+\.\s*/i', '', trim($name));
    }

    /**
     * Membangun breadcrumb berdasarkan folder saat ini.
     * Breadcrumb ditampilkan sebagai navigasi path.
     * Hasil dicache selama 24 jam (TTL_BREADCRUMB).
     *
     * @param  array  $currentFolder  Metadata folder saat ini
     * @return array<int, array{id: string, name: string}>
     */
    private function buildBreadcrumb(array $currentFolder): array
    {
        $cached = $this->cacheService->getBreadcrumb($currentFolder['id']);
        if ($cached !== null) {
            return $cached;
        }

        $breadcrumb = [
            ['id' => $currentFolder['id'], 'name' => $currentFolder['name']],
        ];

        $settings = $this->oAuthService->getSettings();
        $rootFolderId = $settings['root_folder_id'] ?? null;
        $parents      = $currentFolder['parents'] ?? [];
        $depth        = 0;
        $maxDepth     = 10;

        while (! empty($parents) && $depth < $maxDepth) {
            $parentId = $parents[0];

            if ($parentId === $rootFolderId) {
                break;
            }

            try {
                $parentFolder  = $this->driveService->getFolder($parentId);
                $breadcrumb[]  = ['id' => $parentFolder['id'], 'name' => $parentFolder['name']];
                $parents       = $parentFolder['parents'] ?? [];
            } catch (Exception $e) {
                break;
            }

            $depth++;
        }

        $result = array_reverse($breadcrumb);
        $this->cacheService->putBreadcrumb($currentFolder['id'], $result);

        return $result;
    }

    /**
     * Cari folder Bulan secara pintar (mencocokkan singkatan/angka bulan).
     */
    private function findMonthFolder(string $monthName, string $parentId): ?array
    {
        try {
            $folders = $this->driveService->listFolders($parentId);
            foreach ($folders as $folder) {
                if ($this->matchMonthFolder($monthName, $folder['name'])) {
                    return $folder;
                }
            }
        } catch (Exception $e) {
            // ignore
        }
        return null;
    }

    private function matchMonthFolder(string $monthName, string $folderName): bool
    {
        $monthNameLower  = strtolower($monthName);
        $folderNameLower = strtolower($folderName);

        // 1. Direct substring match (misal: "desember" ada di "12. DES 2025")
        if (str_contains($folderNameLower, $monthNameLower)) {
            return true;
        }

        // 2. Singkatan teks bulan (case-insensitive substring)
        $textAbbrs = [
            'januari'   => ['jan'],
            'februari'  => ['feb'],
            'maret'     => ['mar', 'maret'],
            'april'     => ['apr'],
            'mei'       => ['mei'],
            'juni'      => ['jun'],
            'juli'      => ['jul'],
            'agustus'   => ['agu', 'agt'],
            'september' => ['sep', 'sept'],
            'oktober'   => ['okt', 'oct'],
            'november'  => ['nov'],
            'desember'  => ['des', 'dec'],
        ];

        if (isset($textAbbrs[$monthNameLower])) {
            foreach ($textAbbrs[$monthNameLower] as $abbr) {
                if (str_contains($folderNameLower, $abbr)) {
                    return true;
                }
            }
        }

        // 3. Nomor urut folder (misal: "1. JAN" = Januari, "12. DES" = Desember)
        //    Format: angka diikuti titik di awal nama folder
        $numericMap = [
            'januari'   => 1,
            'februari'  => 2,
            'maret'     => 3,
            'april'     => 4,
            'mei'       => 5,
            'juni'      => 6,
            'juli'      => 7,
            'agustus'   => 8,
            'september' => 9,
            'oktober'   => 10,
            'november'  => 11,
            'desember'  => 12,
        ];

        if (isset($numericMap[$monthNameLower])) {
            $num = $numericMap[$monthNameLower];
            // Match pola: "12." atau "12 " di awal nama folder
            if (preg_match('/^\s*' . $num . '[.\s]/', $folderNameLower)) {
                return true;
            }
        }

        return false;
    }


    /**
     * Menampilkan daftar seluruh folder yang Pending Finalisasi.
     */
    public function pendingFinalization(): View
    {
        try {
            $pendingFolders = $this->monitoringService->getPendingFolders();
            return view('drive.pending', compact('pendingFolders'));
        } catch (Exception $e) {
            return view('drive.pending', [
                'pendingFolders' => [],
                'error'          => 'Gagal memuat daftar folder pending finalisasi: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * Memfinalisasi upload folder.
     */
    public function finalizeFolder(Request $request): RedirectResponse
    {
        $folderId = $request->input('folder_id');

        try {
            \Illuminate\Support\Facades\Cache::put("drive.folder.manual_status.{$folderId}", 'COMPLETE', now()->addHours(24));
            $this->sheetService->setChecklistValue($folderId, 'v');
            $this->syncFolderRealtimeStatus($folderId);
            $this->cacheService->invalidateAfterFileChange($folderId);

            return redirect()
                ->route('drive.show', $folderId)
                ->with('success', 'Folder berhasil difinalisasi.');
        } catch (Exception $e) {
            return redirect()
                ->route('drive.show', $folderId)
                ->with('error', 'Gagal memfinalisasi folder. Silakan coba lagi.');
        }
    }

    /**
     * Membatal-finalisasi upload folder (mengembalikan ke Pending Finalisasi).
     */
    public function cancelFinalizeFolder(Request $request): RedirectResponse
    {
        $folderId = $request->input('folder_id');

        try {
            \Illuminate\Support\Facades\Cache::put("drive.folder.manual_status.{$folderId}", 'PENDING', now()->addHours(24));
            $this->sheetService->setChecklistValue($folderId, '');
            $this->syncFolderRealtimeStatus($folderId);
            $this->cacheService->invalidateAfterFileChange($folderId);

            return redirect()
                ->route('drive.show', $folderId)
                ->with('success', 'Finalisasi dibatalkan. Folder kembali menjadi draft.');
        } catch (Exception $e) {
            return redirect()
                ->route('drive.show', $folderId)
                ->with('error', 'Gagal membatalkan finalisasi folder. Silakan coba lagi.');
        }
    }

    /**
     * Mengubah status folder secara manual (Sudah Ceklist / Pending / Belum Upload)
     * dan menyinkronkan ceklist ke Google Sheet serta database SQLite.
     *
     * @param  Request  $request
     * @return RedirectResponse
     */
    public function updateFolderStatus(Request $request): RedirectResponse
    {
        $folderId = $request->input('folder_id');
        $newStatus = strtolower(trim($request->input('status', '')));

        if (empty($folderId) || ! in_array($newStatus, ['complete', 'pending', 'incomplete'], true)) {
            return redirect()->back()->with('error', 'Status atau ID folder tidak valid.');
        }

        try {
            $sheetValue = ($newStatus === 'complete') ? 'v' : '';

            // Update sel di Google Sheet
            $this->sheetService->setChecklistValue($folderId, $sheetValue);

            // Update record MonitoringStatus di SQLite — buat baru jika belum ada
            $statusRecord = \App\Models\MonitoringStatus::where('folder_id', $folderId)
                ->orWhere('folder_id', 'like', $folderId . '_%')
                ->first();

            $dbStatus = match ($newStatus) {
                'complete'   => 'COMPLETE',
                'pending'    => 'PENDING',
                'incomplete' => 'INCOMPLETE',
            };

            // SELALU simpan manual override di cache (24 jam) agar pilihan user tidak tertimpa auto-sync
            \Illuminate\Support\Facades\Cache::put(
                "drive.folder.manual_status.{$folderId}",
                $dbStatus,
                now()->addHours(24)
            );

            if ($statusRecord) {
                $statusRecord->update([
                    'status'       => $dbStatus,
                    'sheet_status' => $sheetValue,
                    'last_sync'    => now(),
                ]);
            }

            // Realtime sync per-folder
            $this->syncFolderRealtimeStatus($folderId);

            // Invalidate cache agar UI langsung update
            $this->cacheService->invalidateAfterFileChange($folderId);

            $message = match ($newStatus) {
                'complete'   => 'Folder berhasil divalidasi dan di-ceklist otomatis pada Google Sheet.',
                'pending'    => 'Status folder berhasil diubah menjadi Pending Finalisasi.',
                'incomplete' => 'Ceklist dibatalkan. Status folder kembali menjadi Belum Upload.',
            };

            return redirect()
                ->route('drive.show', $folderId)
                ->with('success', $message);
        } catch (Exception $e) {
            Log::error('DriveController::updateFolderStatus gagal', [
                'folderId'  => $folderId,
                'newStatus' => $newStatus,
                'error'     => $e->getMessage(),
            ]);

            return redirect()
                ->route('drive.show', $folderId)
                ->with('error', 'Gagal mengubah status folder. Silakan coba lagi.');
        }
    }

    /**
     * Memulai proses Auto Generate Folder di background secara async via Queue.
     *
     * @param  Request  $request
     * @return mixed
     */
    /**
     * Menghasilkan rekomendasi nama folder bertingkat secara Context-Aware untuk modal Buat Folder Baru.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRecommendations(Request $request): \Illuminate\Http\JsonResponse
    {
        $folderId = $request->query('folder_id');

        if (empty($folderId)) {
            return response()->json([
                'level'           => 'ROOT',
                'recommendations' => [],
                'is_leaf'         => false,
                'message'         => 'Folder ID tidak valid.',
            ], 400);
        }

        try {
            /** @var \App\Services\FolderRecommendationService $service */
            $service = app(\App\Services\FolderRecommendationService::class);
            $data    = $service->getRecommendations($folderId);

            return response()->json($data);
        } catch (Exception $e) {
            Log::error('DriveController::getRecommendations gagal', [
                'folder_id' => $folderId,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'level'           => 'ERROR',
                'recommendations' => [],
                'is_leaf'         => false,
                'message'         => 'Gagal mengambil struktur folder. Silakan Refresh.',
            ], 500);
        }
    }


    /**
     * Menghasilkan preview lokasi dan item folder yang akan dibuat (Context-Aware).
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generatePreview(Request $request): \Illuminate\Http\JsonResponse
    {
        $folderId = $request->query('folder_id');

        if (empty($folderId)) {
            return response()->json([
                'can_generate' => false,
                'message'      => 'Folder ID tidak valid.',
            ], 400);
        }

        try {
            /** @var \App\Services\FolderGeneratorService $generatorService */
            $generatorService = app(\App\Services\FolderGeneratorService::class);
            $preview = $generatorService->generatePreviewData($folderId);

            return response()->json($preview);
        } catch (Exception $e) {
            Log::error('DriveController::generatePreview gagal', [
                'folder_id' => $folderId,
                'error'     => $e->getMessage(),
            ]);

            return response()->json([
                'can_generate' => false,
                'message'      => 'Gagal memuat preview: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Memulai proses Auto Generate Folder di background secara async via Queue (Context-Aware).
     *
     * @param  Request  $request
     * @return mixed
     */
    public function generateFolder(Request $request): mixed
    {
        $folderId = $request->input('folder_id');

        if (empty($folderId)) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Folder ID wajib diisi.'], 400);
            }
            return redirect()->back()->with('error', 'Folder ID wajib diisi.');
        }

        try {
            /** @var \App\Services\FolderGeneratorService $generatorService */
            $generatorService = app(\App\Services\FolderGeneratorService::class);
            $contextInfo = $generatorService->resolveContextInfo($folderId);

            if (! $contextInfo['can_generate']) {
                if ($request->wantsJson()) {
                    return response()->json(['success' => false, 'message' => $contextInfo['message'] ?? 'Generasi folder tidak diizinkan.'], 400);
                }
                return redirect()->back()->with('error', $contextInfo['message'] ?? 'Generasi folder tidak diizinkan.');
            }

            // Self-Healing: Tandai FAILED pada log PENDING/RUNNING yang macet (> 5 menit)
            \App\Models\FolderGenerationLog::whereIn('status', ['PENDING', 'RUNNING'])
                ->where('updated_at', '<', now()->subMinutes(5))
                ->update([
                    'status'        => 'FAILED',
                    'error_message' => 'Proses terhenti karena batas waktu (timeout/stale log).',
                ]);

            // Cek jika ada proses generate aktif untuk folderId ini
            $activeLog = \App\Models\FolderGenerationLog::where('folder_id', $folderId)
                ->whereIn('status', ['PENDING', 'RUNNING'])
                ->latest('id')
                ->first();

            if ($activeLog) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Proses pembuatan folder untuk lokasi ini sedang berjalan.',
                        'log_id'  => $activeLog->id,
                    ], 409);
                }
                return redirect()->back()->with('error', 'Proses pembuatan folder untuk lokasi ini sedang berjalan.');
            }

            // Buat log transaksi baru
            $log = \App\Models\FolderGenerationLog::create([
                'folder_id'         => $folderId,
                'context_level'     => $contextInfo['context_level'],
                'status'            => 'PENDING',
                'progress'          => 5,
                'processed_folders' => 0,
                'total_folders'     => 0,
                'current_folder'    => "Mendeteksi posisi folder ({$contextInfo['context_level']})",
            ]);

            Log::info("[AUTO-GENERATE CONTEXT-AWARE CONTROLLER] Request diterima", [
                'folder_id'     => $folderId,
                'context_level' => $contextInfo['context_level'],
                'log_id'        => $log->id,
            ]);

            // Dispatch background job
            \App\Jobs\GenerateFolderJob::dispatchAfterResponse($folderId, $log->id);

            if ($request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Proses Auto Generate Folder telah dimulai di background.',
                    'log_id'  => $log->id,
                ]);
            }

            return redirect()->back()->with('success', 'Proses Auto Generate Folder telah dimulai.');

        } catch (Exception $e) {
            Log::error('DriveController::generateFolder gagal', [
                'folder_id' => $folderId,
                'error'     => $e->getMessage(),
            ]);

            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }

            return redirect()->back()->with('error', 'Gagal memicu pembuatan folder: ' . $e->getMessage());
        }
    }

    /**
     * Memeriksa status progress realtime dari pembuatan folder untuk polling AJAX.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateFolderStatus(Request $request): \Illuminate\Http\JsonResponse
    {
        $logId    = $request->query('log_id');
        $folderId = $request->query('folder_id');

        $query = \App\Models\FolderGenerationLog::query();

        if (! empty($logId)) {
            $query->where('id', $logId);
        } elseif (! empty($folderId)) {
            $query->where('folder_id', $folderId);
        }

        $log = $query->latest('id')->first();

        if (! $log) {
            return response()->json([
                'status'            => 'completed',
                'progress'          => 100,
                'processed_folders' => 0,
                'total_folders'     => 0,
                'new_count'         => 0,
                'existing_count'    => 0,
                'duration_seconds'  => 0,
                'current_folder'    => 'Generate selesai',
                'error_message'     => null,
            ]);
        }

        $rawStatus = strtoupper($log->status);
        $status = match ($rawStatus) {
            'RUNNING', 'PENDING' => 'running',
            'COMPLETED', 'SUCCESS' => 'completed',
            'FAILED' => 'failed',
            default => strtolower($rawStatus),
        };

        return response()->json([
            'log_id'            => $log->id,
            'folder_id'         => $log->folder_id,
            'context_level'     => $log->context_level,
            'status'            => $status,
            'progress'          => (int) ($status === 'completed' ? 100 : ($log->progress ?? 5)),
            'processed_folders' => (int) ($log->processed_folders ?? 0),
            'total_folders'     => (int) ($log->total_folders ?? 0),
            'new_count'         => (int) ($log->new_count ?? 0),
            'existing_count'    => (int) ($log->existing_count ?? 0),
            'duration_seconds'  => (float) ($log->duration_seconds ?? 0),
            'error_message'     => $log->error_message,
        ]);
    }

    /**
     * Sinkronisasi realtime cepat per-folder untuk 1 unit tertentu.
     * Memperbarui record MonitoringStatus di SQLite secara instan (< 50ms)
     * saat file di-upload, di-delete, status diubah, atau folder leaf dibuka.
     */
    private function syncFolderRealtimeStatus(string $folderId): void
    {
        try {
            $hierarchy = $this->driveService->resolveFolderHierarchy($folderId);
            if (! $hierarchy) {
                return;
            }

            $semesterName = $hierarchy['semester'];
            $monthRaw     = $hierarchy['month'];
            $regionRaw    = $hierarchy['region'];
            $unitRaw      = $hierarchy['unit'];

            // Resolusi nama bulan standar
            $stdMonth = match (true) {
                str_contains(strtolower($monthRaw), 'jan') => 'Januari',
                str_contains(strtolower($monthRaw), 'feb') => 'Februari',
                str_contains(strtolower($monthRaw), 'mar') => 'Maret',
                str_contains(strtolower($monthRaw), 'apr') => 'April',
                str_contains(strtolower($monthRaw), 'mei') => 'Mei',
                str_contains(strtolower($monthRaw), 'jun') => 'Juni',
                str_contains(strtolower($monthRaw), 'jul') => 'Juli',
                str_contains(strtolower($monthRaw), 'agu') || str_contains(strtolower($monthRaw), 'agt') => 'Agustus',
                str_contains(strtolower($monthRaw), 'sep') => 'September',
                str_contains(strtolower($monthRaw), 'okt') => 'Oktober',
                str_contains(strtolower($monthRaw), 'nov') => 'November',
                str_contains(strtolower($monthRaw), 'des') => 'Desember',
                default => $monthRaw,
            };

            // Hitung berkas bukti pada folder ini
            $files = $this->driveService->listFiles($folderId);
            $validFiles = array_filter($files, function ($file) {
                $mime = strtolower($file['mimeType'] ?? '');
                $name = strtolower($file['name'] ?? '');
                return str_contains($mime, 'image') || str_contains($mime, 'pdf') 
                    || preg_match('/\.(png|jpe?g|webp|gif|pdf)$/i', $name);
            });

            $imageCount  = count($validFiles);
            $sampleFile  = ! empty($validFiles) ? array_values($validFiles)[0]['name'] : null;
            $hasEvidence = $imageCount >= 1;

            // Ambil checklist di Google Sheet (jika ada)
            $sheetVal = $this->sheetService->getChecklistValue($folderId);
            $isSheetComplete = in_array(strtolower(trim($sheetVal)), ['v', '✓', 'true', '1'], true);

            // Normalisasi nama region & unit untuk pencarian fleksibel di SQLite
            $normReg   = strtolower(trim(preg_replace('/[^a-z0-9]/i', '', $regionRaw)));
            $cleanUnit = preg_replace('/^[a-z0-9]+\.\s*/i', '', trim($unitRaw));
            $normUnit  = strtolower(trim(preg_replace('/[^a-z0-9]/i', '', $cleanUnit)));

            $statusRecord = \App\Models\MonitoringStatus::where('folder_id', $folderId)
                ->orWhere('folder_id', 'like', $folderId . '_%')
                ->first();

            if (! $statusRecord) {
                $statusRecord = \App\Models\MonitoringStatus::where('semester', $semesterName)
                    ->where('bulan', $stdMonth)
                    ->where(function ($q) use ($regionRaw, $normReg) {
                        $q->where('region', $regionRaw)
                          ->orWhereRaw("LOWER(REPLACE(REPLACE(region, ' ', ''), '.', '')) LIKE ?", ["%{$normReg}%"]);
                    })
                    ->where(function ($q) use ($unitRaw, $cleanUnit, $normUnit) {
                        $q->where('unit', $cleanUnit)
                          ->orWhere('unit_original', $unitRaw)
                          ->orWhereRaw("LOWER(REPLACE(REPLACE(unit, ' ', ''), '.', '')) LIKE ?", ["%{$normUnit}%"]);
                    })
                    ->first();
            }

            // Jika folder kosong (0 file bukti), status SELALU 'INCOMPLETE' (Belum Upload) dan hapus manual cache
            if (! $hasEvidence) {
                \Illuminate\Support\Facades\Cache::forget("drive.folder.manual_status.{$folderId}");
                $this->sheetService->setChecklistValue($folderId, '');
                $finalStatus = 'INCOMPLETE';
                $sheetStatus = '';
            } else {
                // Ada file bukti. Prioritaskan cache manual atau status PENDING yang sedang aktif
                $cachedManual = \Illuminate\Support\Facades\Cache::get("drive.folder.manual_status.{$folderId}");
                if ($cachedManual) {
                    $finalStatus = strtoupper($cachedManual);
                } elseif ($statusRecord && strtoupper($statusRecord->status) === 'PENDING') {
                    $finalStatus = 'PENDING';
                } elseif ($isSheetComplete) {
                    $finalStatus = 'COMPLETE';
                } else {
                    $finalStatus = ($statusRecord && strtoupper($statusRecord->status) === 'COMPLETE') ? 'COMPLETE' : 'PENDING';
                }

                if ($finalStatus === 'COMPLETE') {
                    $sheetStatus = 'v';
                } else {
                    $sheetStatus = '';
                }
            }

            if ($statusRecord) {
                $statusRecord->update([
                    'folder_id'            => $folderId,
                    'folder_name'          => strtolower(trim($unitRaw)),
                    'folder_path'          => "{$regionRaw} / {$unitRaw}",
                    'sheet_status'         => $sheetStatus,
                    'drive_status'         => $driveStatus,
                    'status'               => $finalStatus,
                    'evidence_count'       => $imageCount,
                    'sample_evidence_file' => $sampleFile,
                    'last_sync'            => now(),
                ]);

                // Invalidate monitoring cache agar halaman Monitoring Upload langsung terupdate realtime!
                $this->cacheService->invalidateAllMonitoring();
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning("DriveController::syncFolderRealtimeStatus failed for {$folderId}: " . $e->getMessage());
        }
    }
}

