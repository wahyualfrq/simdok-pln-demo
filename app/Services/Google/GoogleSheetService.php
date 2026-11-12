<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Exceptions\GoogleAuthException;
use App\Services\CacheService;
use App\Services\UnitAliasService;
use App\Services\UnitNameNormalizerService;
use Google\Service\Sheets;
use Illuminate\Support\Facades\Log;
use App\Services\Google\GoogleDriveService;

/**
 * GoogleSheetService
 *
 * Bertanggung jawab untuk seluruh operasi Google Spreadsheet API.
 * Membaca data langsung dari Google Sheets — tidak perlu download file Excel.
 *
 * Google\Client diperoleh dari GoogleApiService.
 * Tidak boleh mengakses OAuth secara langsung.
 *
 * Sesuai dengan: 12_GOOGLE_API_SERVICE.md & 11_MONITORING_GOOGLE_SHEET.md
 */
class GoogleSheetService
{
    private const SHEET_NAME = 'UNIT';

    private const MONTH_MAPPING = [
        'Januari'   => ['col' => 4,  'label' => 'Jan'],   // Kolom E
        'Februari'  => ['col' => 5,  'label' => 'Feb'],   // Kolom F
        'Maret'     => ['col' => 6,  'label' => 'Mar'],   // Kolom G
        'April'     => ['col' => 7,  'label' => 'Apr'],   // Kolom H
        'Mei'       => ['col' => 8,  'label' => 'Mei'],   // Kolom I
        'Juni'      => ['col' => 9,  'label' => 'Jun'],   // Kolom J
        'Juli'      => ['col' => 10, 'label' => 'Jul'],   // Kolom K
        'Agustus'   => ['col' => 11, 'label' => 'Agt'],   // Kolom L
        'September' => ['col' => 12, 'label' => 'Sept'],  // Kolom M
        'Oktober'   => ['col' => 13, 'label' => 'Okt'],   // Kolom N
        'November'  => ['col' => 14, 'label' => 'Nov'],   // Kolom O
        'Desember'  => ['col' => 3,  'label' => 'Des'],   // Kolom D
    ];

    private const SEMESTER_MONTHS = [
        'Semester 1' => ['Desember', 'Januari', 'Februari', 'Maret', 'April', 'Mei'],
        'Semester 2' => ['Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November'],
    ];

    /** Singleton Sheets instance — dibuat sekali per request lifecycle. */
    private ?Sheets $sheets = null;

    public function __construct(
        private readonly GoogleApiService $apiService,
        private readonly GoogleDriveService $driveService,
        private readonly CacheService $cacheService,
        private readonly UnitAliasService $aliasService,
        private readonly UnitNameNormalizerService $normalizer,
    ) {}

    /**
     * Mendapatkan instance Google Service Sheets yang terautentikasi.
     *
     * @return Sheets
     * @throws GoogleAuthException
     */
    /**
     * Mendapatkan instance Google Service Sheets yang terautentikasi.
     * Lazy-init: satu instance per request lifecycle.
     *
     * @return Sheets
     * @throws GoogleAuthException
     */
    private function getSheets(): Sheets
    {
        if ($this->sheets === null) {
            $this->sheets = new Sheets($this->apiService->getClient());
        }
        return $this->sheets;
    }

    /**
     * Invalidate cache baris Google Sheet agar pembacaan berikutnya mengambil data paling segar dari API.
     */
    public function invalidateSheetCache(): void
    {
        $this->cacheService->invalidateSheetRows();
    }

    /**
     * Membaca seluruh nilai dari range tertentu dalam spreadsheet.
     *
     * @param  string  $spreadsheetId  ID Google Spreadsheet
     * @param  string  $range          Notasi A1, contoh: 'Sheet1!A1:Z100'
     * @return array<int, array>  Array 2D nilai sel
     * @throws \Exception
     */
    public function readRange(string $spreadsheetId, string $range): array
    {
        $spreadsheetId = $this->getSpreadsheetId($spreadsheetId);
        // Gunakan cache hanya untuk range UNIT utama agar monitoring tidak baca ulang Sheet
        $isUnitRange = str_contains($range, 'UNIT!A1:P500') || str_contains($range, 'UNIT!A1:P');
        if ($isUnitRange) {
            $cachedRows = $this->cacheService->getSheetRows();
            if ($cachedRows !== null) {
                return $cachedRows;
            }
        }

        try {
            $response = $this->getSheets()->spreadsheets_values->get($spreadsheetId, $range);
            $result   = $response->getValues() ?? [];

            // Simpan ke cache jika ini range UNIT utama
            if ($isUnitRange) {
                $this->cacheService->putSheetRows($result);
            }

            return $result;
        } catch (GoogleAuthException $e) {
            throw $e;
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'must not be an Office file')) {
                Log::info('GoogleSheetService::readRange: Mendeteksi file Excel (.xlsx). Melakukan konversi temporary di Google Drive...');
                $result = $this->readRangeFromOfficeFile($spreadsheetId, $range);

                // Simpan ke cache jika ini range UNIT utama
                if ($isUnitRange) {
                    $this->cacheService->putSheetRows($result);
                }

                return $result;
            }
            Log::error('GoogleSheetService::readRange gagal', [
                'spreadsheetId' => $spreadsheetId,
                'range'         => $range,
                'error'         => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Mendapatkan metadata spreadsheet (nama sheet, dll).
     *
     * @param  string  $spreadsheetId  ID Google Spreadsheet
     * @return array  Metadata spreadsheet
     * @throws \Exception
     */
    public function getSpreadsheetMetadata(string $spreadsheetId): array
    {
        try {
            $response = $this->getSheets()->spreadsheets->get($spreadsheetId);
            return [
                'title'  => $response->getProperties()->getTitle(),
                'sheets' => array_map(
                    fn ($s) => $s->getProperties()->getTitle(),
                    $response->getSheets()
                ),
            ];
        } catch (GoogleAuthException $e) {
            throw $e;
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'must not be an Office file')) {
                Log::info('GoogleSheetService::getSpreadsheetMetadata: Mendeteksi file Excel (.xlsx). Melakukan konversi temporary...');
                return $this->getMetadataFromOfficeFile($spreadsheetId);
            }
            Log::error('GoogleSheetService::getSpreadsheetMetadata gagal', [
                'spreadsheetId' => $spreadsheetId,
                'error'         => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Membaca range dari file Excel (.xlsx) dengan cara mengonversi secara sementara ke Google Sheet.
     */
    private function readRangeFromOfficeFile(string $spreadsheetId, string $range): array
    {
        $drive = new \Google\Service\Drive($this->apiService->getClient());
        $tempSheetId = null;

        try {
            $fileMetadata = new \Google\Service\Drive\DriveFile([
                'name' => 'Temp Converted ' . time(),
                'mimeType' => 'application/vnd.google-apps.spreadsheet',
            ]);

            $copiedFile = $drive->files->copy($spreadsheetId, $fileMetadata);
            $tempSheetId = $copiedFile->getId();

            $response = $this->getSheets()->spreadsheets_values->get($tempSheetId, $range);
            return $response->getValues() ?? [];
        } finally {
            if ($tempSheetId) {
                try {
                    $drive->files->delete($tempSheetId);
                } catch (\Exception $deleteEx) {
                    Log::warning('GoogleSheetService: Gagal menghapus file Excel temporary', [
                        'id'    => $tempSheetId,
                        'error' => $deleteEx->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Mendapatkan metadata dari file Excel (.xlsx) dengan cara mengonversi secara sementara.
     */
    private function getMetadataFromOfficeFile(string $spreadsheetId): array
    {
        $drive = new \Google\Service\Drive($this->apiService->getClient());
        $tempSheetId = null;

        try {
            $fileMetadata = new \Google\Service\Drive\DriveFile([
                'name' => 'Temp Converted Metadata ' . time(),
                'mimeType' => 'application/vnd.google-apps.spreadsheet',
            ]);

            $copiedFile = $drive->files->copy($spreadsheetId, $fileMetadata);
            $tempSheetId = $copiedFile->getId();

            $response = $this->getSheets()->spreadsheets->get($tempSheetId);
            return [
                'title'  => $response->getProperties()->getTitle(),
                'sheets' => array_map(
                    fn ($s) => $s->getProperties()->getTitle(),
                    $response->getSheets()
                ),
            ];
        } finally {
            if ($tempSheetId) {
                try {
                    $drive->files->delete($tempSheetId);
                } catch (\Exception $deleteEx) {
                    Log::warning('GoogleSheetService: Gagal menghapus file Excel temporary', [
                        'id'    => $tempSheetId,
                        'error' => $deleteEx->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Menulis nilai ke range tertentu dalam spreadsheet.
     *
     * @param  string  $spreadsheetId
     * @param  string  $range
     * @param  array   $values  Array 2D nilai yang akan ditulis
     * @return void
     * @throws \Exception
     */
    public function writeRange(string $spreadsheetId, string $range, array $values): void
    {
        try {
            $body = new \Google\Service\Sheets\ValueRange(['values' => $values]);
            $params = ['valueInputOption' => 'USER_ENTERED'];

            $this->getSheets()->spreadsheets_values->update(
                $spreadsheetId,
                $range,
                $body,
                $params
            );

            // Invalidate cache Sheet setelah berhasil menulis
            $this->cacheService->invalidateSheetRows();
        } catch (GoogleAuthException $e) {
            throw $e;
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'must not be an Office file')) {
                Log::info('GoogleSheetService::writeRange: Mendeteksi file Excel (.xlsx). Menulis dengan alur konversi...');
                $this->writeToOfficeFile($spreadsheetId, function (string $tempSheetId) use ($range, $values) {
                    $body = new \Google\Service\Sheets\ValueRange(['values' => $values]);
                    $params = ['valueInputOption' => 'USER_ENTERED'];
                    $this->getSheets()->spreadsheets_values->update($tempSheetId, $range, $body, $params);
                });
                // Invalidate cache Sheet setelah berhasil menulis (via Office)
                $this->cacheService->invalidateSheetRows();
                return;
            }
            Log::error('GoogleSheetService::writeRange gagal', [
                'spreadsheetId' => $spreadsheetId,
                'range'         => $range,
                'error'         => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Melakukan batch update nilai ke beberapa range dalam spreadsheet.
     *
     * @param  string  $spreadsheetId
     * @param  array<string, string>  $data  Mapping range ke nilai, contoh: ['UNIT!K6' => 'v', 'UNIT!L6' => '']
     * @return void
     * @throws \Exception
     */
    public function batchUpdate(string $spreadsheetId, array $data): void
    {
        if (empty($data)) {
            return;
        }

        try {
            $dataEntries = [];
            foreach ($data as $range => $value) {
                $dataEntries[] = new \Google\Service\Sheets\ValueRange([
                    'range'  => $range,
                    'values' => [[$value]],
                ]);
            }

            $body = new \Google\Service\Sheets\BatchUpdateValuesRequest([
                'valueInputOption' => 'USER_ENTERED',
                'data'             => $dataEntries,
            ]);

            $this->getSheets()->spreadsheets_values->batchUpdate($spreadsheetId, $body);
        } catch (GoogleAuthException $e) {
            throw $e;
        } catch (\Exception $e) {
            if (str_contains($e->getMessage(), 'must not be an Office file')) {
                Log::info('GoogleSheetService::batchUpdate: Mendeteksi file Excel (.xlsx). Menulis dengan alur konversi batch...');
                $this->writeToOfficeFile($spreadsheetId, function (string $tempSheetId) use ($data) {
                    $dataEntries = [];
                    foreach ($data as $range => $value) {
                        $dataEntries[] = new \Google\Service\Sheets\ValueRange([
                            'range'  => $range,
                            'values' => [[$value]],
                        ]);
                    }

                    $body = new \Google\Service\Sheets\BatchUpdateValuesRequest([
                        'valueInputOption' => 'USER_ENTERED',
                        'data'             => $dataEntries,
                    ]);

                    $this->getSheets()->spreadsheets_values->batchUpdate($tempSheetId, $body);
                });
                return;
            }
            Log::error('GoogleSheetService::batchUpdate gagal', [
                'spreadsheetId' => $spreadsheetId,
                'error'         => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Menulis ke file Excel (.xlsx) dengan cara mengonversi secara sementara, melakukan update,
     * lalu mengekspor kembali dan menimpa file asli di Google Drive.
     */
    private function writeToOfficeFile(string $spreadsheetId, \Closure $updateCallback): void
    {
        $drive = new \Google\Service\Drive($this->apiService->getClient());
        $tempSheetId = null;

        try {
            // 1. Copy file asli (.xlsx) menjadi Google Sheet sementara
            $fileMetadata = new \Google\Service\Drive\DriveFile([
                'name' => 'Temp Converted Write ' . time(),
                'mimeType' => 'application/vnd.google-apps.spreadsheet',
            ]);

            $copiedFile = $drive->files->copy($spreadsheetId, $fileMetadata);
            $tempSheetId = $copiedFile->getId();

            // 2. Jalankan aksi update pada Google Sheet sementara
            $updateCallback($tempSheetId);

            // 3. Ekspor Google Sheet sementara menjadi file Excel (.xlsx)
            $response = $drive->files->export(
                $tempSheetId,
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                ['alt' => 'media']
            );
            $xlsxContent = $response->getBody()->getContents();

            // 4. Perbarui konten file Excel (.xlsx) asli di Google Drive
            $drive->files->update(
                $spreadsheetId,
                new \Google\Service\Drive\DriveFile(),
                [
                    'data'       => $xlsxContent,
                    'mimeType'   => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    'uploadType' => 'media',
                ]
            );

        } finally {
            // 5. Hapus Google Sheet sementara
            if ($tempSheetId) {
                try {
                    $drive->files->delete($tempSheetId);
                } catch (\Exception $deleteEx) {
                    Log::warning('GoogleSheetService: Gagal menghapus file Excel temporary setelah penulisan', [
                        'id'    => $tempSheetId,
                        'error' => $deleteEx->getMessage(),
                    ]);
                }
            }
        }
    }

    /**
     * Memperbarui checklist di Google Sheet setelah file diunggah ke Google Drive.
     *
     * @param  string  $folderId  Google Drive Folder ID (Unit Folder)
     * @return void
     */
    public function updateChecklistAfterUpload(string $folderId): void
    {
        $this->setChecklistValue($folderId, 'v');
    }

    /**
     * Memperbarui checklist di Google Sheet setelah file dihapus dari Google Drive.
     * Jika folder kosong, hapus centang. Jika masih ada file, biarkan centang tetap "v".
     *
     * @param  string  $folderId  Google Drive Folder ID (Unit Folder)
     * @return void
     */
    public function updateChecklistAfterDelete(string $folderId): void
    {
        $hasFiles = $this->driveService->hasFiles($folderId);
        if (! $hasFiles) {
            $this->setChecklistValue($folderId, '');
        }
    }

    /**
     * Mengatur nilai checklist (v atau '') pada sel yang tepat berdasarkan hirarki folder Drive.
     */
    public function setChecklistValue(string $folderId, string $value): void
    {
        $spreadsheetId = $this->getSpreadsheetId();
        if (empty($spreadsheetId)) {
            Log::warning('GoogleSheetService::setChecklistValue - GOOGLE_SHEET_ID tidak terkonfigurasi.');
            return;
        }

        try {
            $hierarchy = $this->driveService->resolveFolderHierarchy($folderId);
            if (! $hierarchy) {
                Log::warning('GoogleSheetService::setChecklistValue - Gagal mengurai hirarki folder untuk ID: ' . $folderId);
                return;
            }

            $monthNameRaw = $hierarchy['month'];
            $regionName   = $hierarchy['region'];
            $unitName     = $hierarchy['unit'];

            $standardMonth = $this->resolveStandardMonthName($monthNameRaw);
            if (! $standardMonth || ! isset(self::MONTH_MAPPING[$standardMonth])) {
                Log::warning("GoogleSheetService::setChecklistValue - Nama bulan tidak valid: {$monthNameRaw}");
                return;
            }

            $colIdx = self::MONTH_MAPPING[$standardMonth]['col'];

            $range = self::SHEET_NAME . '!A1:P500';
            $rows  = $this->readRange($spreadsheetId, $range);

            $matchedRowIndex = null;
            $currentParent = '';

            foreach ($rows as $rowIndex => $rowData) {
                if ($rowIndex < 5) {
                    continue;
                }

                $colB = trim($rowData[1] ?? '');
                $colC = trim($rowData[2] ?? '');

                if (
                    str_contains(strtolower($colC), 'disusun oleh') ||
                    str_contains(strtolower($colC), 'mengetahui')
                ) {
                    break;
                }

                if ($colB !== '') {
                    $currentParent = $colB;
                }

                $cleanC         = $this->cleanUnitName($colC);
                $cleanUnitName  = $this->cleanUnitName($unitName);

                // Cek 1: exact match (nama persis sama)
                $exactMatch = strtolower(trim($cleanC)) === strtolower(trim($cleanUnitName));

                // Cek 2: alias match — Drive folder name (unitName) bisa merupakan alias
                //   dari unit yang ada di Sheet (cleanC).
                //   Contoh: Drive="manager up3" → Sheet="MUP3 OGAN ILIR"
                $aliasMatch = ! $exactMatch && $this->aliasService->matchesAlias($cleanUnitName, $colC);

                // Cek 3: normalizer match (fallback untuk variasi penulisan)
                $normMatch  = ! $exactMatch && ! $aliasMatch
                              && $this->normalizer->isMatch($cleanC, $cleanUnitName);

                $regionMatch = strtolower(trim($currentParent)) === strtolower(trim($regionName))
                               || $this->normalizer->isMatch($currentParent, $regionName);

                if ($regionMatch && ($exactMatch || $aliasMatch || $normMatch)) {
                    $matchedRowIndex = $rowIndex;
                    break;
                }
            }

            if ($matchedRowIndex === null) {
                Log::warning("GoogleSheetService::setChecklistValue - Baris tidak ditemukan untuk Unit: {$unitName} di Daerah: {$regionName}");
                return;
            }

            $colLetter = $this->getColumnLetter($colIdx);
            $rowNum = $matchedRowIndex + 1;
            $cellRange = self::SHEET_NAME . "!{$colLetter}{$rowNum}";

            $this->writeRange($spreadsheetId, $cellRange, [[$value]]);
            Log::info("GoogleSheetService::setChecklistValue - Berhasil memperbarui sel {$cellRange} menjadi \"{$value}\"");

        } catch (\Exception $e) {
            Log::error('GoogleSheetService::setChecklistValue gagal', [
                'folderId' => $folderId,
                'value'    => $value,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Mendapatkan nilai checklist (v atau '') dari Google Sheet untuk folder ID tertentu.
     */
    public function getChecklistValue(string $folderId): string
    {
        $spreadsheetId = $this->getSpreadsheetId();
        if (empty($spreadsheetId)) {
            return '';
        }

        try {
            $hierarchy = $this->driveService->resolveFolderHierarchy($folderId);
            if (! $hierarchy) {
                return '';
            }

            $monthNameRaw = $hierarchy['month'];
            $regionName   = $hierarchy['region'];
            $unitName     = $hierarchy['unit'];

            $standardMonth = $this->resolveStandardMonthName($monthNameRaw);
            if (! $standardMonth || ! isset(self::MONTH_MAPPING[$standardMonth])) {
                return '';
            }

            $colIdx = self::MONTH_MAPPING[$standardMonth]['col'];
            $range = self::SHEET_NAME . '!A1:P500';
            $rows  = $this->readRange($spreadsheetId, $range);

            $matchedRowIndex = null;
            $currentParent = '';

            foreach ($rows as $rowIndex => $rowData) {
                if ($rowIndex < 5) {
                    continue;
                }

                $colB = trim($rowData[1] ?? '');
                $colC = trim($rowData[2] ?? '');

                if (
                    str_contains(strtolower($colC), 'disusun oleh') ||
                    str_contains(strtolower($colC), 'mengetahui')
                ) {
                    break;
                }

                if ($colB !== '') {
                    $currentParent = $colB;
                }

                $cleanC = $this->cleanUnitName($colC);
                $cleanUnitName = $this->cleanUnitName($unitName);

                if (
                    strtolower(trim($currentParent)) === strtolower(trim($regionName)) &&
                    strtolower(trim($cleanC)) === strtolower(trim($cleanUnitName))
                ) {
                    $matchedRowIndex = $rowIndex;
                    break;
                }
            }

            if ($matchedRowIndex === null) {
                return '';
            }

            return trim($rows[$matchedRowIndex][$colIdx] ?? '');
        } catch (\Exception $e) {
            Log::error('GoogleSheetService::getChecklistValue gagal', [
                'folderId' => $folderId,
                'error'    => $e->getMessage(),
            ]);
            return '';
        }
    }

    private function cleanUnitName(string $name): string
    {
        return preg_replace('/^[a-z0-9]+\.\s*/i', '', trim($name));
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

    private function resolveStandardMonthName(string $folderName): ?string
    {
        $folderNameLower = strtolower($folderName);

        // Tahap 1: Pencocokan berdasarkan nama/singkatan teks (lebih akurat, prioritas tinggi)
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

        // Tahap 2: Pencocokan berdasarkan nomor bulan kalender sebagai fallback
        // Gunakan urutan dari yang paling spesifik (multi-digit) ke yang lebih umum
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

    /**
     * Memindai Google Drive dan menyinkronkan seluruh checklist di Google Sheet untuk Semester tertentu.
     *
     * @param  string  $semesterName
     * @param  string  $spreadsheetId  ID Google Sheet tujuan
     * @return void
     */
    public function syncSemester(string $semesterName, string $spreadsheetId = ''): void
    {
        $startTime = microtime(true);

        $spreadsheetId = $this->getSpreadsheetId($spreadsheetId);
        if (empty($spreadsheetId)) {
            Log::warning('GoogleSheetService::syncSemester - GOOGLE_SHEET_ID tidak terkonfigurasi.');
            return;
        }

        $settings = $this->apiService->getSettings();
        $rootId = $settings['root_folder_id'] ?? null;
        if (empty($rootId)) {
            Log::warning('GoogleSheetService::syncSemester - Root Folder belum dikonfigurasi.');
            return;
        }

        try {
            $client = new \Google\Service\Drive($this->apiService->getClient());

            // 1. Folder bulan berada LANGSUNG di bawah Root Folder
            $semesterFolderId = $rootId;

            // 2. Ambil semua subfolder di bawah semesterFolderId secara bertahap (tiga level)
            // Level 1: Month folders
            $months = [];
            $response = $client->files->listFiles([
                'q'        => "'{$semesterFolderId}' in parents and mimeType = 'application/vnd.google-apps.folder' and trashed = false",
                'fields'   => 'files(id, name)',
                'pageSize' => 100,
            ]);
            foreach ($response->getFiles() as $file) {
                $months[$file->getId()] = $file->getName();
            }

            if (empty($months)) {
                return;
            }

            // Level 2: Region folders
            $monthIds = array_keys($months);
            $regions = [];
            $parentQueries = array_map(fn($id) => "'{$id}' in parents", $monthIds);
            $q = "(" . implode(' or ', $parentQueries) . ") and mimeType = 'application/vnd.google-apps.folder' and trashed = false";
            $response = $client->files->listFiles([
                'q'        => $q,
                'fields'   => 'files(id, name, parents)',
                'pageSize' => 500,
            ]);
            foreach ($response->getFiles() as $file) {
                $parents = $file->getParents() ?? [];
                foreach ($parents as $parentId) {
                    if (isset($months[$parentId])) {
                        $regions[$file->getId()] = [
                            'name'    => $file->getName(),
                            'monthId' => $parentId,
                        ];
                    }
                }
            }

            if (empty($regions)) {
                return;
            }

            // Level 3: Unit folders
            $regionIds = array_keys($regions);
            $units = [];
            $regionChunks = array_chunk($regionIds, 100);
            foreach ($regionChunks as $chunk) {
                $parentQueries = array_map(fn($id) => "'{$id}' in parents", $chunk);
                $q = "(" . implode(' or ', $parentQueries) . ") and mimeType = 'application/vnd.google-apps.folder' and trashed = false";
                $response = $client->files->listFiles([
                    'q'        => $q,
                    'fields'   => 'files(id, name, parents)',
                    'pageSize' => 1000,
                ]);
                foreach ($response->getFiles() as $file) {
                    $parents = $file->getParents() ?? [];
                    foreach ($parents as $parentId) {
                        if (isset($regions[$parentId])) {
                            $units[$file->getId()] = [
                                'name'     => $file->getName(),
                                'regionId' => $parentId,
                            ];
                        }
                    }
                }
            }

            if (empty($units)) {
                return;
            }

            // Level 4: Get files count for each Unit folder
            $unitIds = array_keys($units);
            $fileCounts = [];
            $unitChunks = array_chunk($unitIds, 100);
            foreach ($unitChunks as $chunk) {
                $parentQueries = array_map(fn($id) => "'{$id}' in parents", $chunk);
                $q = "(" . implode(' or ', $parentQueries) . ") and mimeType != 'application/vnd.google-apps.folder' and trashed = false";
                
                $pageToken = null;
                do {
                    $response = $client->files->listFiles([
                        'q'         => $q,
                        'fields'    => 'nextPageToken, files(id, name, parents)',
                        'pageSize'  => 1000,
                        'pageToken' => $pageToken,
                    ]);

                    foreach ($response->getFiles() as $file) {
                        $parents = $file->getParents() ?? [];
                        foreach ($parents as $parentId) {
                            if (isset($units[$parentId])) {
                                $fileCounts[$parentId] = ($fileCounts[$parentId] ?? 0) + 1;
                            }
                        }
                    }
                    $pageToken = $response->getNextPageToken();
                } while ($pageToken);
            }

            // 3. Baca data Google Sheet
            $range = self::SHEET_NAME . '!A1:P500';
            $rows  = $this->readRange($spreadsheetId, $range);

            $updates = [];
            $currentParent = '';
            $activeMonths = self::SEMESTER_MONTHS[$semesterName] ?? [];

            foreach ($rows as $rowIndex => $rowData) {
                if ($rowIndex < 5) {
                    continue;
                }

                $colB = trim($rowData[1] ?? '');
                $colC = trim($rowData[2] ?? '');

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

                foreach ($activeMonths as $mName) {
                    $colIdx = self::MONTH_MAPPING[$mName]['col'];
                    $valInSheet = strtolower(trim($rowData[$colIdx] ?? ''));

                    // Temukan folder unit di map kita
                    $unitFolderId = null;
                    $cleanColC = $this->cleanUnitName($colC);
                    foreach ($units as $uId => $uData) {
                        $uName = $uData['name'];
                        $rId = $uData['regionId'];
                        $rData = $regions[$rId] ?? null;
                        if (!$rData) continue;
                        $rName = $rData['name'];
                        $mId = $rData['monthId'];
                        $mNameRaw = $months[$mId] ?? '';
                        
                        $standardMonth = $this->resolveStandardMonthName($mNameRaw);
                        
                        if (
                            $standardMonth === $mName &&
                            strtolower(trim($rName)) === strtolower(trim($currentParent)) &&
                            strtolower(trim($this->cleanUnitName($uName))) === strtolower(trim($cleanColC))
                        ) {
                            $unitFolderId = $uId;
                            break;
                        }
                    }

                    $fileCount = $unitFolderId ? ($fileCounts[$unitFolderId] ?? 0) : 0;
                    $isComplete = $fileCount >= 1;

                    $sheetIsComplete = in_array($valInSheet, ['v', '✓', 'true', '1'], true);

                    if ($sheetIsComplete && ! $isComplete) {
                        $colLetter = $this->getColumnLetter($colIdx);
                        $rowNum = $rowIndex + 1;
                        $updates[self::SHEET_NAME . "!{$colLetter}{$rowNum}"] = '';
                    }
                }
            }

            // 4. Batch update ke Google Sheet
            if (! empty($updates)) {
                $this->batchUpdate($spreadsheetId, $updates);
            }

            $duration = round(microtime(true) - $startTime, 2);
            Log::info("Google Drive Sync selesai untuk {$semesterName}.\n" . count($units) . " unit folder\n" . count($updates) . " sel diperbarui\n{$duration} detik");

        } catch (\Exception $e) {
            Log::error('GoogleSheetService::syncSemester gagal', [
                'semester' => $semesterName,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    private function getDescendantFolderIds(string $parentId, array $folderMap): array
    {
        $descendants = [];
        foreach ($folderMap as $folder) {
            if (in_array($parentId, $folder['parents'], true)) {
                $descendants[] = $folder['id'];
                $descendants = array_merge($descendants, $this->getDescendantFolderIds($folder['id'], $folderMap));
            }
        }
        return array_unique($descendants);
    }

    private function findUnitFolderIdInMap(
        string $semesterId,
        string $monthName,
        string $regionName,
        string $unitName,
        array $folderMap
    ): ?string {
        $monthFolderId = null;
        foreach ($folderMap as $folder) {
            if (in_array($semesterId, $folder['parents'], true)) {
                if ($this->matchMonthFolder($monthName, $folder['name'])) {
                    $monthFolderId = $folder['id'];
                    break;
                }
            }
        }
        if (! $monthFolderId) {
            return null;
        }

        $regionFolderId = null;
        $targetRegion = strtolower(trim($regionName));
        foreach ($folderMap as $folder) {
            if (in_array($monthFolderId, $folder['parents'], true)) {
                if (strtolower(trim($folder['name'])) === $targetRegion) {
                    $regionFolderId = $folder['id'];
                    break;
                }
            }
        }
        if (! $regionFolderId) {
            return null;
        }

        $cleanedUnitName = $this->cleanUnitName($unitName);
        $targetUnit = strtolower(trim($cleanedUnitName));
        foreach ($folderMap as $folder) {
            if (in_array($regionFolderId, $folder['parents'], true)) {
                $cleanFolder = $this->cleanUnitName($folder['name']);
                if (strtolower(trim($cleanFolder)) === $targetUnit) {
                    return $folder['id'];
                }
            }
        }

        return null;
    }

    private function matchMonthFolder(string $monthName, string $folderName): bool
    {
        $monthNameLower  = strtolower($monthName);
        $folderNameLower = strtolower($folderName);

        // Tahap 1: Pencocokan nama/singkatan teks (prioritas tinggi)
        $textPatterns = [
            'desember'  => ['desember', 'des'],
            'januari'   => ['januari', 'jan'],
            'februari'  => ['februari', 'feb'],
            'maret'     => ['maret', 'mar'],
            'april'     => ['april', 'apr'],
            'mei'       => ['mei'],
            'juni'      => ['juni', 'jun'],
            'juli'      => ['juli', 'jul'],
            'agustus'   => ['agustus', 'agu', 'agt'],
            'september' => ['september', 'sept', 'sep'],
            'oktober'   => ['oktober', 'okt'],
            'november'  => ['november', 'nov'],
        ];

        if (isset($textPatterns[$monthNameLower])) {
            foreach ($textPatterns[$monthNameLower] as $search) {
                if (str_contains($folderNameLower, $search)) {
                    return true;
                }
            }
        }

        // Tahap 2: Pencocokan nomor bulan kalender sebagai fallback
        $numericPatterns = [
            'januari'   => ['1.'],
            'februari'  => ['2.'],
            'maret'     => ['3.'],
            'april'     => ['4.'],
            'mei'       => ['5.'],
            'juni'      => ['6.'],
            'juli'      => ['7.'],
            'agustus'   => ['8.'],
            'september' => ['9.'],
            'oktober'   => ['10.'],
            'november'  => ['11.'],
            'desember'  => ['12.'],
        ];

        if (isset($numericPatterns[$monthNameLower])) {
            foreach ($numericPatterns[$monthNameLower] as $search) {
                if (str_contains($folderNameLower, $search)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Mendapatkan Spreadsheet ID secara dinamis.
     *
     * @param  string|null  $providedId
     * @return string
     */
    public function getSpreadsheetId(?string $providedId = null): string
    {
        if (! empty($providedId)) {
            return $providedId;
        }

        return $this->apiService->getSpreadsheetId();
    }
}
