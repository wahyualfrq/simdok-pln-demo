<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Google\GoogleApiService;
use Google\Service\Drive as GoogleDrive;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DriveLeafScannerService
 *
 * Melakukan recursive DFS (Depth-First Search) pada folder Google Drive
 * untuk menemukan LEAF FOLDER yang berisi file gambar bukti posting.
 *
 * Problem yang diselesaikan:
 * ─────────────────────────
 * Struktur folder Drive SIMDOK sangat tidak konsisten:
 *   - Beberapa unit menyimpan file langsung di Level 4 (Unit folder)
 *   - Beberapa unit menyimpan file di Level 5 via container SS/SCREENSHOT
 *   - Beberapa unit menyimpan file di Level 5-6 via MEDSOS DISEMINASI/JAMBI/
 *   - Nama folder berubah-ubah per bulan (ULP → MULP, LAKSDAN → LAKDAN)
 *
 * Solusi:
 * ───────
 * Bottom-up leaf detection: telusuri sampai terdalam, abaikan nama folder,
 * hitung hanya file gambar (.jpg, .jpeg, .png) di leaf folder.
 *
 * Folder yang diabaikan: FLYER, FLAYER, Template, SMAP UID S2JB RAMADAN.
 *
 * Cache: Hasil disimpan ke tabel `monitoring_folder_cache` (DB) dan
 * Laravel Cache (in-memory, TTL pendek) untuk performa.
 *
 * @see analysis_simdok_sync_redesign.md
 * @see 11_MONITORING_GOOGLE_SHEET.md
 */
class DriveLeafScannerService
{
    /**
     * Kedalaman traversal maksimum dari folder bulan.
     * Level 0 = folder bulan (e.g., "4. APR 2026")
     * Level 1 = unit induk (e.g., "UP3 PALEMBANG")
     * Level 2 = sub-unit atau container (e.g., "ULP AMPERA" atau "SCREENSHOT")
     * Level 3 = leaf atau sub-container
     * Level 4 = leaf (jarang)
     * Level 5 = batas aman
     */
    private const MAX_DEPTH = 5;

    /**
     * Ekstensi file yang dianggap sebagai bukti posting valid (.jpg, .jpeg, .png, .pdf).
     */
    private const VALID_IMAGE_EXTENSIONS = ['jpg', 'jpeg', 'png', 'pdf', 'webp'];

    /**
     * MIME types yang valid untuk file gambar & dokumen bukti.
     * Digunakan sebagai filter sekunder saat query Drive API.
     */
    private const VALID_IMAGE_MIMES = [
        'image/jpeg',
        'image/jpg',
        'image/png',
        'image/webp',
        'application/pdf',
    ];

    /**
     * Folder yang harus SELALU diabaikan (bukan folder monitoring).
     * Berdasarkan analisis aktual data Drive SIMDOK.
     */
    private const NON_MONITORING_KEYWORDS = [
        'flyer',
        'flayer',
        'template',
        'smap',
        'desain',
        'asset',
        'poster',
        'banner',
        'draft',
        'desiminasi',
        'diseminasi',
        'desemenasi',
        'disemenasi',
        'dismensi',
    ];

    /**
     * Keyword dalam nama FILE yang menandakan file adalah screenshot / evidence posting valid.
     * File dengan keyword ini diprioritaskan dan dianggap bukti yang kuat.
     */
    private const SCREENSHOT_KEYWORDS = [
        'screenshot',
        'screen shot',
        'screen',
        'ss ',
        ' ss.',
        'wa story',
        'wa status',
        'story',
        'update',
    ];

    /**
     * Keyword dalam nama FILE yang menandakan file adalah materi desiminasi.
     */
    private const DESIMINASI_FILE_KEYWORDS = [
        'desiminasi',
        'diseminasi',
        'desemenasi',
        'disemenasi',
        'dismensi',
    ];

    /**
     * Keyword dalam nama FILE yang menandakan file adalah materi desain / flyer (bukan screenshot).
     * File dengan keyword ini DIABAIKAN sebagai evidence.
     */
    private const FLYER_FILE_KEYWORDS = [
        'flyer',
        'flayer',
        'template',
        'desain',
        'poster',
        'banner',
        'draft',
        'asset',
    ];

    private const TTL_ACTIVE_SECONDS = 86400;   // 24 jam
    private const TTL_CLOSED_SECONDS = 2592000; // 30 hari

    private ?GoogleDrive $drive = null;

    public function __construct(
        private readonly GoogleApiService $apiService,
        private readonly UnitAliasService $aliasService,
        private readonly EvidenceMatchingService $matchingEngine,
    ) {}

    /**
     * Periksa apakah folder tertentu masuk dalam kategori "non-monitoring" (Blacklist).
     *
     * @param  string  $folderName
     * @return bool
     */
    public function isNonMonitoringFolder(string $folderName): bool
    {
        $nameLower = strtolower(trim($folderName));

        // Jika nama folder mengandung 'medsos', 'sosmed', atau 'social media',
        // JANGAN PERNAH di-blacklist (misal "MEDSOS DISEMINASI") karena ini folder bukti posting!
        if (
            str_contains($nameLower, 'medsos') ||
            str_contains($nameLower, 'sosmed') ||
            str_contains($nameLower, 'sosial media') ||
            str_contains($nameLower, 'social media')
        ) {
            return false;
        }

        foreach (self::NON_MONITORING_KEYWORDS as $keyword) {
            if (str_contains($nameLower, $keyword)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Scan seluruh leaf folders di bawah sebuah folder bulan (e.g., "4. APR 2026").
     *
     * @param  string  $monthFolderId
     * @param  string  $monthPeriod
     * @param  bool    $forceRefresh
     * @return array<int, array{
     *     region_name: string,
     *     unit_name: string,
     *     drive_folder_id: string,
     *     image_file_count: int,
     *     sample_file: string|null,
     *     drive_modified_at: string|null
     * }>
     */
    public function scanMonthFolder(
        string $monthFolderId,
        string $monthPeriod = '',
        bool $forceRefresh = false
    ): array {
        $startTime = microtime(true);

        if (! $forceRefresh) {
            $cached = $this->loadFromDbCache($monthFolderId, $monthPeriod);
            if ($cached !== null) {
                Log::info('DriveLeafScannerService: Cache HIT (DB)', [
                    'monthFolderId' => $monthFolderId,
                    'monthPeriod'   => $monthPeriod,
                    'leafCount'     => count($cached),
                ]);
                return $cached;
            }
        }

        $subfolders  = $this->listSubfolders($monthFolderId);
        $leafResults = [];

        foreach ($subfolders as $sub) {
            if ($this->isNonMonitoringFolder($sub['name'])) {
                continue;
            }

            $regionName = $sub['name'];

            // ── MODE 2: Keyword-Based File Matching (mode=file) ─────────────
            // Untuk parent seperti KANTOR UID, UP2D S2JB, UP2K *
            // file gambar tersimpan langsung di folder parent (tanpa subfolder unit).
            // Unit dideteksi dari nama file menggunakan Keyword Matching Engine.
            $parentMode = $this->matchingEngine->getParentMode($regionName);
            if ($parentMode === 'file') {
                Log::info('DriveLeafScannerService: MODE 2 (Keyword File Matching)', [
                    'region' => $regionName,
                ]);

                $mode2Results = $this->scanFilesAsUnits(
                    folderId:   $sub['id'],
                    regionName: $regionName,
                );

                $leafResults = array_merge($leafResults, $mode2Results);
                continue;
            }

            // ── MODE 1: has_subfolder=true (default) ────────────────────────
            $childLeafs = $this->traverseFolder(
                folderId:   $sub['id'],
                folderName: $sub['name'],
                regionName: $regionName,
                depth:      0
            );

            $leafResults = array_merge($leafResults, $childLeafs);
        }

        // Jika subfolders kosong, mungkin ID ini adalah folder unit langsung.
        // Coba evaluasi folder ini sendiri.
        if (empty($subfolders)) {
            try {
                $client = new GoogleDrive($this->apiService->getClient());
                $folder = $client->files->get($monthFolderId, ['fields' => 'name']);
                $directLeafs = $this->traverseFolder(
                    folderId:   $monthFolderId,
                    folderName: $folder->getName(),
                    regionName: '', 
                    depth:      0
                );
                $leafResults = array_merge($leafResults, $directLeafs);
            } catch (\Exception $e) {
                Log::warning('DriveLeafScannerService: Gagal fallbak empty subfolders', [
                    'folderId' => $monthFolderId,
                    'error'    => $e->getMessage()
                ]);
            }
        }

        $this->saveToDbCache($monthFolderId, $monthPeriod, $leafResults);

        $elapsed = round(microtime(true) - $startTime, 2);
        Log::info('DriveLeafScannerService: Scan selesai', [
            'monthFolderId' => $monthFolderId,
            'monthPeriod'   => $monthPeriod,
            'leafCount'     => count($leafResults),
            'elapsedSec'    => $elapsed,
        ]);

        return $leafResults;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE: TRAVERSAL
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Rekursif DFS untuk menemukan leaf folder dari sebuah folder.
     *
     * Evaluasi evidence menggunakan logika bertingkat:
     *   1. Jika ada file yang match nama unit (inisial/nama asli) → COMPLETE (image_file_count=999)
     *   2. Jika ada file screenshot → COMPLETE
     *   3. Jika jumlah file non-flyer > 2 → COMPLETE
     *   4. Jika jumlah = 2 && ada screenshot → COMPLETE
     *   5. Jika ada subfolder non-flyer → masuk & evaluasi rekursif
     *   6. Selain itu → INCOMPLETE (image_file_count=0)
     *
     * @param  string  $folderId    ID folder yang sedang diperiksa
     * @param  string  $folderName  Nama folder ini (untuk unit_name jika leaf)
     * @param  string  $regionName  Nama region asal (lowercase), tidak berubah saat rekursi
     * @param  int     $depth       Kedalaman saat ini dari unit pertama (0 = level unit)
     * @return array<int, array{
     *     region_name: string,
     *     unit_name: string,
     *     drive_folder_id: string,
     *     image_file_count: int,
     *     drive_modified_at: string|null
     * }>
     */
    private function traverseFolder(
        string $folderId,
        string $folderName,
        string $regionName,
        int $depth = 0
    ): array {
        if ($depth > self::MAX_DEPTH) {
            Log::warning('DriveLeafScannerService: Max depth tercapai', [
                'folderId'   => $folderId,
                'folderName' => $folderName,
                'depth'      => $depth,
            ]);
            return [];
        }

        [$subfolders, $imageCount, $screenshotCount, $unitMatchCount, $desiminasiCount, $latestModified, $sampleFile] =
            $this->listContentsForScanning($folderId, $folderName);

        // Filter subfolder non-monitoring (abaikan flyer dan desiminasi)
        $monitoringSubfolders = array_values(array_filter(
            $subfolders,
            fn ($sf) => ! $this->isNonMonitoringFolder($sf['name'])
        ));

        // Prioritaskan folder medsos, screenshot, atau nama unit
        usort($monitoringSubfolders, function ($a, $b) use ($folderName) {
            $nameA = strtolower($a['name']);
            $nameB = strtolower($b['name']);

            $preferredKeywords = ['medsos', 'instagram', 'ig', 'facebook', 'fb', 'wa', 'story', 'screenshot', 'ss', 'bukti', strtolower($folderName)];

            $priorityA = 0;
            $priorityB = 0;

            foreach ($preferredKeywords as $kw) {
                if ($kw !== '' && str_contains($nameA, $kw)) {
                    $priorityA = 1;
                    break;
                }
            }

            foreach ($preferredKeywords as $kw) {
                if ($kw !== '' && str_contains($nameB, $kw)) {
                    $priorityB = 1;
                    break;
                }
            }

            return $priorityB <=> $priorityA;
        });

        // ── EVALUASI EVIDENCE ──────────────────────────────────────────────
        // Gunakan logika evaluasi bertingkat:
        //   1. Ada file yang match nama unit → COMPLETE
        //   2. Ada file screenshot → COMPLETE
        //   3. Jumlah file non-flyer > 2 → COMPLETE
        //   4. Jumlah file = 2 DAN bukan bernama Desiminasi → COMPLETE
        // Jika tidak ada evidence langsung tapi ada subfolder → rekursi
        $directEvidenceScore = $this->computeEvidenceScore(
            imageCount:      $imageCount,
            screenshotCount: $screenshotCount,
            unitMatchCount:  $unitMatchCount,
            desiminasiCount: $desiminasiCount,
        );

        // ── LEAF DETECTION ──────────────────────────────────────────────────
        if (empty($monitoringSubfolders)) {
            $unitName      = strtolower(trim($this->cleanUnitName($folderName)));
            $effectiveCount = $directEvidenceScore >= 1 ? max($imageCount, 1) : 0;
            return [[
                'region_name'       => $regionName,
                'unit_name'         => $unitName,
                'drive_folder_id'   => $folderId,
                'image_file_count'  => $effectiveCount,
                'sample_file'       => $sampleFile,
                'drive_modified_at' => $latestModified,
            ]];
        }

        // ── MIXED FOLDER (ada file langsung + subfolder) ────────────────────
        // Jika evidence langsung sudah kuat dan BUKAN di level region ($depth > 0), jangan perlu masuk subfolder
        if ($directEvidenceScore >= 1 && $depth > 0) {
            $unitName = strtolower(trim($this->cleanUnitName($folderName)));
            return [[
                'region_name'       => $regionName,
                'unit_name'         => $unitName,
                'drive_folder_id'   => $folderId,
                'image_file_count'  => max($imageCount, 1),
                'sample_file'       => $sampleFile,
                'drive_modified_at' => $latestModified,
            ]];
        }

        // ── CONTAINER → lanjutkan rekursi ke subfolder non-flyer ───────────
        $results = [];
        foreach ($monitoringSubfolders as $subfolder) {
            $childResults = $this->traverseFolder(
                folderId:   $subfolder['id'],
                folderName: $subfolder['name'],
                regionName: $regionName,
                depth:      $depth + 1
            );
            $results = array_merge($results, $childResults);
        }

        // Jika seluruh subfolder tidak menghasilkan hasil (kosong semua) →
        // kembalikan leaf dengan count=0 (INCOMPLETE)
        if (empty($results)) {
            $unitName = strtolower(trim($this->cleanUnitName($folderName)));
            return [[
                'region_name'       => $regionName,
                'unit_name'         => $unitName,
                'drive_folder_id'   => $folderId,
                'image_file_count'  => 0,
                'sample_file'       => null,
                'drive_modified_at' => null,
            ]];
        }

        return $results;
    }

    /**
     * Menghitung skor evidence dari file-file gambar yang ditemukan.
     * Skor 1 berarti COMPLETE (CEKLIST), Skor 0 berarti INCOMPLETE (SILANG).
     *
     * Aturan Evaluasi:
     *   1. Jika jumlah file non-flyer > 2 (3 file atau lebih) → CEKLIST (COMPLETE)
     *   2. Jika jumlah file non-flyer == 2 DAN bukan bernama Desiminasi (desiminasiCount == 0) → CEKLIST (COMPLETE)
     *   3. Jika berjumlah 1 atau 0, ATAU 2 file berisi desiminasi → SILANG (INCOMPLETE)
     *
     * @return int  0 = INCOMPLETE (SILANG), 1 = COMPLETE (CEKLIST)
     */
    private function computeEvidenceScore(
        int $imageCount,
        int $screenshotCount = 0,
        int $unitMatchCount = 0,
        int $desiminasiCount = 0
    ): int {
        // Kondisi 1: Jumlah file non-flyer > 2 (3 file atau lebih) → CEKLIST
        if ($imageCount > 2) {
            return 1;
        }

        // Kondisi 2: Jumlah = 2 DAN file-file tersebut bukan bernama Desiminasi → CEKLIST
        if ($imageCount === 2 && $desiminasiCount === 0) {
            return 1;
        }

        // Kondisi 3: Berjumlah 1, 0, atau 2 file yang mengandung desiminasi → SILANG
        return 0;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE: DRIVE API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Daftar subfolder langsung di bawah sebuah folder.
     *
     * @param  string  $parentId
     * @return array<int, array{id: string, name: string}>
     */
    private function listSubfolders(string $parentId): array
    {
        $drive    = $this->getDrive();
        $response = $drive->files->listFiles([
            'q'        => "'{$parentId}' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
            'fields'   => 'files(id, name)',
            'pageSize' => 500,
        ]);

        $result = [];
        foreach ($response->getFiles() as $file) {
            $result[] = [
                'id'   => $file->getId(),
                'name' => $file->getName(),
            ];
        }
        return $result;
    }

    /**
     * Daftar isi folder: subfolder + hitung file gambar valid + metadata screenshot/unit-match.
     *
     * Mengembalikan tuple:
     *   [subfolders, imageCount, screenshotCount, unitMatchCount, latestModifiedTime, sampleFile]
     *
     * imageCount     = jumlah file gambar valid (non-flyer)
     * screenshotCount = jumlah file yang mengandung keyword screenshot
     * unitMatchCount  = jumlah file yang mengandung nama/inisial unit
     *
     * @param  string       $parentId
     * @param  string|null  $unitName   Nama unit (untuk unit-match detection)
     * @return array{0: array, 1: int, 2: int, 3: int, 4: string|null, 5: string|null}
     */
    private function listContentsForScanning(string $parentId, ?string $unitName = null): array
    {
        $drive           = $this->getDrive();
        $subfolders      = [];
        $imageCount      = 0;
        $screenshotCount = 0;
        $unitMatchCount  = 0;
        $desiminasiCount = 0;
        $latestModified  = null;
        $sampleFile      = null;

        // Ekstrak inisial unit: "ULP AMPERA" → ['ulp', 'ampera', 'ulpampera']
        $unitKeywords = [];
        if ($unitName !== null) {
            $cleanUnit  = strtolower(trim(preg_replace('/^[a-z0-9]+\.\s*/i', '', $unitName)));
            $words      = preg_split('/\s+/', $cleanUnit);
            $words      = array_filter($words, fn($w) => strlen($w) >= 2);
            $initials   = implode('', array_map(fn($w) => $w[0], $words));
            $unitKeywords = array_values(array_unique(array_filter(
                array_merge($words, [$initials, $cleanUnit]),
                fn($k) => strlen($k) >= 2
            )));
        }

        // Single Query ke Google Drive API
        $response = $this->getDrive()->files->listFiles([
            'q'        => "'{$parentId}' in parents and trashed = false",
            'fields'   => 'files(id, name, mimeType, modifiedTime)',
            'pageSize' => 1000,
        ]);

        foreach ($response->getFiles() as $file) {
            $mime = $file->getMimeType();

            if ($mime === 'application/vnd.google-apps.folder') {
                $subfolders[] = [
                    'id'   => $file->getId(),
                    'name' => $file->getName(),
                ];
                continue;
            }

            $filename = $file->getName();

            // Abaikan file yang bukan gambar
            if (! $this->isValidImageFile($filename)) {
                continue;
            }

            // Abaikan file flyer berdasarkan nama file
            if ($this->isFlyerFilename($filename)) {
                continue;
            }

            $filenameLower = strtolower($filename);
            $imageCount++;

            // Deteksi desiminasi
            if ($this->isDesiminasiFilename($filenameLower)) {
                $desiminasiCount++;
            }

            // Deteksi screenshot
            if ($this->isScreenshotFile($filenameLower)) {
                $screenshotCount++;
            }

            // Deteksi unit match
            foreach ($unitKeywords as $kw) {
                if (str_contains($filenameLower, $kw)) {
                    $unitMatchCount++;
                    break;
                }
            }

            // Pilih sample: prioritaskan screenshot atau unit-match
            if ($sampleFile === null
                || $this->isScreenshotFile($filenameLower)
                || ($screenshotCount === 0 && $unitMatchCount > 0)) {
                $sampleFile = $filename;
            }

            $mtime = $file->getModifiedTime();
            if ($mtime) {
                $mtimeStr = is_string($mtime) ? $mtime : (string) $mtime;
                if ($latestModified === null || strcmp($mtimeStr, $latestModified) > 0) {
                    $latestModified = $mtimeStr;
                }
            }
        }

        return [$subfolders, $imageCount, $screenshotCount, $unitMatchCount, $desiminasiCount, $latestModified, $sampleFile];
    }

    /**
     * Memeriksa apakah nama file mengandung keyword desiminasi.
     */
    private function isDesiminasiFilename(string $filenameLower): bool
    {
        foreach (self::DESIMINASI_FILE_KEYWORDS as $kw) {
            if (str_contains($filenameLower, $kw)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Memeriksa apakah nama file mengandung keyword screenshot / evidence posting.
     */
    private function isScreenshotFile(string $filenameLower): bool
    {
        foreach (self::SCREENSHOT_KEYWORDS as $kw) {
            if (str_contains($filenameLower, $kw)) {
                return true;
            }
        }
        // Pola: "Screenshot 2026-03-31..." atau "Screenshot_..."
        if (preg_match('/screenshot\s*[\d_\-]/i', $filenameLower)) {
            return true;
        }
        return false;
    }

    /**
     * Memeriksa apakah nama file mengandung keyword flyer / desain.
     */
    private function isFlyerFilename(string $filename): bool
    {
        $lower = strtolower($filename);
        foreach (self::FLYER_FILE_KEYWORDS as $kw) {
            if (str_contains($lower, $kw)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Mendapatkan Google Drive service instance (lazy-init, singleton per request).
     */
    private function getDrive(): GoogleDrive
    {
        if ($this->drive === null) {
            $this->drive = new GoogleDrive($this->apiService->getClient());
        }
        return $this->drive;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE: VALIDATION
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Memeriksa apakah sebuah nama file memiliki ekstensi gambar yang valid
     * dan tidak mengandung nama/kata kunci flyer/desain/template/banner/poster/asset/draft.
     *
     * @param  string  $filename
     * @return bool
     */
    private function isValidImageFile(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, self::VALID_IMAGE_EXTENSIONS, true);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE: STRING UTILS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Membersihkan nama unit dari prefix nomor urut.
     * Contoh: "1. ULP AMPERA" → "ULP AMPERA"
     *
     * @param  string  $name
     * @return string
     */
    private function cleanUnitName(string $name): string
    {
        return preg_replace('/^[a-z0-9]+\.\s*/i', '', trim($name));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE: MODE 2 — FILE-AS-UNIT SCANNING
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * MODE 2: Scan file gambar langsung di folder parent (has_subfolder=false).
     *
     * Alih-alih menggunakan nama subfolder sebagai unit_name, deteksi unit
     * dari nama file gambar menggunakan alias matching.
     *
     * Contoh:
     *   Folder: KANTOR UID
     *   File:   GM IG JULI.png → unit = GM (alias GM - IG)
     *   File:   SM DIST.jpeg  → unit = SM DISTRIBUSI
     *
     * Setiap unit yang ditemukan mendapatkan satu leaf entry dengan:
     *   - unit_name    = nama unit yang terdeteksi dari file (lowercase)
     *   - image_file_count = jumlah file yang cocok dengan alias unit tersebut
     *   - sample_file  = nama file pertama yang ditemukan
     *
     * @param  string  $folderId    ID folder parent di Google Drive
     * @param  string  $regionName  Nama region (untuk leaf result)
     * @return array<int, array{region_name: string, unit_name: string, drive_folder_id: string, image_file_count: int, sample_file: string|null, drive_modified_at: string|null}>
     */
    /**
     * Memindai file-file gambar di bawah folder (termasuk di dalam subfolder jika ada)
     * dan mengelompokkannya per unit kerja berdasarkan pencocokan nama file.
     *
     * @param  string  $folderId
     * @param  string  $regionName
     * @return array
     */
    private function scanFilesAsUnits(string $folderId, string $regionName): array
    {
        $allFiles = $this->collectAllDriveFiles($folderId);

        // Map: sheet_unit_name (string asli dari Sheet) → { count, sample, latest_modified }
        // KEY adalah nama Sheet asli (bukan lowercase alias), agar unit_name di cache benar.
        $unitMap = [];

        foreach ($allFiles as $file) {
            $filename = $file->getName();

            // Hanya proses file gambar valid
            if (! $this->isValidImageFile($filename)) {
                continue;
            }

            // Deteksi unit dari nama file menggunakan Keyword Matching Engine (SCOPED ke parent)
            $matchResult   = $this->matchingEngine->matchFileKeywords($filename, $regionName);
            $sheetUnitName = $matchResult['unit'] ?? null;

            if ($sheetUnitName === null) {
                // Fallback ke aliasService jika keyword matching tidak menemukan match
                $sheetUnitName = $this->aliasService->findUnitByFile($filename, $regionName);
            }

            if ($sheetUnitName === null) {
                Log::debug('DriveLeafScannerService [MODE 2 - FILE]: File tidak teridentifikasi unit-nya', [
                    'region'   => $regionName,
                    'filename' => $filename,
                ]);
                continue;
            }

            // KEY pakai nama Sheet asli (kapital, as-returned dari getOriginalName)
            // agar unit_name yang masuk ke cache = nama Google Sheet, bukan nama Drive/alias
            $unitKey = $sheetUnitName;

            if (! isset($unitMap[$unitKey])) {
                $unitMap[$unitKey] = [
                    'count'       => 0,
                    'sample'      => null,
                    'modified_at' => null,
                ];
            }

            $unitMap[$unitKey]['count']++;

            // Prioritaskan file dengan kata 'WA STORY' atau 'WA STATUS' sebagai sample file
            $fnLower = strtolower($filename);
            if ($unitMap[$unitKey]['sample'] === null
                || str_contains($fnLower, 'wa story')
                || str_contains($fnLower, 'wa status')) {
                $unitMap[$unitKey]['sample'] = $filename;
            }

            $mtime = $file->getModifiedTime();
            if ($mtime) {
                $mtimeStr = is_string($mtime) ? $mtime : (string) $mtime;
                if ($unitMap[$unitKey]['modified_at'] === null
                    || strcmp($mtimeStr, $unitMap[$unitKey]['modified_at']) > 0) {
                    $unitMap[$unitKey]['modified_at'] = $mtimeStr;
                }
            }
        }

        // Rule Khusus UP2D S2JB: Jika total file gambar di folder > 2, pastikan semua unit (MUP2D S2JB & TL LAKDAN) dianggap complete
        if (str_contains(strtoupper($regionName), 'UP2D')) {
            $validFilesCount = 0;
            $sampleFallback = null;
            foreach ($allFiles as $f) {
                if ($this->isValidImageFile($f->getName())) {
                    $validFilesCount++;
                    if ($sampleFallback === null) {
                        $sampleFallback = $f->getName();
                    }
                }
            }

            if ($validFilesCount > 2) {
                $up2dUnits = ['MUP2D S2JB', 'TL LAKDAN'];
                foreach ($up2dUnits as $uName) {
                    if (! isset($unitMap[$uName]) || $unitMap[$uName]['count'] === 0) {
                        $unitMap[$uName] = [
                            'count'       => $validFilesCount,
                            'sample'      => $sampleFallback,
                            'modified_at' => null,
                        ];
                    }
                }
            }
        }

        // Konversi ke format leaf result
        // unit_name = nama Sheet asli (sesuai Google Sheet, bukan nama Drive atau alias)
        $results = [];
        foreach ($unitMap as $sheetUnitName => $info) {
            $results[] = [
                'region_name'       => $regionName,
                'unit_name'         => $sheetUnitName,  // Nama Sheet asli — BUKAN alias
                'drive_folder_id'   => "{$folderId}_" . md5($sheetUnitName), // Unique per unit agar tidak ter-overwrite pada upsert DB cache
                'image_file_count'  => $info['count'],
                'sample_file'       => $info['sample'],
                'drive_modified_at' => $info['modified_at'],
            ];

            Log::info('DriveLeafScannerService [MODE2]: Unit terdeteksi dari file', [
                'region'      => $regionName,
                'unit'        => $sheetUnitName,
                'img_count'   => $info['count'],
                'sample_file' => $info['sample'],
            ]);
        }

        return $results;
    }

    /**
     * Mengumpulkan seluruh file di bawah folder (termasuk di dalam subfolder jika ada).
     *
     * @param  string  $folderId
     * @param  int     $depth
     * @return array<int, \Google\Service\Drive\DriveFile>
     */
    private function collectAllDriveFiles(string $folderId, int $depth = 0): array
    {
        if ($depth > 2) {
            return [];
        }

        $drive    = $this->getDrive();
        $response = $drive->files->listFiles([
            'q'        => "'{$folderId}' in parents and trashed = false",
            'fields'   => 'files(id, name, mimeType, modifiedTime)',
            'pageSize' => 1000,
        ]);

        $filesList = [];
        foreach ($response->getFiles() as $file) {
            if ($file->getMimeType() === 'application/vnd.google-apps.folder') {
                if (! $this->isNonMonitoringFolder($file->getName())) {
                    $subFiles = $this->collectAllDriveFiles($file->getId(), $depth + 1);
                    $filesList = array_merge($filesList, $subFiles);
                }
            } else {
                $filesList[] = $file;
            }
        }

        return $filesList;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE: DB CACHE
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Muat hasil scan dari DB cache.
     * Mengembalikan null jika cache tidak ada atau sudah kadaluarsa.
     *
     * @param  string  $monthFolderId
     * @param  string  $monthPeriod
     * @param  bool    $ignoreExpiry  Jika true, kembalikan data meskipun sudah expired
     * @return array|null
     */
    private function loadFromDbCache(
        string $monthFolderId,
        string $monthPeriod,
        bool $ignoreExpiry = false
    ): ?array {
        $rows = DB::table('monitoring_folder_cache')
            ->where('month_folder_id', $monthFolderId)
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        // Tentukan TTL berdasarkan apakah bulan ini sudah lewat atau belum
        $ttlSeconds = $this->isActivePeriod($monthPeriod)
            ? self::TTL_ACTIVE_SECONDS
            : self::TTL_CLOSED_SECONDS;

        // Cek apakah ada baris yang kadaluarsa
        if (! $ignoreExpiry) {
            foreach ($rows as $row) {
                if ($row->last_scanned_at === null) {
                    return null; // Ada yang belum di-scan atau di-invalidate
                }
                $scannedAt = strtotime($row->last_scanned_at);
                if ((time() - $scannedAt) > $ttlSeconds) {
                    return null; // Cache kadaluarsa
                }
            }
        }

        // Build result map dari DB rows
        $result = [];
        foreach ($rows as $row) {
            $key = $row->region_name . '|' . $row->unit_name;
            $result[$key] = [
                'region_name'       => $row->region_name,
                'unit_name'         => $row->unit_name,
                'drive_folder_id'   => $row->drive_folder_id,
                'image_file_count'  => (int) $row->image_file_count,
                'drive_modified_at' => $row->drive_modified_at,
            ];
        }

        Log::info('DriveLeafScannerService: Cache DB hit', [
            'monthFolderId' => $monthFolderId,
            'rowCount'      => count($result),
        ]);

        return $result;
    }

    /**
     * Simpan hasil scan ke DB cache.
     * Menggunakan upsert berdasarkan drive_folder_id (UNIQUE key).
     *
     * @param  string  $monthFolderId
     * @param  string  $monthPeriod
     * @param  array   $leafResults   Hasil dari findLeafFoldersWithName()
     */
    private function saveToDbCache(
        string $monthFolderId,
        string $monthPeriod,
        array $leafResults
    ): void {
        if (empty($leafResults)) {
            return;
        }

        $now = now();

        $rows = [];
        foreach ($leafResults as $leaf) {
            $rows[] = [
                'drive_folder_id'   => $leaf['drive_folder_id'],
                'month_folder_id'   => $monthFolderId,
                'month_period'      => $monthPeriod,
                'region_name'       => $leaf['region_name'],
                'unit_name'         => $leaf['unit_name'],
                'image_file_count'  => $leaf['image_file_count'],
                'is_leaf'           => true,
                'last_scanned_at'   => $now,
                'drive_modified_at' => $leaf['drive_modified_at'] ?? null,
                'created_at'        => $now,
                'updated_at'        => $now,
            ];
        }

        // Upsert: SATU record per drive_folder_id.
        // Jika record dengan drive_folder_id yang sama sudah ada, UPDATE.
        // JANGAN insert duplicate.
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table('monitoring_folder_cache')->upsert(
                $chunk,
                ['drive_folder_id'],  // Kolom unique key
                [
                    'month_folder_id',
                    'month_period',
                    'region_name',
                    'unit_name',
                    'image_file_count',
                    'is_leaf',
                    'last_scanned_at',
                    'drive_modified_at',
                    'updated_at',
                ]
            );
        }

        Log::info('DriveLeafScannerService: Cache DB disimpan (upsert)', [
            'monthFolderId' => $monthFolderId,
            'monthPeriod'   => $monthPeriod,
            'rowCount'      => count($rows),
        ]);
    }

    /**
     * Tentukan apakah bulan tertentu masih dalam periode aktif.
     * Bulan aktif = bulan ini atau bulan lalu (masih mungkin ada upload).
     * Bulan lalu lebih dari 1 bulan = dianggap "closed", TTL lebih lama.
     *
     * @param  string  $monthPeriod  Format YYYY-MM
     * @return bool
     */
    private function isActivePeriod(string $monthPeriod): bool
    {
        try {
            $periodDate = \Carbon\Carbon::createFromFormat('Y-m', $monthPeriod);
            $twoMonthsAgo = now()->subMonths(2)->startOfMonth();
            return $periodDate->isAfter($twoMonthsAgo);
        } catch (\Exception) {
            return true; // default: anggap aktif
        }
    }
}
