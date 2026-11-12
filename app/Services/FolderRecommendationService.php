<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Google\GoogleDriveService;
use Illuminate\Support\Facades\Log;

/**
 * FolderRecommendationService
 *
 * Service stateless untuk menentukan rekomendasi nama folder
 * berdasarkan posisi (context) folder yang sedang dibuka.
 *
 * Penamaan folder bulan di Drive bervariasi:
 *   - "1. JAN 2026"        → singkatan, dengan nomor & tahun
 *   - "9. SEPTEMBER 2026"  → nama penuh, dengan nomor & tahun
 *   - "Agustus"            → nama penuh saja, tanpa nomor & tahun
 *
 * Untuk menghindari false-positive, deteksi menggunakan extractMonthIndex()
 * yang mengambil index bulan (1-12) berdasarkan pola nama, bukan substring.
 *
 * Single Source of Truth: config/folder_structure.php
 */
class FolderRecommendationService
{
    /**
     * Template nama bulan resmi yang akan direkomendasikan untuk folder baru.
     * Index array = index bulan - 1 (array[0] = Januari = index 1)
     */
    private const MONTH_TEMPLATES = [
        '01. Januari',
        '02. Februari',
        '03. Maret',
        '04. April',
        '05. Mei',
        '06. Juni',
        '07. Juli',
        '08. Agustus',
        '09. September',
        '10. Oktober',
        '11. November',
        '12. Desember',
    ];

    /**
     * Peta nama/singkatan bulan → index (1-12).
     * Hanya nama PERSIS yang dicocokkan (bukan substring) untuk mencegah
     * false-positive seperti "MARIANA" cocok dengan "MAR".
     */
    private const MONTH_NAME_MAP = [
        // Nama penuh bahasa Indonesia
        'januari'   => 1,  'februari'  => 2,  'maret'     => 3,
        'april'     => 4,  'mei'       => 5,  'juni'      => 6,
        'juli'      => 7,  'agustus'   => 8,  'september' => 9,
        'oktober'   => 10, 'november'  => 11, 'desember'  => 12,
        // Singkatan bahasa Indonesia (3-4 huruf)
        'jan'  => 1,  'feb'  => 2,  'mar'  => 3,  'apr'  => 4,
        'mei'  => 5,  'jun'  => 6,  'jul'  => 7,  'agu'  => 8,
        'aug'  => 8,  'sep'  => 9,  'okt'  => 10, 'oct'  => 10,
        'nov'  => 11, 'des'  => 12, 'dec'  => 12,
    ];

    public function __construct(
        private readonly GoogleDriveService $driveService,
    ) {}

    /**
     * Entry point utama.
     *
     * @param  string  $folderId
     * @return array{level: string, recommendations: array<string>, is_leaf: bool, message: string|null}
     */
    public function getRecommendations(string $folderId): array
    {
        // Ambil metadata folder aktif & ancestor chain
        $folderMeta = $this->driveService->getMetadata($folderId);
        $folderName = $folderMeta['name'] ?? '';
        $parentIds  = $folderMeta['parents'] ?? [];
        $parentId   = $parentIds[0] ?? null;

        $parentName      = null;
        $grandParentName = null;

        if ($parentId) {
            try {
                $parentMeta = $this->driveService->getMetadata($parentId);
                $parentName = $parentMeta['name'] ?? null;

                $gpIds = $parentMeta['parents'] ?? [];
                if (!empty($gpIds)) {
                    try {
                        $gpMeta          = $this->driveService->getMetadata($gpIds[0]);
                        $grandParentName = $gpMeta['name'] ?? null;
                    } catch (\Exception) {}
                }
            } catch (\Exception) {}
        }

        // Ambil isi folder aktif (1x API call)
        $existingFolders = $this->driveService->listFolders($folderId);
        $existingNames   = array_map(fn($f) => $f['name'], $existingFolders);

        $level = $this->detectLevel($folderName, $parentName, $grandParentName, $existingNames);

        Log::info('[FolderRecommendationService] Level', [
            'folder'      => $folderName,
            'parent'      => $parentName,
            'grandparent' => $grandParentName,
            'level'       => $level,
            'existing'    => $existingNames,
        ]);

        return match ($level) {
            'ROOT'   => $this->buildRootRecommendations($existingNames),
            'MONTH'  => $this->buildMonthRecommendations($existingNames),
            'PARENT' => $this->buildParentRecommendations($folderName, $existingNames),
            default  => $this->buildLeafResponse(),
        };
    }

    // ──────────────────────────────────────────────────────────
    // LEVEL DETECTION
    // ──────────────────────────────────────────────────────────

    /**
     * Tentukan level folder saat ini.
     *
     * Hierarki level:
     *   ROOT   → folder aktif berisi (atau akan berisi) folder bulan
     *   MONTH  → folder aktif adalah folder bulan, berisi unit PLN
     *   PARENT → folder aktif adalah unit PLN, berisi sub-unit
     *   UNIT   → folder aktif adalah sub-unit (leaf)
     *
     * Strategi deteksi (berurutan, dari paling spesifik):
     *   1. Grandparent = bulan → UNIT (leaf)
     *   2. Parent = bulan → PARENT
     *   3. Folder aktif = bulan → MONTH
     *   4. Isi folder mengandung unit PLN → MONTH (folder bulan singkatan)
     *   5. Isi folder mengandung folder bulan → ROOT
     *   6. Default → ROOT
     */
    private function detectLevel(
        string  $folderName,
        ?string $parentName,
        ?string $grandParentName,
        array   $existingNames,
    ): string {
        // 1. Grandparent = bulan → kita di UNIT (leaf)
        if ($grandParentName !== null && $this->isMonthFolder($grandParentName)) {
            return 'UNIT';
        }

        // 2. Parent = bulan → kita di dalam folder bulan = PARENT
        if ($parentName !== null && $this->isMonthFolder($parentName)) {
            return 'PARENT';
        }

        // 3. Folder aktif sendiri adalah folder bulan → MONTH
        if ($this->isMonthFolder($folderName)) {
            return 'MONTH';
        }

        // 4. Isi folder mengandung setidaknya 1 unit PLN resmi → MONTH
        //    (menangani: folder bulan yang namanya singkatan, misal "1. JAN 2026")
        if ($this->existingNamesContainParentUnits($existingNames)) {
            return 'MONTH';
        }

        // 5. Isi folder mengandung folder bulan → ROOT
        //    (menangani: sudah ada beberapa bulan, perlu tambah bulan baru)
        if ($this->existingNamesContainMonthFolders($existingNames)) {
            return 'ROOT';
        }

        // 6. Default → ROOT
        return 'ROOT';
    }

    /**
     * Deteksi apakah nama folder adalah folder bulan.
     *
     * Format yang dikenali:
     *   - "1. JAN 2026"        → nomor + singkatan + tahun
     *   - "9. SEPTEMBER 2026"  → nomor + nama penuh + tahun
     *   - "Agustus"            → nama penuh saja
     *   - "Agustus 2026"       → nama penuh + tahun
     *
     * TIDAK cocok dengan:
     *   - "MULP MARIANA"       → "mar" bukan nama bulan standalone
     *   - "MULP MARTAPURA"     → sama
     */
    private function isMonthFolder(string $name): bool
    {
        return $this->extractMonthIndex($name) !== null;
    }

    /**
     * Ekstrak index bulan (1-12) dari nama folder.
     * Mengembalikan null jika nama bukan folder bulan.
     *
     * Pola yang dikenali:
     *   (A) "^{angka}. {bulan}..." → ambil angka sebagai index, validasi 1-12
     *   (B) "{bulan}" atau "{bulan} {tahun}" → cocokkan nama bulan secara PERSIS
     */
    private function extractMonthIndex(string $name): ?int
    {
        $nameTrimmed = trim($name);
        $nameLower   = strtolower($nameTrimmed);

        // Pola A: diawali angka + titik (contoh: "1. JAN 2026", "12. Desember")
        if (preg_match('/^(\d{1,2})\.\s+(\S+)/i', $nameTrimmed, $matches)) {
            $num       = (int) $matches[1];
            $monthPart = strtolower(trim($matches[2]));

            // Validasi: nomor harus 1-12
            if ($num >= 1 && $num <= 12) {
                // Tambahan: pastikan bagian nama bulan dikenali (hindari "1. PLN UP3...")
                if (isset(self::MONTH_NAME_MAP[$monthPart])) {
                    return $num;
                }
                // Jika tidak dikenali tapi nomornya valid, tetap anggap bulan
                // (contoh: "8. Agustus" → "agustus" hanya 1 kata, cek apakah itu nama bulan)
                if (in_array($monthPart, array_keys(self::MONTH_NAME_MAP), true)) {
                    return $num;
                }
            }
        }

        // Pola B: nama bulan berdiri sendiri (dengan atau tanpa tahun di akhir)
        // Hapus tahun 4 digit di akhir jika ada
        $nameWithoutYear = trim(preg_replace('/\s+\d{4}$/', '', $nameLower));

        if (isset(self::MONTH_NAME_MAP[$nameWithoutYear])) {
            return self::MONTH_NAME_MAP[$nameWithoutYear];
        }

        return null;
    }

    /**
     * Cek apakah daftar existing mengandung setidaknya 1 unit PLN resmi.
     */
    private function existingNamesContainParentUnits(array $existingNames): bool
    {
        if (empty($existingNames)) {
            return false;
        }

        $structure = config('folder_structure.parents', []);

        foreach ($existingNames as $name) {
            if ($this->resolveParentName($name, $structure) !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cek apakah daftar existing mengandung setidaknya 1 folder bulan.
     */
    private function existingNamesContainMonthFolders(array $existingNames): bool
    {
        foreach ($existingNames as $name) {
            if ($this->isMonthFolder($name)) {
                return true;
            }
        }
        return false;
    }

    // ──────────────────────────────────────────────────────────
    // RECOMMENDATION BUILDERS
    // ──────────────────────────────────────────────────────────

    /**
     * ROOT: Rekomendasikan bulan yang belum ada.
     *
     * Perbandingan menggunakan MONTH INDEX (1-12), bukan string matching,
     * sehingga "1. JAN 2026" dan "01. Januari" sama-sama index 1.
     */
    private function buildRootRecommendations(array $existingNames): array
    {
        // Kumpulkan index bulan yang sudah ada
        $existingMonthIndices = [];
        foreach ($existingNames as $name) {
            $idx = $this->extractMonthIndex($name);
            if ($idx !== null) {
                $existingMonthIndices[] = $idx;
            }
        }

        $recommendations = [];
        foreach (self::MONTH_TEMPLATES as $i => $template) {
            $monthIndex = $i + 1; // MONTH_TEMPLATES[0] = Januari = index 1
            if (!in_array($monthIndex, $existingMonthIndices, true)) {
                $recommendations[] = $template;
            }
        }

        return [
            'level'           => 'ROOT',
            'recommendations' => $recommendations,
            'is_leaf'         => false,
            'message'         => null,
        ];
    }

    /**
     * MONTH: Rekomendasikan unit PLN yang belum ada di folder bulan ini.
     */
    private function buildMonthRecommendations(array $existingNames): array
    {
        $structure       = config('folder_structure.parents', []);
        $recommendations = [];

        foreach (array_keys($structure) as $canonicalName) {
            if (!$this->existsInList($canonicalName, $existingNames)) {
                $recommendations[] = $canonicalName;
            }
        }

        return [
            'level'           => 'MONTH',
            'recommendations' => $recommendations,
            'is_leaf'         => false,
            'message'         => null,
        ];
    }

    /**
     * PARENT: Rekomendasikan sub-unit yang belum ada di folder unit PLN ini.
     */
    private function buildParentRecommendations(string $currentFolderName, array $existingNames): array
    {
        $structure       = config('folder_structure.parents', []);
        $canonicalParent = $this->resolveParentName($currentFolderName, $structure);

        if ($canonicalParent === null) {
            Log::warning('[FolderRecommendationService] Parent tidak ditemukan di config', [
                'folder_name' => $currentFolderName,
            ]);

            return [
                'level'           => 'PARENT',
                'recommendations' => [],
                'is_leaf'         => false,
                'message'         => 'Struktur folder tidak dikenali dalam konfigurasi PLN S2JB. Ketik nama folder secara manual.',
            ];
        }

        $units           = $structure[$canonicalParent]['units'] ?? [];
        $recommendations = [];

        foreach ($units as $unitName) {
            if (!$this->existsInList($unitName, $existingNames)) {
                $recommendations[] = $unitName;
            }
        }

        return [
            'level'           => 'PARENT',
            'recommendations' => $recommendations,
            'is_leaf'         => false,
            'message'         => null,
        ];
    }

    /**
     * UNIT (leaf): Tidak ada subfolder yang dapat dibuat.
     */
    private function buildLeafResponse(): array
    {
        return [
            'level'           => 'UNIT',
            'recommendations' => [],
            'is_leaf'         => true,
            'message'         => 'Tidak ada folder yang dapat dibuat lagi. Folder ini merupakan level terakhir.',
        ];
    }

    // ──────────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────────

    /**
     * Normalisasi nama: lowercase, hapus prefix angka/huruf, hapus karakter non-alfanumerik.
     */
    private function normalize(string $name): string
    {
        $s = strtolower(trim($name));
        $s = preg_replace('/^[0-9]+[a-z]?\.\s*/i', '', $s); // hapus "01. " atau "a. "
        $s = preg_replace('/[^a-z0-9 ]/i', '', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }

    /**
     * Cek apakah templateName sudah ada di daftar existing.
     * Menggunakan exact match + alias matching dari config.
     */
    private function existsInList(string $templateName, array $existingNames): bool
    {
        $normTemplate = $this->normalize($templateName);

        foreach ($existingNames as $existing) {
            $normExisting = $this->normalize($existing);

            if ($normTemplate === $normExisting) {
                return true;
            }

            // Partial match untuk menangani variasi prefix
            if (
                strlen($normTemplate) >= 5 &&
                strlen($normExisting) >= 5 &&
                (str_contains($normExisting, $normTemplate) || str_contains($normTemplate, $normExisting))
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cocokkan nama folder dengan canonical parent PLN di config.
     * Mendukung: exact match, normalized match, alias match, partial match.
     */
    private function resolveParentName(string $folderName, array $structure): ?string
    {
        $target     = strtoupper(trim($folderName));
        $normTarget = $this->normalize($folderName);

        // 1. Exact match uppercase
        if (isset($structure[$target])) {
            return $target;
        }

        // 2. Exact match normalized
        foreach ($structure as $canonical => $info) {
            if ($this->normalize($canonical) === $normTarget) {
                return $canonical;
            }
        }

        // 3. Alias exact match
        foreach ($structure as $canonical => $info) {
            foreach ($info['aliases'] ?? [] as $alias) {
                if (strtoupper(trim($alias)) === $target || $this->normalize($alias) === $normTarget) {
                    return $canonical;
                }
            }
        }

        // 4. Partial match (minimal 5 karakter untuk menghindari false-positive)
        foreach ($structure as $canonical => $info) {
            $normCanonical = $this->normalize($canonical);
            if (
                strlen($normTarget) >= 5 &&
                strlen($normCanonical) >= 5 &&
                (str_contains($normTarget, $normCanonical) || str_contains($normCanonical, $normTarget))
            ) {
                return $canonical;
            }
        }

        return null;
    }
}
