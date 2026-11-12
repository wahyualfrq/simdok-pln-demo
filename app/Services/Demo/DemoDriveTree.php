<?php

declare(strict_types=1);

namespace App\Services\Demo;

/**
 * DemoDriveTree
 *
 * DEMO: Builder pohon Google Drive dummy yang DETERMINISTIK (tanpa random,
 * tanpa membaca data/ maupun Google). Hierarki mengikuti logika yang ada:
 *
 *   Drive Saya ('root')
 *   ├── Root Folder demo
 *   │   ├── <spreadsheet dummy>
 *   │   └── Bulan ("12. DES 2025" … "7. JULI 2026")
 *   │       └── Region ("KANTOR UID", "PLN UP3 PALEMBANG", …)
 *   │           ├── mode folder  → Unit ("a. MULP KENTEN", …) → File
 *   │           └── mode file    → File (nama berawalan nama unit)
 *   └── folder lain (agar daftar Root Folder punya pilihan)
 *
 * Nama region/unit berasal dari config/folder_structure.php (identitas PLN
 * dipertahankan). Nama file, ukuran, dan tanggal murni dummy.
 * ID: demo_<tipe>_<nomor> (cocok regex route [a-zA-Z0-9_-]+).
 *
 * Node internal: id, name, mime, folder(bool), parent, size(int byte),
 * created, modified (RFC3339 UTC), kind.
 *
 * Sesuai dengan: rules/14_DEMO_MOCK_SPEC.md §5.3
 */
class DemoDriveTree
{
    public const FOLDER_MIME = 'application/vnd.google-apps.folder';

    /** ID induk virtual: "Drive Saya" (alias 'root' pada Google Drive API). */
    public const MY_DRIVE = 'root';

    private const XLSX_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /** @var array<string, array>|null Cache per proses (data identik untuk semua pengunjung). */
    private static ?array $nodes = null;

    /** @var array<string, list<string>>|null parentId => daftar id anak (urutan pembuatan). */
    private static ?array $children = null;

    /** @var list<array>|null Slot (unit × bulan): sumber tunggal untuk Sheet palsu & DemoSeeder. */
    private static ?array $slots = null;

    /**
     * Seluruh node dasar (tanpa overlay mutasi).
     *
     * @return array<string, array>
     */
    public function nodes(): array
    {
        $this->ensureBuilt();

        return self::$nodes;
    }

    /**
     * Id anak langsung dari sebuah parent (urutan pembuatan).
     *
     * @return list<string>
     */
    public function childIds(string $parentId): array
    {
        $this->ensureBuilt();

        return self::$children[$parentId] ?? [];
    }

    public function find(string $id): ?array
    {
        $this->ensureBuilt();

        return self::$nodes[$id] ?? null;
    }

    /**
     * Seluruh slot unit × bulan (urutan: bulan → parent → unit).
     *
     * Setiap slot: key ("<nama folder bulan>|<region>|<unit>"), period (YYYY-MM), month (nama folder bulan),
     * monthId, region (nama folder region), regionId, canonical (nama kanonikal config), mode ('folder'|'file'),
     * unit (nama unit dari config), folderId (folder unit / folder region untuk mode file), hasFiles, fileCount,
     * sample (nama file contoh|null), pending (ada file tetapi belum difinalisasi → checklist kosong).
     *
     * @return list<array>
     */
    public function slots(): array
    {
        $this->ensureBuilt();

        return self::$slots;
    }

    /**
     * Ringkasan jumlah node untuk laporan/uji.
     *
     * @return array<string, int>
     */
    public function stats(): array
    {
        $this->ensureBuilt();

        $stats = ['total' => count(self::$nodes), 'folders' => 0, 'files' => 0];
        foreach (self::$nodes as $node) {
            $stats[$node['kind']] = ($stats[$node['kind']] ?? 0) + 1;
            $stats[$node['folder'] ? 'folders' : 'files']++;
        }

        return $stats;
    }

    // ─────────────────────────────────────────────────────────
    // PEMBANGUN POHON
    // ─────────────────────────────────────────────────────────

    private function ensureBuilt(): void
    {
        if (self::$nodes !== null) {
            return;
        }

        $nodes = [];
        $slots = [];
        $seq   = ['month' => 0, 'region' => 0, 'unit' => 0, 'file' => 0, 'folder' => 0];
        $cfg   = (array) config('demo.drive');

        $rootId = (string) config('demo.root_folder_id');
        $rootAt = (string) $cfg['root_created_at'];

        $nodes[$rootId] = $this->node($rootId, (string) config('demo.root_folder_name'), true, self::MY_DRIVE, 'root', $rootAt, $rootAt);

        // Spreadsheet dummy di Root (id = config('demo.spreadsheet_id')) agar findSpreadsheetInFiles() menemukannya.
        $sheetId = (string) config('demo.spreadsheet_id');
        $nodes[$sheetId] = $this->node($sheetId, (string) $cfg['spreadsheet_name'], false, $rootId, 'sheet', $rootAt, $rootAt, self::XLSX_MIME, (int) $cfg['spreadsheet_size']);

        // Folder lain di Drive Saya.
        foreach ((array) $cfg['decoy_folders'] as $decoyName) {
            $id = $this->id('folder', $seq);
            $nodes[$id] = $this->node($id, (string) $decoyName, true, self::MY_DRIVE, 'decoy', $rootAt, $rootAt);
        }

        $structure = (array) config('folder_structure.parents', []);
        $regionMap = (array) ($cfg['region_folder_names'] ?? []);

        foreach ((array) $cfg['months'] as $month) {
            $period  = (string) $month['period'];
            $filled  = $this->filledKeys($structure, $regionMap, $period, (int) ($month['fill_percent'] ?? $cfg['fill_percent']));
            $monthId = $this->id('month', $seq);
            $nodes[$monthId] = $this->node($monthId, (string) $month['name'], true, $rootId, 'month', $this->stamp($period, 1), $this->stamp($period, 28));

            foreach ($structure as $canonical => $info) {
                $regionName = (string) ($regionMap[$canonical] ?? $canonical);
                $regionId   = $this->id('region', $seq);
                $nodes[$regionId] = $this->node($regionId, $regionName, true, $monthId, 'region', $this->stamp($period, 2), $this->stamp($period, $this->hash($regionName . $period)));

                $hasSubfolder = (bool) ($info['has_subfolder'] ?? true);

                foreach ((array) ($info['units'] ?? []) as $unitName) {
                    $key = $period . '|' . $regionName . '|' . $unitName;

                    if ($hasSubfolder) {
                        // MODE FOLDER: setiap unit = subfolder berisi file.
                        $unitId = $this->id('unit', $seq);
                        $nodes[$unitId] = $this->node($unitId, (string) $unitName, true, $regionId, 'unit', $this->stamp($period, 3), $this->stamp($period, $this->hash($key)));
                        $files    = $this->addFiles($nodes, $seq, $unitId, $period, $key, '', isset($filled[$key]));
                        $folderId = $unitId;
                    } else {
                        // MODE FILE: file langsung di folder region; nama berawalan nama unit.
                        $files    = $this->addFiles($nodes, $seq, $regionId, $period, $key, (string) $unitName . ' - ', isset($filled[$key]));
                        $folderId = $regionId;
                    }

                    $slots[] = [
                        'key'       => (string) $month['name'] . '|' . $regionName . '|' . $unitName,
                        'period'    => $period,
                        'month'     => (string) $month['name'],
                        'monthId'   => $monthId,
                        'region'    => $regionName,
                        'regionId'  => $regionId,
                        'canonical' => (string) $canonical,
                        'mode'      => $hasSubfolder ? 'folder' : 'file',
                        'unit'      => (string) $unitName,
                        'folderId'  => $folderId,
                        'hasFiles'  => $files['count'] > 0,
                        'fileCount' => $files['count'],
                        'sample'    => $files['sample'],
                        'pending'   => $files['count'] > 0 && $this->hash($key . '#pending') % 100 < (int) $cfg['pending_percent'],
                    ];
                }
            }
        }

        $children = [];
        foreach ($nodes as $id => $node) {
            $children[$node['parent']][] = $id;
        }

        self::$nodes    = $nodes;
        self::$children = $children;
        self::$slots    = $slots;
    }

    /**
     * Himpunan key unit yang "sudah upload" pada sebuah bulan: tepat round(jumlah × fill%) unit,
     * dipilih dari peringkat hash terendah (deterministik, tersebar acak antar region).
     *
     * @return array<string, true>
     */
    private function filledKeys(array $structure, array $regionMap, string $period, int $fillPercent): array
    {
        $keys = [];
        foreach ($structure as $canonical => $info) {
            $regionName = (string) ($regionMap[$canonical] ?? $canonical);
            foreach ((array) ($info['units'] ?? []) as $unitName) {
                $keys[] = $period . '|' . $regionName . '|' . $unitName;
            }
        }

        usort($keys, fn (string $a, string $b) => [$this->hash($a), $a] <=> [$this->hash($b), $b]);

        return array_fill_keys(array_slice($keys, 0, (int) round(count($keys) * $fillPercent / 100)), true);
    }

    /**
     * Menambahkan file dummy ke sebuah folder bila unit "sudah upload" (deterministik).
     */
    private function addFiles(array &$nodes, array &$seq, string $parentId, string $period, string $key, string $prefix, bool $filled): array
    {
        if (! $filled) {
            return ['count' => 0, 'sample' => null]; // unit belum upload → folder kosong
        }

        $cfg  = (array) config('demo.drive');
        $hash = $this->hash($key);

        [$minFiles, $maxFiles] = $cfg['files_per_leaf'];
        [$minKb, $maxKb]       = $cfg['file_size_kb'];

        $count = $minFiles + intdiv($hash, 100) % ($maxFiles - $minFiles + 1);

        $sample = null;

        for ($i = 1; $i <= $count; $i++) {
            $fileHash = $this->hash($key . '#' . $i);
            $kind     = $fileHash % 10;
            $number   = str_pad((string) $i, 3, '0', STR_PAD_LEFT);

            if ($i === 1 && $kind < 2) {
                $name = $prefix . 'Laporan_Bulanan_Dummy.pdf';
                $mime = 'application/pdf';
            } elseif ($kind < 8) {
                $name = $prefix . 'Evidence_Diseminasi_' . $number . '.jpg';
                $mime = 'image/jpeg';
            } else {
                $name = $prefix . 'Dokumentasi_Kegiatan_' . $number . '.png';
                $mime = 'image/png';
            }

            $sizeBytes = ($minKb + intdiv($fileHash, 10) % ($maxKb - $minKb + 1)) * 1024;
            $stamp     = $this->stamp($period, $fileHash);

            $id = $this->id('file', $seq);
            $nodes[$id] = $this->node($id, $name, false, $parentId, 'file', $stamp, $stamp, $mime, $sizeBytes);
            $sample ??= $name;
        }

        return ['count' => $count, 'sample' => $sample];
    }

    private function node(string $id, string $name, bool $folder, string $parent, string $kind, string $created, string $modified, ?string $mime = null, int $size = 0): array
    {
        return [
            'id'       => $id,
            'name'     => $name,
            'mime'     => $folder ? self::FOLDER_MIME : (string) $mime,
            'folder'   => $folder,
            'parent'   => $parent,
            'size'     => $size,
            'created'  => $created,
            'modified' => $modified,
            'kind'     => $kind,
        ];
    }

    /**
     * ID berurutan: demo_<tipe>_<nomor>. Nomor file 5 digit, lainnya 4 digit.
     */
    private function id(string $type, array &$seq): string
    {
        $seq[$type]++;

        return 'demo_' . $type . '_' . str_pad((string) $seq[$type], $type === 'file' ? 5 : 4, '0', STR_PAD_LEFT);
    }

    private function hash(string $value): int
    {
        return crc32($value);
    }

    /**
     * Waktu RFC3339 UTC deterministik di dalam bulan $period (YYYY-MM).
     */
    private function stamp(string $period, int $seed): string
    {
        $day    = 1 + $seed % 27;
        $hour   = 7 + intdiv($seed, 28) % 10;
        $minute = intdiv($seed, 280) % 60;
        $second = intdiv($seed, 16800) % 60;

        return sprintf('%s-%02dT%02d:%02d:%02d.000Z', $period, $day, $hour, $minute, $second);
    }
}
