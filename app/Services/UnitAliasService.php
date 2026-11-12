<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * UnitAliasService
 *
 * Membaca file konfigurasi alias unit (unit_aliases_verified.txt) SATU KALI
 * dan menyediakan helper untuk pencarian evidence berbasis alias.
 *
 * Fitur:
 * ─────
 * • Membaca & mem-parsing file alias secara lazy (saat pertama kali dibutuhkan).
 * • Menyimpan hasil parsing sebagai singleton (satu instance per request lifecycle).
 * • Mendukung pencarian:
 *     - getAliases()       → seluruh alias untuk satu nama unit di Google Sheet
 *     - hasSubfolder()     → apakah parent punya subfolder unit (MODE 1) atau tidak (MODE 2)
 *     - matchesAlias()     → cek apakah string kandidat cocok dengan salah satu alias
 *     - findUnitByFile()   → deteksi unit dari nama file (untuk MODE 2)
 *
 * Format file (unit_aliases_verified.txt):
 * ─────────────────────────────────────────
 * File ditulis sebagai teks panjang yang di-wrap bukan newline per entry.
 * Parser menggunakan regex untuk menemukan pola: `NAMA_UNIT = alias1, alias2, alias3`
 * dan pola parent: `PARENT_NAME : has_subfolder=true/false`
 *
 * Normalisasi matching (sesuai spesifikasi):
 * ─────────────────────────────────────────
 * • case-insensitive
 * • ignore extension (.jpg, .jpeg, .png)
 * • ignore spasi berlebih
 * • ignore underscore dan dash
 * • partial match: alias "MTP" harus match "WA STORY MTP JULI.png"
 *
 * @see unit_aliases_verified.txt
 */
class UnitAliasService
{
    /**
     * Path absolut ke file alias.
     */
    private readonly string $filePath;

    /**
     * Flag apakah data sudah di-load.
     */
    private bool $loaded = false;

    /**
     * Map: normalized sheet name → array alias (as-is dari file)
     *
     * Contoh:
     * 'MULP MARTAPURA' => ['ULP MARTAPURA', 'ULP MTP', 'MTP']
     *
     * @var array<string, array<int, string>>
     */
    private array $aliasMap = [];

    /**
     * Map: normalized parent name → has_subfolder (bool)
     *
     * Contoh:
     * 'KANTOR UID' => false,
     * 'PLN UP3 PALEMBANG' => true,
     *
     * @var array<string, bool>
     */
    private array $parentMap = [];

    /**
     * Map: normalized parent name → array of sheet unit names (untuk scope alias lookup).
     *
     * Contoh:
     * 'KANTOR UID' => ['GM', 'SM PERENCANAAN', 'SM DISTRIBUSI', ...]
     * 'PLN UP2D S2JB' => ['MUP2D S2JB', 'TL LAKDAN']
     *
     * @var array<string, array<int, string>>
     */
    private array $parentUnitsMap = [];

    /**
     * @param  string  $filePath  Path absolut ke unit_aliases_verified.txt
     */
    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PUBLIC API
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Kembalikan semua alias untuk nama unit yang ada di Google Sheet.
     *
     * Alias mencakup nama aslinya juga (untuk fallback).
     * Jika unit tidak ditemukan di file alias, kembalikan array berisi nama aslinya saja.
     *
     * @param  string  $sheetUnitName  Nama unit dari Google Sheet (misal "MULP MARTAPURA")
     * @return array<int, string>      Daftar alias (misal ["ULP MARTAPURA", "ULP MTP", "MTP", "MULP MARTAPURA"])
     */
    public function getAliases(string $sheetUnitName): array
    {
        $this->ensureLoaded();

        $key = $this->normalizeKey($sheetUnitName);

        if (! isset($this->aliasMap[$key])) {
            $cleaned = preg_replace('/^[a-z0-9]+\.\s*/i', '', trim($sheetUnitName));
            $cleanedKey = $this->normalizeKey($cleaned);
            if (isset($this->aliasMap[$cleanedKey])) {
                $key = $cleanedKey;
            }
        }

        if (isset($this->aliasMap[$key])) {
            $aliases = $this->aliasMap[$key];

            // Tambahkan nama asli sebagai fallback jika belum ada
            $sheetNameUpper = strtoupper(trim($sheetUnitName));
            if (! in_array($sheetNameUpper, array_map('strtoupper', $aliases), true)) {
                $aliases[] = $sheetNameUpper;
            }

            return $aliases;
        }

        // Tidak ditemukan di map → kembalikan nama asli saja (fallback)
        return [strtoupper(trim($sheetUnitName))];
    }

    /**
     * Periksa apakah parent memiliki subfolder unit (MODE 1) atau tidak (MODE 2).
     *
     * MODE 1 (has_subfolder=true):
     *   Parent → subfolder unit → file gambar
     *   Contoh: PLN UP3 LAHAT → ULP MTP → gambar
     *
     * MODE 2 (has_subfolder=false):
     *   Parent → file gambar langsung (dicocokkan via nama file + alias)
     *   Contoh: KANTOR UID → GM IG JULI.png
     *
     * @param  string  $parentName  Nama parent dari Google Sheet (misal "PLN UP3 LAHAT")
     * @return bool                 true jika MODE 1, false jika MODE 2
     */
    public function hasSubfolder(string $parentName): bool
    {
        $this->ensureLoaded();

        $key = $this->normalizeKey($parentName);

        // Exact match dulu
        if (isset($this->parentMap[$key])) {
            return $this->parentMap[$key];
        }

        // Partial match: "UP3 PALEMBANG" harus match "PLN UP3 PALEMBANG"
        foreach ($this->parentMap as $mapKey => $hasSubfolder) {
            if (str_contains($key, $mapKey) || str_contains($mapKey, $key)) {
                return $hasSubfolder;
            }
        }

        // Default: anggap punya subfolder (MODE 1) agar backward compatible
        return true;
    }

    /**
     * Periksa apakah string kandidat (nama folder Drive atau nama file) cocok
     * dengan salah satu alias dari unit yang ada di Google Sheet.
     *
     * Mendukung:
     * • Case-insensitive
     * • Ignore extension
     * • Ignore spasi, dash, underscore
     * • Partial match (alias "MTP" match "WA STORY MTP JULI.png")
     *
     * @param  string  $candidate      Nama folder Drive atau nama file
     * @param  string  $sheetUnitName  Nama unit dari Google Sheet
     * @return bool
     */
    public function matchesAlias(string $candidate, string $sheetUnitName): bool
    {
        $aliases = $this->getAliases($sheetUnitName);
        $normCandidate = $this->normalizeForMatch($candidate);

        foreach ($aliases as $alias) {
            $normAlias = $this->normalizeForMatch($alias);

            if ($normAlias === '') {
                continue;
            }

            // Exact match
            if ($normCandidate === $normAlias) {
                return true;
            }

            // Partial match: alias ada di dalam kandidat
            if (str_contains($normCandidate, $normAlias)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Kembalikan daftar unit Sheet yang dimiliki oleh sebuah parent (untuk scope).
     *
     * Digunakan oleh MODE 2 agar alias lookup hanya berjalan untuk unit
     * yang benar-benar berada di bawah parent tersebut.
     *
     * Jika parent tidak ditemukan di parentUnitsMap, kembalikan array kosong
     * (caller harus memutuskan fallback-nya sendiri).
     *
     * @param  string  $parentName  Nama parent dari Google Sheet (misal "KANTOR UID")
     * @return array<int, string>  Daftar nama unit Sheet (misal ['GM', 'SM DISTRIBUSI', ...])
     */
    public function getUnitsForParent(string $parentName): array
    {
        $this->ensureLoaded();

        $key = $this->normalizeKey($parentName);

        // Exact match
        if (isset($this->parentUnitsMap[$key])) {
            return $this->parentUnitsMap[$key];
        }

        // Partial match: "UP2D S2JB" match "PLN UP2D S2JB"
        foreach ($this->parentUnitsMap as $mapKey => $units) {
            if (str_contains($key, $mapKey) || str_contains($mapKey, $key)) {
                return $units;
            }
        }

        return [];
    }

    /**
     * Deteksi nama unit berdasarkan nama file, SCOPED ke parent tertentu.
     *
     * Berbeda dari versi tanpa parent:
     * • Hanya mencari di alias milik unit-unit yang terdaftar di bawah $parentName.
     * • Ini mencegah alias "MUP2K" dari UP2K SUMSEL cocok dengan file di KANTOR UID.
     * • Kembalikan nama unit asli dari Google Sheet (BUKAN alias).
     *
     * Jika $parentName kosong atau tidak ditemukan di parentUnitsMap,
     * fallback ke pencarian global (perilaku lama).
     *
     * @param  string       $filename    Nama file Drive (misal "SM DIST.jpeg")
     * @param  string|null  $parentName  Nama parent dari Google Sheet (misal "KANTOR UID")
     * @return string|null               Nama unit Sheet asli jika match, null jika tidak ada
     */
    /**
     * Normalisasi string khusus MODE FILE (no subfolder):
     * - lowercase
     * - hapus extension (.png, .jpg, .jpeg)
     * - ubah '_' dan '-' menjadi spasi
     * - hapus spasi ganda
     *
     * @param  string  $s
     * @return string
     */
    public function normalizeFileModeString(string $s): string
    {
        $str = strtolower(trim($s));
        $str = preg_replace('/\.(png|jpg|jpeg|pdf|webp)$/i', '', $str);
        $str = str_replace(['_', '-'], ' ', $str);
        $str = preg_replace('/\s+/', ' ', $str);
        return trim($str);
    }

    /**
     * Deteksi nama unit berdasarkan nama file, SCOPED ke parent tertentu (MODE 2 / File Mode).
     *
     * Algoritma MODE FILE (no subfolder):
     * 1. Ambil filename tanpa extension, lowercase, ubah '_' dan '-' menjadi spasi, hapus spasi ganda.
     * 2. Ambil seluruh alias unit yang di-scope di bawah parent.
     * 3. Normalisasi alias dengan cara yang sama.
     * 4. Gunakan contains() dua arah: contains(filename, alias) OR contains(alias, filename).
     * 5. Log debug lengkap per perbandingan.
     *
     * @param  string       $filename    Nama file Drive (misal "GM - IG.png")
     * @param  string|null  $parentName  Nama parent dari Google Sheet (misal "KANTOR UID")
     * @return string|null               Nama unit Sheet asli jika match, null jika tidak ada
     */
    public function findUnitByFile(string $filename, ?string $parentName = null): ?string
    {
        $this->ensureLoaded();

        $normFile = $this->normalizeFileModeString($filename);

        // Tentukan scope: unit-unit mana yang boleh dicocokkan
        $scopedUnitKeys = null; // null = tidak dibatasi (global search)

        if ($parentName !== null && $parentName !== '') {
            $scopedUnits = $this->getUnitsForParent($parentName);

            if (! empty($scopedUnits)) {
                $scopedUnitKeys = array_map(
                    fn ($u) => $this->normalizeKey($u),
                    $scopedUnits
                );
                usort($scopedUnitKeys, fn ($a, $b) => strlen($b) - strlen($a));
            } else {
                return null;
            }
        }

        // Iterasi aliasMap — jika ada scope, hanya proses unit yang ada di scope
        foreach ($this->aliasMap as $sheetKey => $aliases) {
            if ($scopedUnitKeys !== null) {
                $inScope = false;

                if (in_array($sheetKey, $scopedUnitKeys, true)) {
                    $inScope = true;
                } else {
                    foreach ($scopedUnitKeys as $scopedKey) {
                        if (str_contains($sheetKey, $scopedKey)) {
                            $inScope = true;
                            break;
                        }
                    }
                }

                if (! $inScope) {
                    continue;
                }
            }

            foreach ($aliases as $alias) {
                $normAlias = $this->normalizeFileModeString($alias);

                if ($normAlias === '') {
                    continue;
                }

                // Two-Way contains(): contains(filename, alias) OR contains(alias, filename)
                $isMatch = str_contains($normFile, $normAlias) || str_contains($normAlias, $normFile);

                Log::debug('UnitAliasService [FILE MODE ALIAS MATCH CHECK]', [
                    'Parent'              => $parentName ?? '(Global)',
                    'Filename'            => $filename,
                    'Normalized Filename' => $normFile,
                    'Alias'               => $alias,
                    'Normalized Alias'    => $normAlias,
                    'Match Result'        => $isMatch ? 'MATCH' : 'NO MATCH',
                ]);

                if ($isMatch) {
                    // Jika scope aktif dan match terjadi via "contains" (bukan exact key),
                    // tentukan scoped unit yang paling tepat untuk alias ini.
                    if ($scopedUnitKeys !== null && ! in_array($sheetKey, $scopedUnitKeys, true)) {
                        foreach ($scopedUnitKeys as $scopedKey) {
                            $scopedAliases = $this->aliasMap[$scopedKey] ?? [];
                            foreach ($this->aliasMap as $k => $kAliases) {
                                if ($k !== $sheetKey && str_contains($k, $scopedKey)) {
                                    $scopedAliases = array_merge($scopedAliases, $kAliases);
                                }
                            }
                            foreach ($scopedAliases as $scopedAlias) {
                                $normScopedAlias = $this->normalizeFileModeString($scopedAlias);
                                if ($normScopedAlias === $normAlias || str_contains($normFile, $normScopedAlias) || str_contains($normScopedAlias, $normFile)) {
                                    return $this->getOriginalName($scopedKey);
                                }
                            }
                        }

                        $bestMatch = null;
                        foreach ($scopedUnitKeys as $scopedKey) {
                            if (str_contains($sheetKey, $scopedKey)) {
                                if ($bestMatch === null || strlen($scopedKey) > strlen($bestMatch)) {
                                    $bestMatch = $scopedKey;
                                }
                            }
                        }
                        if ($bestMatch !== null) {
                            return $this->getOriginalName($bestMatch);
                        }
                    }

                    return $this->getOriginalName($sheetKey);
                }
            }
        }

        return null;
    }

    /**
     * Kembalikan seluruh alias map (untuk debugging/logging).
     *
     * @return array<string, array<int, string>>
     */
    public function getAllAliases(): array
    {
        $this->ensureLoaded();
        return $this->aliasMap;
    }

    /**
     * Kembalikan seluruh parent map (untuk debugging).
     *
     * @return array<string, bool>
     */
    public function getAllParents(): array
    {
        $this->ensureLoaded();
        return $this->parentMap;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE: LOADING & PARSING
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Load file alias jika belum di-load (lazy loading).
     */
    private function ensureLoaded(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->loadFromFile();
        $this->loaded = true;
    }

    /**
     * Baca dan parse file unit_aliases_verified.txt.
     *
     * File ditulis sebagai teks panjang yang di-wrap (bukan satu entry per baris).
     * Strategy parsing:
     * 1. Baca seluruh isi file sebagai string tunggal.
     * 2. Normalisasi whitespace (newline → spasi) agar jadi satu baris panjang.
     * 3. Parse bagian PARENTS dengan regex: `NAMA : has_subfolder=true/false`
     * 4. Parse bagian ALIASES dengan regex: `NAMA_UNIT = alias1, alias2, alias3`
     *    → setiap token `NAMA_UNIT =` menandai awal entry baru.
     */
    private function loadFromFile(): void
    {
        // Populate default parent maps from Single Source of Truth config/folder_structure.php
        $configParents = config('folder_structure.parents', []);
        foreach ($configParents as $parentName => $pData) {
            $hasSubfolder = (bool) ($pData['has_subfolder'] ?? true);
            $key = $this->normalizeKey($parentName);
            $this->parentMap[$key] = $hasSubfolder;

            $withoutPln = preg_replace('/^PLN\s+/i', '', $parentName);
            if ($withoutPln !== $parentName) {
                $this->parentMap[$this->normalizeKey($withoutPln)] = $hasSubfolder;
            }

            if (! empty($pData['units'])) {
                $this->parentUnitsMap[$key] = $pData['units'];
                if ($withoutPln !== $parentName) {
                    $this->parentUnitsMap[$this->normalizeKey($withoutPln)] = $pData['units'];
                }
            }
        }

        if (file_exists($this->filePath)) {
            $content = file_get_contents($this->filePath);
            if ($content !== false && trim($content) !== '') {
                $content = ltrim($content, "\xEF\xBB\xBF");
                $flat = preg_replace('/[\r\n]+/', ' ', $content);
                $flat = preg_replace('/\s{2,}/', ' ', $flat);

                $this->parseParents($flat);
                $this->parseAliases($flat);
                $this->parseParentUnits($flat);
            }
        }

        Log::info('UnitAliasService: Data alias & parent berhasil di-load', [
            'parent_count'      => count($this->parentMap),
            'alias_count'       => count($this->aliasMap),
            'parent_unit_count' => count($this->parentUnitsMap),
        ]);
    }

    /**
     * Parse bagian PARENTS dari konten file.
     *
     * Format: `PARENT_NAME : has_subfolder=true` atau `has_subfolder=false`
     *
     * Karena file di-wrap, pattern seperti:
     * "PLN UP3 MUARA BUNGO : has_subfolder=true PLN UP3 LUBUK LINGGAU : has_subfolder=true"
     *
     * Strategy: temukan semua `has_subfolder=true/false`, lalu backtrack untuk mendapatkan nama parent.
     *
     * @param  string  $flat  Konten file dalam satu baris
     */
    private function parseParents(string $flat): void
    {
        // Extract bagian PARENTS saja (antara "PARENTS" dan "VERIFIED ALIASES")
        if (preg_match('/PARENTS\s+(.*?)\s+VERIFIED ALIASES/i', $flat, $m)) {
            $parentsSection = $m[1];
        } else {
            $parentsSection = $flat;
        }

        // Regex: tangkap nama parent dan nilai has_subfolder
        // Pattern: serangkaian kata (nama parent) diikuti `: has_subfolder=true/false`
        // Nama parent bisa berisi huruf, angka, spasi
        $pattern = '/([A-Z0-9](?:[A-Z0-9\s]*?[A-Z0-9])?)\s*:\s*has_subfolder=(true|false)/i';

        preg_match_all($pattern, $parentsSection, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $parentName   = trim($match[1]);
            $hasSubfolder = strtolower($match[2]) === 'true';

            if ($parentName === '') {
                continue;
            }

            $key = $this->normalizeKey($parentName);
            $this->parentMap[$key] = $hasSubfolder;

            // Simpan juga versi tanpa prefix "PLN " untuk kemudahan lookup
            $withoutPln = preg_replace('/^PLN\s+/i', '', $parentName);
            if ($withoutPln !== $parentName) {
                $this->parentMap[$this->normalizeKey($withoutPln)] = $hasSubfolder;
            }
        }
    }

    /**
     * Parse bagian PARENT_UNITS dari konten file.
     *
     * Format: `PARENT_NAME : unit1, unit2, unit3`
     *
     * Contoh:
     * "KANTOR UID : GM, SM PERENCANAAN, SM DISTRIBUSI"
     * "PLN UP2D S2JB : MUP2D S2JB, TL LAKDAN"
     *
     * @param  string  $flat  Konten file dalam satu baris
     */
    private function parseParentUnits(string $flat): void
    {
        // Baca file asli lagi untuk mendapatkan baris-per-baris yang akurat
        // (section PARENT_UNITS ditulis satu entry per baris, sehingga
        //  tidak perlu di-flatten. Parsing flat string berisiko regex greedy
        //  memotong unit list di tengah.)
        $rawContent = file_get_contents($this->filePath);
        if ($rawContent === false) {
            return;
        }
        $rawContent = ltrim($rawContent, "\xEF\xBB\xBF");

        // Temukan awal section PARENT_UNITS
        $startPos = stripos($rawContent, 'PARENT_UNITS');
        if ($startPos === false) {
            return; // Section tidak ada
        }

        // Ambil teks setelah "PARENT_UNITS" sampai "Catatan:" atau akhir file
        $afterHeader = substr($rawContent, $startPos + strlen('PARENT_UNITS'));
        $endPos = stripos($afterHeader, 'Catatan:');
        if ($endPos !== false) {
            $afterHeader = substr($afterHeader, 0, $endPos);
        }

        // Parse baris per baris — format: "PARENT_NAME : unit1, unit2, unit3"
        $lines = preg_split('/\r?\n/', $afterHeader);
        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || stripos($line, 'PARENT_UNITS') !== false) {
                continue;
            }

            // Hanya proses baris yang mengandung " : " (bukan "has_subfolder")
            if (! str_contains($line, ' : ') || str_contains($line, 'has_subfolder')) {
                continue;
            }

            $colonPos   = strpos($line, ' : ');
            $parentName = trim(substr($line, 0, $colonPos));
            $unitsRaw   = trim(substr($line, $colonPos + 3));

            if ($parentName === '' || $unitsRaw === '') {
                continue;
            }

            $units = array_values(array_filter(
                array_map('trim', explode(',', $unitsRaw)),
                fn ($u) => $u !== ''
            ));

            if (empty($units)) {
                continue;
            }

            $key = $this->normalizeKey($parentName);
            $this->parentUnitsMap[$key] = $units;

            // Juga simpan tanpa prefix "PLN " untuk kemudahan lookup
            $withoutPln = preg_replace('/^PLN\s+/i', '', $parentName);
            if ($withoutPln !== $parentName) {
                $this->parentUnitsMap[$this->normalizeKey($withoutPln)] = $units;
            }
        }
    }

    /**
     * Parse bagian VERIFIED ALIASES dari konten file.
     *
     * Format target: `SHEET_NAME = alias1, alias2, alias3`
     *
     * Karena file di-wrap, kita tidak bisa split per newline.
     * Strategy:
     * 1. Temukan semua posisi pattern `KATA_KATA = ` yang menandai awal entry.
     * 2. Substring dari posisi entry hingga posisi entry berikutnya = nilai entry tersebut.
     * 3. Split nilai oleh koma, tapi hati-hati: NAMA entry berikutnya juga bisa muncul di dalam nilai.
     *
     * Solusi lebih robust: gunakan regex greedy dengan lookahead.
     * Pattern: `([A-Z][A-Z0-9 ]+)\s*=\s*(.*?)(?=\s+[A-Z][A-Z0-9 ]+\s*=|$)`
     *
     * @param  string  $flat  Konten file dalam satu baris
     */
    private function parseAliases(string $flat): void
    {
        // Extract bagian VERIFIED ALIASES saja
        if (preg_match('/VERIFIED ALIASES\s+(.*?)(?:Catatan:|$)/i', $flat, $m)) {
            $aliasSection = trim($m[1]);
        } else {
            $aliasSection = $flat;
        }

        // ────────────────────────────────────────────────────────────────────
        // PENDEKATAN COMMA-SPLIT
        //
        // Insight kunci dari format file:
        //   • Nama entry TIDAK PERNAH mengandung koma.
        //   • Alias DIPISAHKAN oleh koma.
        //   • Entry baru diidentifikasi dari token yang mengandung " = " di dalamnya.
        //
        // Format yang di-wrap:
        //   "GM = GM, GM - IG SM PERENCANAAN = SM PERENCANAAN, SM REN SM DISTRIBUSI = ..."
        //
        // Setelah split dengan koma:
        //   Token 0: "GM = GM"                       → entry: name=GM, firstAlias=GM
        //   Token 1: " GM - IG SM PERENCANAAN = SM PERENCANAAN"  → entry baru: name=GM-IG SM PERENCANAAN
        //   Token 2: " SM REN SM DISTRIBUSI = SM DISTRIBUSI"     → entry baru
        //   ...
        //
        // Setiap token yang mengandung " = " menandai entry baru.
        // Bagian kiri " = " adalah nama entry, bagian kanan adalah alias pertama.
        // Token yang tidak mengandung " = " adalah alias lanjutan dari entry sebelumnya.
        // ────────────────────────────────────────────────────────────────────

        $tokens = explode(',', $aliasSection);

        // Prefix yang dikenal sebagai awal nama entry.
        // Diurutkan dari paling spesifik (panjang) ke paling pendek agar tidak salah match.
        // Catatan: "SM " harus hati-hati karena alias juga bisa dimulai "SM REN", "SM DIST".
        //   → Kita gunakan prefix ini hanya saat ada " = " di belakangnya.
        $knownPrefixes = [
            'MULP ',
            'MUP2D ',
            'MUP2K SUMSEL',
            'MUP2K JAMBI',
            'MUP2K BENGKULU',
            'MUP2K ',
            'MUP3 OGAN ILIR',
            'MUP3 ',
            'SM PERENCANAAN',
            'SM DISTRIBUSI',
            'SM NIAGA',
            'SM KKU',
            'GM',
            'PJ REN DAN',
            'MAN LAKSDA',
            'TL LAKDAN',
        ];

        $currentName    = null;
        $currentAliases = [];

        $saveEntry = function () use (&$currentName, &$currentAliases): void {
            if ($currentName === null || empty($currentAliases)) {
                return;
            }

            $key = $this->normalizeKey($currentName);

            if (isset($this->aliasMap[$key])) {
                $merged = array_values(array_unique(array_merge($this->aliasMap[$key], $currentAliases)));
                $this->aliasMap[$key] = $merged;
            } else {
                $this->aliasMap[$key] = $currentAliases;
            }

            $this->originalNames[$key] = $currentName;
        };

        foreach ($tokens as $token) {
            $token = trim($token);

            if ($token === '') {
                continue;
            }

            // Cek apakah token ini mengandung " = " yang menandai entry baru
            $eqPos = strpos($token, ' = ');

            if ($eqPos !== false) {
                // Bagian kiri " = " mungkin berisi: [alias_sebelumnya NAMA_ENTRY_BARU]
                // Contoh: "BTA MULP LEMBAYUNG = ULP LEMBAYUNG"
                //   → alias sebelumnya = "BTA", nama baru = "MULP LEMBAYUNG"
                //
                // Kita perlu memisahkan alias terakhir dari nama entry baru.
                // Cara: cari prefix nama entry yang dikenal di dalam bagian kiri " = ".

                $leftPart   = trim(substr($token, 0, $eqPos));
                $firstAlias = trim(substr($token, $eqPos + 3));

                // Coba temukan prefix nama entry di dalam $leftPart.
                // Prioritaskan prefix yang muncul di offset > 0 (artinya ada alias sebelumnya di posisi 0).
                $foundEntryStart = null;

                // Pass 1: Cari prefix di offset > 0
                foreach ($knownPrefixes as $prefix) {
                    $prefixPos = strpos($leftPart, $prefix);
                    if ($prefixPos !== false && $prefixPos > 0) {
                        $trailingAlias   = trim(substr($leftPart, 0, $prefixPos));
                        $newEntryName    = trim(substr($leftPart, $prefixPos));
                        $foundEntryStart = ['alias' => $trailingAlias, 'name' => $newEntryName];
                        break;
                    }
                }

                // Pass 2: Jika tidak ada prefix di offset > 0, cari prefix di offset 0
                if ($foundEntryStart === null) {
                    foreach ($knownPrefixes as $prefix) {
                        if (str_starts_with($leftPart, $prefix)) {
                            $foundEntryStart = ['alias' => null, 'name' => $leftPart];
                            break;
                        }
                    }
                }

                if ($foundEntryStart !== null) {
                    // Ada alias terakhir dari entry sebelumnya
                    if ($foundEntryStart['alias'] !== null && $foundEntryStart['alias'] !== '') {
                        if ($currentName !== null) {
                            $currentAliases[] = $foundEntryStart['alias'];
                        }
                    }

                    // Simpan entry sebelumnya
                    $saveEntry();

                    // Handle kasus di mana firstAlias sendiri mengandung " = "
                    // Contoh: "SM KKU = SM KKU PJ REN DAN = PJ REN DAN"
                    // firstAlias = "SM KKU PJ REN DAN = PJ REN DAN"
                    // → split lagi: firstAlias real = "SM KKU PJ REN DAN" (alias sebelum =)
                    //   dan sisanya adalah entry berikutnya yang akan diproses dari tokens[] terpisah
                    if (strpos($firstAlias, ' = ') !== false) {
                        $innerEqPos   = strpos($firstAlias, ' = ');
                        $innerLeft    = trim(substr($firstAlias, 0, $innerEqPos));
                        $innerRemain  = trim(substr($firstAlias, $innerEqPos + 3));

                        // innerLeft mungkin = "SM KKU PJ REN DAN"
                        // Pisahkan alias entry saat ini dari nama entry berikutnya di innerLeft
                        $cleanAlias    = $innerLeft;
                        $embeddedEntry = null;

                        foreach ($knownPrefixes as $prefix) {
                            $pPos = strpos($innerLeft, $prefix);
                            if ($pPos !== false && $pPos > 0) {
                                $cleanAlias    = trim(substr($innerLeft, 0, $pPos));
                                $embeddedEntry = [
                                    'name'    => trim(substr($innerLeft, $pPos)),
                                    'aliases' => $innerRemain !== '' ? [$innerRemain] : [],
                                ];
                                break;
                            }
                            if ($pPos === 0) {
                                $cleanAlias    = '';
                                $embeddedEntry = [
                                    'name'    => $innerLeft,
                                    'aliases' => $innerRemain !== '' ? [$innerRemain] : [],
                                ];
                                break;
                            }
                        }

                        // Mulai entry baru dengan cleanAlias sebagai firstAlias
                        $currentName    = $foundEntryStart['name'];
                        $currentAliases = $cleanAlias !== '' ? [$cleanAlias] : [];

                        // Jika ada embedded entry, simpan entry saat ini dan mulai embedded
                        if ($embeddedEntry !== null) {
                            $saveEntry();
                            $currentName    = $embeddedEntry['name'];
                            $currentAliases = $embeddedEntry['aliases'];
                        }
                    } else {
                        // Mulai entry baru normal
                        $currentName    = $foundEntryStart['name'];
                        $currentAliases = $firstAlias !== '' ? [$firstAlias] : [];
                    }
                } else {
                    // Tidak ada prefix yang dikenal → leftPart seluruhnya adalah nama entry baru
                    $saveEntry();
                    $currentName    = $leftPart;
                    $currentAliases = $firstAlias !== '' ? [$firstAlias] : [];
                }
            } else {
                // Token ini adalah alias lanjutan dari entry sebelumnya
                if ($currentName !== null && $token !== '') {
                    $currentAliases[] = $token;
                }
            }
        }

        // Simpan entry terakhir
        $saveEntry();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PRIVATE: STRING UTILS
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Normalisasi nama sebagai key di aliasMap / parentMap.
     * Uppercase, hapus spasi berlebih.
     *
     * @param  string  $name
     * @return string
     */
    private function normalizeKey(string $name): string
    {
        // Uppercase dan hapus SEMUA spasi agar variasi seperti
        // 'MULP MUARAENIM' vs 'MULP MUARA ENIM' menghasilkan key yang sama.
        return strtoupper(preg_replace('/\s+/', '', trim($name)));
    }

    /**
     * Normalisasi string untuk keperluan matching:
     * • lowercase
     * • hapus extension file (.jpg, .jpeg, .png, .JPG, dll)
     * • hapus spasi, dash, underscore
     *
     * Contoh:
     *   "WA STORY MTP JULI.png" → "wastorymtpjuli"
     *   "GM - IG"               → "gmig"
     *   "ULP MTP"               → "ulpmtp"
     *
     * @param  string  $s
     * @return string
     */
    private function normalizeForMatch(string $s): string
    {
        // Hapus extension file
        $s = preg_replace('/\.(jpg|jpeg|png|pdf|webp)$/i', '', $s);

        // Lowercase
        $s = strtolower(trim($s));

        // Hapus spasi, dash, underscore, titik
        $s = preg_replace('/[\s\-_\.]+/', '', $s);

        return $s;
    }

    /**
     * Kembalikan original name (dari sheet) berdasarkan normalized key.
     *
     * @param  string  $normalizedKey
     * @return string
     */
    private function getOriginalName(string $normalizedKey): string
    {
        return $this->originalNames[$normalizedKey] ?? $normalizedKey;
    }

    /**
     * Simpan mapping normalized key → original sheet name.
     *
     * @var array<string, string>
     */
    private array $originalNames = [];
}
