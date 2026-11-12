<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Services\Google\GoogleApiService;
use App\Services\Google\GoogleDriveService;
use App\Services\Google\GoogleSheetService;
use Illuminate\Support\Facades\Log;

/**
 * FakeGoogleSheetService
 *
 * DEMO: Pengganti GoogleSheetService untuk versi demo (portofolio).
 * Di-bind lewat AppServiceProvider hanya saat config('demo.enabled').
 * Tidak ada jaringan, tidak ada Google\Service\Sheets/Drive, tidak ada file Office.
 *
 * "Sheet" UNIT diturunkan dari pohon dummy (DemoDriveTree::slots()) sehingga konsisten
 * dengan Drive Explorer & Monitoring: unit berisi file dan sudah final = 'v'; unit kosong
 * atau masih pending = ''. Perubahan checklist (upload/hapus/finalize/write) disimpan di
 * overlay per sesi (DemoDriveStore) — bukan di repo.
 *
 * Tata letak sama dengan sheet asli: baris 0–4 header; baris 5+ data (kolom B = region pada
 * baris pertama grup, kolom C = unit, kolom D–O = bulan: Des=D, Jan=E … Mei=I, Jun=J … Nov=O);
 * ditutup baris "Disusun oleh" (penanda berhenti di kode sync).
 *
 * DIWARISI (logika murni yang hanya memanggil method publik lain via $this):
 *   - updateChecklistAfterUpload() → setChecklistValue($folderId, 'v')
 *
 * DI-OVERRIDE: readRange, getSpreadsheetMetadata, writeRange, batchUpdate,
 *   updateChecklistAfterDelete, setChecklistValue, getChecklistValue, syncSemester,
 *   getSpreadsheetId, invalidateSheetCache.
 *
 * Sesuai dengan: rules/14_DEMO_MOCK_SPEC.md §5.4
 */
class FakeGoogleSheetService extends GoogleSheetService
{
    private const SHEET_NAME = 'UNIT';

    /** Indeks baris pertama data (baris 0–4 adalah header). */
    private const FIRST_DATA_ROW = 5;

    /** Label kolom bulan mulai kolom D (indeks 3): Des, Jan … Nov. */
    private const MONTH_LABELS = ['DES', 'JAN', 'FEB', 'MAR', 'APR', 'MEI', 'JUN', 'JUL', 'AGT', 'SEPT', 'OKT', 'NOV'];

    /** @var array<string, array>|null Indeks slot per key (memo per instance). */
    private ?array $slotIndex = null;

    /**
     * DEMO: sengaja tidak memanggil parent::__construct() (tanpa Google\Client/CacheService).
     */
    public function __construct(
        private readonly DemoDriveTree $demoTree,
        private readonly DemoDriveStore $demoStore,
        private readonly GoogleApiService $demoApi,
        private readonly GoogleDriveService $demoDrive,
        private readonly DemoUnitStatus $demoUnits,
    ) {}

    // ─────────────────────────────────────────────────────────
    // MEMBACA
    // ─────────────────────────────────────────────────────────

    /**
     * @return array<int, array> Array 2D nilai sel (baris tak berujung kosong dibuang, seperti Sheets API).
     */
    public function readRange(string $spreadsheetId, string $range): array
    {
        $matrix = $this->buildMatrix();

        return $this->sliceRange($matrix, $range);
    }

    /**
     * @return array{title: string, sheets: list<string>}
     */
    public function getSpreadsheetMetadata(string $spreadsheetId): array
    {
        return [
            'title'  => pathinfo((string) config('demo.drive.spreadsheet_name'), PATHINFO_FILENAME),
            'sheets' => [self::SHEET_NAME],
        ];
    }

    public function getChecklistValue(string $folderId): string
    {
        $key = $this->slotKeyForFolder($folderId);

        return $key === null ? '' : $this->cellValue($key);
    }

    // ─────────────────────────────────────────────────────────
    // MENULIS (overlay per sesi)
    // ─────────────────────────────────────────────────────────

    /**
     * Mengatur checklist ('v' atau '') untuk unit pada hirarki folder. Folder yang tidak
     * cocok dengan baris sheet (mis. mode file / subfolder dalam) diabaikan diam-diam.
     */
    public function setChecklistValue(string $folderId, string $value): void
    {
        // Status sel Monitoring ikut berubah (sumber kebenaran isi folder) — lihat DemoUnitStatus.
        $this->demoUnits->applyChecklist($folderId, $value);

        $key = $this->slotKeyForFolder($folderId);
        if ($key === null) {
            return;
        }

        $this->demoStore->setChecklist([$key => $value]);

        Log::info('FakeGoogleSheetService::setChecklistValue (overlay demo)', ['folderId' => $folderId, 'value' => $value]);
    }

    /**
     * Jika folder sudah tak berisi file, hapus centang. Jika masih ada file, biarkan.
     */
    public function updateChecklistAfterDelete(string $folderId): void
    {
        if (! $this->demoDrive->hasFiles($folderId)) {
            $this->setChecklistValue($folderId, '');
            // Folder kosong = "Belum Upload" (INCOMPLETE) pada sel Monitoring.
            $this->demoUnits->setFolderStatus($folderId, DemoUnitStatus::INCOMPLETE);
        }
    }

    public function writeRange(string $spreadsheetId, string $range, array $values): void
    {
        $start = $this->parseCell($range);
        if ($start === null) {
            return;
        }

        $this->demoStore->setChecklist($this->changesFor($start['row'], $start['col'], $values));
    }

    /**
     * @param  array<string, string>  $data  Mapping range ke nilai, mis. ['UNIT!K6' => 'v']
     */
    public function batchUpdate(string $spreadsheetId, array $data): void
    {
        $changes = [];
        foreach ($data as $range => $value) {
            $start = $this->parseCell((string) $range);
            if ($start !== null) {
                $changes += $this->changesFor($start['row'], $start['col'], [[$value]]);
            }
        }

        $this->demoStore->setChecklist($changes);
    }

    // ─────────────────────────────────────────────────────────
    // NO-OP / KONFIGURASI
    // ─────────────────────────────────────────────────────────

    public function invalidateSheetCache(): void
    {
        // DEMO: tidak ada cache sheet.
    }

    public function syncSemester(string $semesterName, string $spreadsheetId = ''): void
    {
        Log::info('FakeGoogleSheetService::syncSemester dilewati (simulasi, tanpa pemindaian Drive)', ['semester' => $semesterName]);
    }

    public function getSpreadsheetId(?string $providedId = null): string
    {
        return ! empty($providedId) ? $providedId : $this->demoApi->getSpreadsheetId();
    }

    // ─────────────────────────────────────────────────────────
    // MODEL SHEET (diturunkan dari pohon dummy)
    // ─────────────────────────────────────────────────────────

    /**
     * Matriks penuh sheet UNIT (baris 0–4 header, 5+ data, lalu baris penutup).
     *
     * @return list<array<int, string>>
     */
    private function buildMatrix(): array
    {
        [$unitRows, $monthByCol] = $this->layout();
        $override = $this->demoStore->checklist();
        $slots    = $this->slotsByKey();

        $matrix   = [];
        $matrix[] = ['', 'REKAP MONITORING UPLOAD DOKUMEN (DATA SIMULASI)'];
        $matrix[] = ['', 'PT PLN (Persero) UID S2JB'];
        $matrix[] = [];
        $matrix[] = ['NO', 'UNIT INDUK / UP3', 'UNIT KERJA', 'REALISASI UPLOAD'];
        $matrix[] = array_merge(['', '', ''], self::MONTH_LABELS);

        $previousRegion = null;
        $number         = 0;

        foreach ($unitRows as $row) {
            $number++;
            $cells = [(string) $number, $row['region'] !== $previousRegion ? $row['region'] : '', $row['unit']];
            $previousRegion = $row['region'];

            foreach ($monthByCol as $col => $monthName) {
                $key = $monthName . '|' . $row['region'] . '|' . $row['unit'];
                $cells[$col] = isset($slots[$key]) ? $this->cellValue($key, $override) : '';
            }
            ksort($cells);

            $matrix[] = $this->trimRow(array_replace(array_fill(0, max(array_keys($cells)) + 1, ''), $cells));
        }

        $matrix[] = [];
        $matrix[] = ['', '', 'Disusun oleh'];
        $matrix[] = ['', '', 'Mengetahui'];

        return $matrix;
    }

    /**
     * Baris unit (urut config) dan peta kolom → nama folder bulan.
     *
     * @return array{0: list<array{region: string, unit: string}>, 1: array<int, string>}
     */
    private function layout(): array
    {
        $unitRows   = [];
        $monthByCol = [];
        $firstMonth = null;

        foreach ($this->demoTree->slots() as $slot) {
            $number = (int) substr($slot['period'], 5, 2);
            $col    = $number === 12 ? 3 : 3 + $number;
            $monthByCol[$col] ??= $slot['month'];

            $firstMonth ??= $slot['month'];
            if ($slot['month'] === $firstMonth) {
                $unitRows[] = ['region' => $slot['region'], 'unit' => $slot['unit']];
            }
        }

        return [$unitRows, $monthByCol];
    }

    /**
     * @return array<string, array> slot per key ("bulan|region|unit")
     */
    private function slotsByKey(): array
    {
        if ($this->slotIndex === null) {
            $this->slotIndex = [];
            foreach ($this->demoTree->slots() as $slot) {
                $this->slotIndex[$slot['key']] = $slot;
            }
        }

        return $this->slotIndex;
    }

    /**
     * Nilai sel: perubahan overlay bila ada, jika tidak nilai dasar ('v' bila ada file dan sudah final).
     *
     * @param  array<string, string>|null  $override
     */
    private function cellValue(string $key, ?array $override = null): string
    {
        $override ??= $this->demoStore->checklist();
        if (array_key_exists($key, $override)) {
            return $override[$key];
        }

        $slot = $this->slotsByKey()[$key] ?? null;

        return ($slot !== null && $slot['hasFiles'] && ! $slot['pending']) ? 'v' : '';
    }

    /**
     * Key slot untuk sebuah folder (lewat hirarki Drive), atau null bila tidak cocok dengan baris sheet.
     */
    private function slotKeyForFolder(string $folderId): ?string
    {
        $hierarchy = $this->demoDrive->resolveFolderHierarchy($folderId);
        if (! $hierarchy) {
            return null;
        }

        $key = $hierarchy['month'] . '|' . $hierarchy['region'] . '|' . $hierarchy['unit'];

        return isset($this->slotsByKey()[$key]) ? $key : null;
    }

    /**
     * Perubahan checklist untuk blok nilai yang ditulis mulai (baris, kolom) — hanya sel data bulan.
     *
     * @param  array<int, array>  $values
     * @return array<string, string>
     */
    private function changesFor(int $row, int $col, array $values): array
    {
        [$unitRows, $monthByCol] = $this->layout();
        $changes = [];

        foreach (array_values($values) as $dr => $line) {
            foreach (array_values((array) $line) as $dc => $value) {
                $unitRow = $unitRows[$row + $dr - self::FIRST_DATA_ROW] ?? null;
                $month   = $monthByCol[$col + $dc] ?? null;

                if ($unitRow !== null && $month !== null) {
                    $changes[$month . '|' . $unitRow['region'] . '|' . $unitRow['unit']] = trim((string) $value);
                }
            }
        }

        return $changes;
    }

    // ─────────────────────────────────────────────────────────
    // NOTASI A1
    // ─────────────────────────────────────────────────────────

    /**
     * Sel awal dari notasi A1 (mis. "UNIT!K6" → baris 5, kolom 10; baris/kolom berindeks 0).
     *
     * @return array{row: int, col: int}|null
     */
    private function parseCell(string $range): ?array
    {
        if (! preg_match('/^(?:.+!)?\$?([A-Z]+)\$?(\d+)/i', trim($range), $m)) {
            return null;
        }

        return ['row' => (int) $m[2] - 1, 'col' => $this->columnIndex($m[1])];
    }

    private function columnIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split(strtoupper($letters)) as $char) {
            $index = $index * 26 + (ord($char) - 64);
        }

        return $index - 1;
    }

    /**
     * Memotong matriks sesuai range A1 (mis. "UNIT!A1:P500"); range tak dikenal → seluruh matriks.
     *
     * @param  list<array<int, string>>  $matrix
     * @return list<array<int, string>>
     */
    private function sliceRange(array $matrix, string $range): array
    {
        if (! preg_match('/^(?:.+!)?\$?([A-Z]+)\$?(\d+)(?::\$?([A-Z]+)\$?(\d+))?$/i', trim($range), $m)) {
            return $this->dropTrailingEmptyRows($matrix);
        }

        $firstCol = $this->columnIndex($m[1]);
        $firstRow = (int) $m[2] - 1;
        $lastCol  = isset($m[3]) && $m[3] !== '' ? $this->columnIndex($m[3]) : $firstCol;
        $lastRow  = isset($m[4]) && $m[4] !== '' ? (int) $m[4] - 1 : $firstRow;

        $result = [];
        for ($r = $firstRow; $r <= $lastRow && $r < count($matrix); $r++) {
            $result[] = $this->trimRow(array_slice(array_pad($matrix[$r], $lastCol + 1, ''), $firstCol, $lastCol - $firstCol + 1));
        }

        return $this->dropTrailingEmptyRows($result);
    }

    /**
     * Sheets API membuang sel kosong di ujung baris.
     *
     * @param  array<int, string>  $row
     * @return array<int, string>
     */
    private function trimRow(array $row): array
    {
        while ($row !== [] && end($row) === '') {
            array_pop($row);
        }

        return array_values($row);
    }

    /**
     * @param  list<array>  $rows
     * @return list<array>
     */
    private function dropTrailingEmptyRows(array $rows): array
    {
        while ($rows !== [] && end($rows) === []) {
            array_pop($rows);
        }

        return $rows;
    }
}
