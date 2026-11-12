<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

/**
 * PdfExportService
 *
 * Menghasilkan PDF laporan monitoring dari Template Excel resmi PLN S2JB.
 *
 * ARSITEKTUR PERFORMA TINGGI (REDESIGN PERFORMA):
 *   1. Template Excel Master (Monitoring_Template.xlsx) tetap READ-ONLY (Single Source of Truth).
 *   2. Data Monitoring (432 cell + mapping unit) hanya diisi SATU KALI per semester.
 *      Hasilnya disimpan di Cache File: storage/app/temp/preview/filled_{slug}.xlsx.
 *   3. Saat user mengubah Footer (Lokasi, Tanggal, Disusun Oleh, Jabatan, Mengetahui):
 *      Backend TIDAK memproses ulang monitoring! Cukup copy filled_{slug}.xlsx, update 5 cell footer,
 *      lalu konversi ke PDF. (Durasi < 500ms!).
 *   4. Cache otomatis di-invalidate ketika data monitoring berubah atau sync dilakukan.
 */
class PdfExportService
{
    private string $libreOfficePath;
    private string $templatePath;
    private string $tempDir;
    private string $previewCacheDir;

    /**
     * Peta kolom bulan di template Excel (Sheet "UNIT").
     * Semester 1: Des, Jan, Feb, Mar, Apr, Mei
     * Semester 2: Jun, Jul, Agt, Sep, Okt, Nov
     */
    private const MONTH_COLUMN_MAP = [
        'Semester 1' => [
            'Desember'  => 'E',
            'Januari'   => 'F',
            'Februari'  => 'G',
            'Maret'     => 'H',
            'April'     => 'I',
            'Mei'       => 'J',
        ],
        'Semester 2' => [
            'Juni'      => 'E',
            'Juli'      => 'F',
            'Agustus'   => 'G',
            'September' => 'H',
            'Oktober'   => 'I',
            'November'  => 'J',
        ],
    ];

    public function __construct()
    {
        $this->libreOfficePath = config('pdf_export.libreoffice_path',
            PHP_OS_FAMILY === 'Windows'
                ? 'C:\Program Files\LibreOffice\program\soffice.exe'
                : '/usr/bin/soffice'
        );
        $this->templatePath    = resource_path('templates/Monitoring_Template.xlsx');
        $this->tempDir         = storage_path('app/temp');
        $this->previewCacheDir = storage_path('app/temp/preview');

        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }

        if (!is_dir($this->previewCacheDir)) {
            mkdir($this->previewCacheDir, 0755, true);
        }
    }

    /**
     * Dapatkan atau buat File Excel Temporary yang sudah terisi Data Monitoring.
     * Monitoring diproses SATU KALI per semester. Subsequent calls membaca dari cache file ini.
     *
     * @param  string  $semester  "Semester 1" atau "Semester 2"
     * @return string  Path ke filled Excel cache file
     */
    public function getOrBuildFilledTemplate(string $semester): string
    {
        $slug      = strtolower(str_replace(' ', '_', $semester));
        $cacheFile = $this->previewCacheDir . DIRECTORY_SEPARATOR . "filled_monitoring_{$slug}.xlsx";

        // Jika cache file sudah ada dan valid (> 1000 bytes), gunakan langsung
        if (file_exists($cacheFile) && filesize($cacheFile) > 1000) {
            Log::info("[PdfExportService] Menggunakan Filled Excel Cache untuk {$semester}: " . basename($cacheFile));
            return $cacheFile;
        }

        $this->ensureTemplateExists();
        $startTime = microtime(true);

        // 1. Copy template asli ke cache file
        if (!copy($this->templatePath, $cacheFile)) {
            throw new \RuntimeException('Gagal menyalin template Excel master.');
        }

        // 2. Isi data monitoring (432 cell) dari SQLite ke cache file — HANYA 1 KALI!
        $spreadsheet = IOFactory::load($cacheFile);
        $sheet       = $spreadsheet->getActiveSheet();

        $this->fillMonitoringData($sheet, $semester);

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($cacheFile);

        $elapsed = round((microtime(true) - $startTime) * 1000, 2);
        Log::info("[PdfExportService] Filled Excel Cache berhasil dibuat dalam {$elapsed} ms untuk {$semester}");

        return $cacheFile;
    }

    /**
     * Generate Preview PDF untuk Modal.
     * Menggunakan Cached Filled Template + meng-update 5 Cell Footer.
     *
     * @param  string  $semester   "Semester 1" atau "Semester 2"
     * @param  array   $footer     Footer data dari user
     * @return array{temp_id: string, pdf_path: string, xlsx_path: string}
     */
    public function generatePreview(string $semester, array $footer): array
    {
        // Step 1: Ambil path filled Excel cache (0ms jika sudah ada)
        $filledCachePath = $this->getOrBuildFilledTemplate($semester);

        $tempId   = Str::uuid()->toString();
        $xlsxPath = $this->tempDir . DIRECTORY_SEPARATOR . "Monitoring_{$tempId}.xlsx";
        $pdfPath  = $this->tempDir . DIRECTORY_SEPARATOR . "Monitoring_{$tempId}.pdf";

        // Step 2: Copy filled template ke file request temporary
        if (!copy($filledCachePath, $xlsxPath)) {
            throw new \RuntimeException('Gagal membuat salinan temporary Excel.');
        }

        // Step 3: Hanya update 5 cell footer! (TIDAK memproses ulang monitoring)
        $this->updateFooterOnly($xlsxPath, $footer);

        // Step 4: Konversi ke PDF
        $this->convertToPdf($xlsxPath, $pdfPath);

        Log::info("[PdfExportService] Preview PDF siap", ['temp_id' => $tempId]);

        return [
            'temp_id'   => $tempId,
            'pdf_path'  => $pdfPath,
            'xlsx_path' => $xlsxPath,
        ];
    }

    /**
     * Generate PDF final untuk download.
     */
    public function generateFinalPdf(string $semester, array $footer): string
    {
        $result   = $this->generatePreview($semester, $footer);
        $pdfPath  = $result['pdf_path'];
        $xlsxPath = $result['xlsx_path'];

        if (file_exists($xlsxPath)) {
            @unlink($xlsxPath);
        }

        return $pdfPath;
    }

    /**
     * Perbarui preview yang sudah ada dengan footer baru.
     * Hapus file request lama, generate baru dari cached filled template.
     */
    public function updatePreview(string $oldTempId, string $semester, array $footer): array
    {
        if ($oldTempId) {
            $this->cleanupById($oldTempId);
        }

        return $this->generatePreview($semester, $footer);
    }

    /**
     * Hapus seluruh Cache Filled Excel.
     * Dipanggil saat Sync Google Drive, Upload File, atau Monitoring Data Berubah.
     */
    public function clearFilledExcelCache(?string $semester = null): void
    {
        if ($semester) {
            $slug      = strtolower(str_replace(' ', '_', $semester));
            $cacheFile = $this->previewCacheDir . DIRECTORY_SEPARATOR . "filled_monitoring_{$slug}.xlsx";
            if (file_exists($cacheFile)) {
                @unlink($cacheFile);
                Log::info("[PdfExportService] Filled Excel cache dihapus untuk {$semester}");
            }
        } else {
            foreach (glob($this->previewCacheDir . DIRECTORY_SEPARATOR . 'filled_monitoring_*.xlsx') as $file) {
                @unlink($file);
            }
            Log::info('[PdfExportService] Semua Filled Excel cache dihapus.');
        }
    }

    /**
     * Ambil path file PDF temporary berdasarkan temp_id.
     */
    public function getPdfPath(string $tempId): ?string
    {
        if (!preg_match('/^[a-f0-9\-]{36}$/', $tempId)) {
            return null;
        }

        $path = $this->tempDir . DIRECTORY_SEPARATOR . "Monitoring_{$tempId}.pdf";
        return file_exists($path) ? $path : null;
    }

    /**
     * Hapus file temporary berdasarkan temp_id.
     */
    public function cleanupById(string $tempId): void
    {
        if (!preg_match('/^[a-f0-9\-]{36}$/', $tempId)) {
            return;
        }

        $xlsx = $this->tempDir . DIRECTORY_SEPARATOR . "Monitoring_{$tempId}.xlsx";
        $pdf  = $this->tempDir . DIRECTORY_SEPARATOR . "Monitoring_{$tempId}.pdf";

        if (file_exists($xlsx)) @unlink($xlsx);
        if (file_exists($pdf)) @unlink($pdf);
    }

    /**
     * Hapus semua file temporary yang lebih lama dari 30 menit.
     */
    public function cleanupOldTempFiles(): void
    {
        $threshold = time() - (30 * 60);

        foreach (glob($this->tempDir . DIRECTORY_SEPARATOR . 'Monitoring_*.{xlsx,pdf}', GLOB_BRACE) as $file) {
            if (filemtime($file) < $threshold) {
                @unlink($file);
                Log::info('[PdfExportService] Temp file dihapus: ' . basename($file));
            }
        }
    }

    // ─────────────────────────────────────────────────────────
    // INTERNAL: Fill & Update Excel
    // ─────────────────────────────────────────────────────────

    /**
     * Update HANYA 5 cell footer pada temporary Excel (< 50ms).
     */
    private function updateFooterOnly(string $xlsxPath, array $footer): void
    {
        $spreadsheet = IOFactory::load($xlsxPath);
        $sheet       = $spreadsheet->getActiveSheet();

        $this->fillFooter($sheet, $footer);

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($xlsxPath);
    }

    /**
     * Isi data monitoring ke worksheet (Hanya dipanggil 1 kali saat membuat cache).
     */
    private function fillMonitoringData($sheet, string $semester): void
    {
        $monthColumns = self::MONTH_COLUMN_MAP[$semester] ?? self::MONTH_COLUMN_MAP['Semester 1'];
        $statuses     = \App\Models\MonitoringStatus::where('semester', $semester)->get();

        if ($statuses->isEmpty()) {
            Log::warning('[PdfExportService] Tidak ada data monitoring untuk semester: ' . $semester);
            return;
        }

        $unitStatusMap = [];
        foreach ($statuses as $row) {
            $key = $this->normalizeUnitName($row->unit_original ?: $row->unit);
            $unitStatusMap[$key][$row->bulan] = strtolower($row->status);
        }

        $maxRow = $sheet->getHighestRow();

        for ($rowNum = 6; $rowNum <= min($maxRow, 80); $rowNum++) {
            $unitCell = $sheet->getCell('D' . $rowNum)->getValue();
            if (empty($unitCell)) continue;

            $unitName   = trim((string) $unitCell);
            $normalKey  = $this->normalizeUnitName($unitName);
            $matchedKey = $this->findBestMatch($normalKey, array_keys($unitStatusMap));

            if ($matchedKey === null) continue;

            $unitStatuses = $unitStatusMap[$matchedKey];

            foreach ($monthColumns as $monthName => $col) {
                $status  = $unitStatuses[$monthName] ?? 'incomplete';
                $display = match (strtolower($status)) {
                    'complete'   => '✓',
                    'mismatch'   => '!',
                    'incomplete' => '-',
                    default      => '-',
                };
                $sheet->getCell($col . $rowNum)->setValue($display);
            }
        }
    }

    /**
     * Isi footer ke worksheet.
     */
    private function fillFooter($sheet, array $footer): void
    {
        $lokasi  = $footer['lokasi'] ?? 'Palembang';
        $tanggal = $footer['tanggal'] ?? now()->locale('id')->isoFormat('DD MMMM YYYY');

        $sheet->getCell('H82')->setValue("{$lokasi}, {$tanggal}");
        $sheet->getCell('C84')->setValue($footer['jabatan_penyusun'] ?? 'JABATAN PENYUSUN (DEMO)');
        $sheet->getCell('H84')->setValue($footer['jabatan'] ?? 'JABATAN PEJABAT (DEMO)');
        $sheet->getCell('C90')->setValue($footer['disusun_oleh'] ?? 'Nama Penyusun (Demo)');
        $sheet->getCell('H90')->setValue($footer['mengetahui'] ?? 'Nama Pejabat (Demo)');
    }

    // ─────────────────────────────────────────────────────────
    // INTERNAL: PDF Conversion (Multi-Tier)
    // ─────────────────────────────────────────────────────────

    private function convertToPdf(string $xlsxPath, string $pdfPath): void
    {
        // DEMO: tanpa Excel/LibreOffice — konversi lewat PhpSpreadsheet+Dompdf. Kode asli di bawah tidak dihapus.
        if (config('demo.enabled')) {
            \App\Services\Demo\DemoPdfConverter::convert($xlsxPath, $pdfPath);
            return;
        }

        $outDir      = dirname($pdfPath);
        $xlsxPathWin = str_replace('/', '\\', $xlsxPath);
        $pdfPathWin  = str_replace('/', '\\', $pdfPath);
        $ps1Script   = storage_path('app/convert_excel.ps1');

        // 1. Primary Strategy: PowerShell Helper Script
        if (file_exists($ps1Script)) {
            try {
                $cmd = sprintf(
                    'powershell -NoProfile -ExecutionPolicy Bypass -File "%s" -InputPath "%s" -OutputPath "%s" 2>&1',
                    $ps1Script,
                    $xlsxPathWin,
                    $pdfPathWin
                );

                $output   = [];
                $exitCode = 0;
                exec($cmd, $output, $exitCode);

                if (file_exists($pdfPath) && filesize($pdfPath) > 0) {
                    Log::info('[PdfExportService] PDF berhasil dibuat menggunakan PowerShell MS Excel.');
                    return;
                }

                Log::warning('[PdfExportService] PowerShell script exit code: ' . $exitCode . '. Output: ' . implode("\n", $output));
            } catch (\Throwable $e) {
                Log::warning('[PdfExportService] PowerShell script error: ' . $e->getMessage());
            }
        }

        // 2. Direct MS Excel COM Extension
        if (class_exists('COM')) {
            try {
                $excel = new \COM('Excel.Application');
                if ($excel) {
                    $excel->Visible       = false;
                    $excel->DisplayAlerts = false;
                    $wb                   = $excel->Workbooks->Open($xlsxPathWin);
                    $wb->ExportAsFixedFormat(0, $pdfPathWin); // 0 = xlTypePDF
                    $wb->Close(false);
                    $excel->Quit();

                    if (file_exists($pdfPath) && filesize($pdfPath) > 0) {
                        Log::info('[PdfExportService] PDF berhasil dibuat menggunakan Direct MS Excel COM.');
                        return;
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('[PdfExportService] Direct MS Excel COM gagal: ' . $e->getMessage());
            }
        }

        // 3. Fallback ke LibreOffice CLI jika tersedia
        if (file_exists($this->libreOfficePath)) {
            try {
                $xlsxFile = escapeshellarg($xlsxPath);
                $soffice  = escapeshellarg($this->libreOfficePath);
                $outDirQ  = escapeshellarg($outDir);

                $cmd      = "{$soffice} --headless --convert-to pdf --outdir {$outDirQ} {$xlsxFile} 2>&1";
                $output   = [];
                $exitCode = 0;
                exec($cmd, $output, $exitCode);

                $expectedPdf = $outDir . DIRECTORY_SEPARATOR . pathinfo($xlsxPath, PATHINFO_FILENAME) . '.pdf';

                if (file_exists($expectedPdf) && filesize($expectedPdf) > 0) {
                    if ($expectedPdf !== $pdfPath) {
                        rename($expectedPdf, $pdfPath);
                    }
                    Log::info('[PdfExportService] PDF berhasil dibuat menggunakan LibreOffice.');
                    return;
                }
            } catch (\Throwable $e) {
                Log::warning('[PdfExportService] LibreOffice CLI gagal: ' . $e->getMessage());
            }
        }

        throw new \RuntimeException(
            'Gagal membuat preview PDF. Pastikan Microsoft Excel atau LibreOffice terpasang pada server.'
        );
    }

    // ─────────────────────────────────────────────────────────
    // INTERNAL: Helpers
    // ─────────────────────────────────────────────────────────

    private function ensureTemplateExists(): void
    {
        if (!file_exists($this->templatePath)) {
            throw new \RuntimeException(
                'Template Monitoring tidak ditemukan di: ' . $this->templatePath
            );
        }
    }

    private function normalizeUnitName(string $name): string
    {
        $s = strtolower(trim($name));
        $s = preg_replace('/^[a-z0-9]+\.\s*/i', '', $s);
        $s = preg_replace('/[^a-z0-9 ]/i', '', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }

    private function findBestMatch(string $target, array $keys): ?string
    {
        if (in_array($target, $keys, true)) {
            return $target;
        }

        foreach ($keys as $key) {
            if (strlen($key) >= 4 && strlen($target) >= 4) {
                if (str_contains($target, $key) || str_contains($key, $target)) {
                    return $key;
                }
            }
        }

        return null;
    }
}
