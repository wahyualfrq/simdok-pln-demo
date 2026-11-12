<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\Google\GoogleDriveService;

class FolderStructureService
{
    /**
     * Mengembalikan seluruh data struktur folder bertingkat dari Single Source of Truth.
     *
     * @return array<string, array{has_subfolder: bool, mode: string, aliases: array<string>, units: array<string>}>
     */
    public function getStructure(): array
    {
        return config('folder_structure.parents', []);
    }

    /**
     * Mengembalikan daftar seluruh nama parent resmi PLN.
     *
     * @return array<int, string>
     */
    public function getParents(): array
    {
        return array_keys($this->getStructure());
    }

    /**
     * Mengembalikan daftar unit/subfolder di bawah parent tertentu.
     *
     * @param  string  $parentName  Nama parent resmi atau aliasnya (misal: "PLN UP3 PALEMBANG" atau "PALEMBANG")
     * @return array<int, string>   Daftar nama unit
     */
    public function getUnits(string $parentName): array
    {
        $canonical = $this->resolveParentCanonical($parentName);
        if (! $canonical) {
            return [];
        }

        $structure = $this->getStructure();
        return $structure[$canonical]['units'] ?? [];
    }

    /**
     * Mengembalikan total seluruh unit di seluruh parent.
     *
     * @return int Total unit slots
     */
    public function getTotalUnits(): int
    {
        $total = 0;
        foreach ($this->getStructure() as $parentInfo) {
            $total += count($parentInfo['units'] ?? []);
        }
        return $total;
    }

    /**
     * Memeriksa apakah parent memiliki subfolder unit (MODE FOLDER / has_subfolder=true)
     * atau langsung berisi file di bawah parent (MODE FILE / has_subfolder=false).
     *
     * @param  string  $parentName
     * @return bool
     */
    public function hasSubfolder(string $parentName): bool
    {
        $canonical = $this->resolveParentCanonical($parentName);
        if (! $canonical) {
            return true; // Default fallback
        }

        $structure = $this->getStructure();
        return (bool) ($structure[$canonical]['has_subfolder'] ?? true);
    }

    /**
     * Mengembalikan mode parent ('folder' atau 'file').
     *
     * @param  string  $parentName
     * @return string
     */
    public function getParentMode(string $parentName): string
    {
        return $this->hasSubfolder($parentName) ? 'folder' : 'file';
    }

    /**
     * Mengembalikan alias dari parent tertentu.
     *
     * @param  string  $parentName
     * @return array<int, string>
     */
    public function getParentAliases(string $parentName): array
    {
        $canonical = $this->resolveParentCanonical($parentName);
        if (! $canonical) {
            return [];
        }

        $structure = $this->getStructure();
        return $structure[$canonical]['aliases'] ?? [];
    }

    /**
     * Mencocokkan nama parent mentah/alias dan mengembalikan nama kanonikal resminya.
     *
     * @param  string  $rawParentName
     * @return string|null Nama kanonikal resmi (contoh: "PLN UP3 PALEMBANG") atau null jika tidak ditemukan
     */
    public function resolveParentCanonical(string $rawParentName): ?string
    {
        $target = strtoupper(trim($rawParentName));
        $targetClean = preg_replace('/^PLN\s+/i', '', $target);

        $structure = $this->getStructure();

        // 1. Exact match
        if (isset($structure[$target])) {
            return $target;
        }

        // 2. Exact match tanpa prefix "PLN "
        foreach ($structure as $canonical => $info) {
            $canonicalClean = preg_replace('/^PLN\s+/i', '', $canonical);
            if ($targetClean === $canonicalClean) {
                return $canonical;
            }
        }

        // 3. Alias match
        foreach ($structure as $canonical => $info) {
            foreach ($info['aliases'] ?? [] as $alias) {
                $aliasUpper = strtoupper(trim($alias));
                if ($target === $aliasUpper || $targetClean === $aliasUpper) {
                    return $canonical;
                }
            }
        }

        // 4. Substring / Partial match
        foreach ($structure as $canonical => $info) {
            $canonicalClean = preg_replace('/^PLN\s+/i', '', $canonical);
            if (
                str_contains($targetClean, $canonicalClean) ||
                str_contains($canonicalClean, $targetClean)
            ) {
                return $canonical;
            }
        }

        return null;
    }

    /**
     * Menghasilkan rekomendasi nama folder bertingkat secara Context-Aware (ROOT, MONTH, PARENT, UNIT).
     *
     * @param  string  $folderId
     * @param  FolderGeneratorService  $generatorService
     * @param  GoogleDriveService      $driveService
     * @return array
     */
    public function getRecommendationsForFolder(string $folderId, FolderGeneratorService $generatorService, GoogleDriveService $driveService): array
    {
        $contextInfo = $generatorService->resolveContextInfo($folderId);
        $contextLevel = $contextInfo['context_level'] ?? 'ROOT';
        $locationPath = $contextInfo['location_path'] ?? 'Lokasi Aktif';

        // Ambil daftar folder yang sudah ada di lokasi aktif (1 Call API)
        $existingFolders = $driveService->listFolders($folderId);
        $existingMap = [];
        $existingNamesList = [];

        foreach ($existingFolders as $f) {
            $norm = $this->normalizeName($f['name']);
            $existingMap[$norm] = $f['name'];
            $existingNamesList[] = strtolower(trim($f['name']));
        }

        $recommendations = [];
        $message = null;

        if ($contextLevel === 'ROOT') {
            // CASE 1: ROOT -> Rekomendasi 12 Nama Bulan Standard
            $monthCandidates = [
                '1. JAN 2026', '2. FEB 2026', '3. MAR 2026', '4. APR 2026',
                '5. MEI 2026', '6. JUNI 2026', '7. JULI 2026', '8. AGUSTUS 2026',
                '9. SEPTEMBER 2026', '10. OKTOBER 2026', '11. NOVEMBER 2026', '12. DESEMBER 2026'
            ];

            foreach ($monthCandidates as $monthName) {
                $normCandidate = $this->normalizeName($monthName);
                $cleanMonth = preg_replace('/^[0-9]+\.\s*/i', '', $monthName);
                $normClean = $this->normalizeName($cleanMonth);

                $exists = false;
                if (isset($existingMap[$normCandidate]) || isset($existingMap[$normClean])) {
                    $exists = true;
                } else {
                    foreach ($existingMap as $existNorm => $origName) {
                        if (str_contains($existNorm, $normClean) || str_contains($normClean, $existNorm)) {
                            $exists = true;
                            break;
                        }
                    }
                }

                $recommendations[] = [
                    'name'   => $monthName,
                    'exists' => $exists,
                    'label'  => $exists ? 'Sudah ada' : 'Belum dibuat',
                ];
            }
        } elseif ($contextLevel === 'MONTH') {
            // CASE 2: MONTH -> Rekomendasi 12 Parent Folders Resmi PLN
            $structure = $this->getStructure();

            foreach ($structure as $officialParentName => $pInfo) {
                $normParent = $this->normalizeName($officialParentName);
                $exists = false;

                if (isset($existingMap[$normParent])) {
                    $exists = true;
                } else {
                    foreach ($pInfo['aliases'] ?? [] as $alias) {
                        if (isset($existingMap[$this->normalizeName($alias)])) {
                            $exists = true;
                            break;
                        }
                    }
                }

                $recommendations[] = [
                    'name'   => $officialParentName,
                    'exists' => $exists,
                    'label'  => $exists ? 'Sudah ada' : 'Belum dibuat',
                ];
            }
        } elseif ($contextLevel === 'PARENT') {
            // CASE 3: PARENT -> Rekomendasi HANYA unit subfolder di bawah Parent ini
            $officialParentName = $contextInfo['official_parent_name'] ?? null;
            $structure = $this->getStructure();

            if (! $officialParentName) {
                $folderMeta = $contextInfo['folder'] ?? [];
                $normCurrent = $this->normalizeName($folderMeta['name'] ?? '');
                foreach ($structure as $pName => $pInfo) {
                    if ($this->normalizeName($pName) === $normCurrent) {
                        $officialParentName = $pName;
                        break;
                    }
                }
            }

            $parentConfig = $structure[$officialParentName] ?? null;
            $units = $parentConfig['units'] ?? [];

            foreach ($units as $officialUnitName) {
                $normTarget = $this->normalizeName($officialUnitName);
                $cleanTarget = $this->normalizeName(preg_replace('/^[a-z0-9]+\.\s*/i', '', $officialUnitName));
                $exists = false;

                if (isset($existingMap[$normTarget]) || isset($existingMap[$cleanTarget])) {
                    $exists = true;
                } else {
                    foreach ($existingMap as $existNorm => $origName) {
                        if (str_contains($existNorm, $cleanTarget) || str_contains($cleanTarget, $existNorm)) {
                            $exists = true;
                            break;
                        }
                    }
                }

                $recommendations[] = [
                    'name'   => $officialUnitName,
                    'exists' => $exists,
                    'label'  => $exists ? 'Sudah ada' : 'Belum dibuat',
                ];
            }
        } else {
            // CASE 4: UNIT / LEAF -> Kosong
            $message = 'Folder ini merupakan folder unit akhir. Tidak ada subfolder yang direkomendasikan.';
        }

        return [
            'context_level'       => $contextLevel,
            'location_path'       => $locationPath,
            'has_recommendations' => ! empty($recommendations),
            'message'             => $message,
            'recommendations'     => $recommendations,
            'existing_names'      => $existingNamesList,
        ];
    }

    /**
     * Normalisasi string nama folder untuk pembandingan bebas karakter khusus.
     */
    private function normalizeName(string $name): string
    {
        $s = strtolower(trim($name));
        $s = preg_replace('/^[a-z0-9]+\.\s*/i', '', $s);
        $s = preg_replace('/[^a-z0-9]/i', '', $s);
        return $s;
    }
}
