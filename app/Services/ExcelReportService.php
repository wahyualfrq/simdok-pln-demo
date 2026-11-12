<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MonitoringStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * ExcelReportService
 *
 * EXCEL-FIRST ARCHITECTURE:
 * Master Template Excel (Monitoring_Template.xlsx) adalah SATU-SATUNYA sumber layout.
 * Database SQLite hanya menyuplai data monitoring status.
 * Both Preview (via PDF conversion) and Download PDF originate from the exact same temporary Excel file.
 */
class ExcelReportService
{
    private string $templatePath;
    private string $tempDir;
    private string $libreOfficePath;

    public function __construct()
    {
        ini_set('memory_limit', '512M');
        $this->templatePath    = config('report_template.template_path', resource_path('templates/Monitoring_Template.xlsx'));
        $this->tempDir         = config('report_template.temp_dir', storage_path('app/temp'));
        $this->libreOfficePath = config('pdf_export.libreoffice_path',
            PHP_OS_FAMILY === 'Windows'
                ? 'C:\Program Files\LibreOffice\program\soffice.exe'
                : '/usr/bin/soffice'
        );

        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }

    /**
     * Step 1: Copy Master Template -> Isi Seleluruh Cell Monitoring & Footer -> Save Excel -> Convert PDF (< 1.5 detik)
     */
    public function createReport(string $semester, array $footer): array
    {
        if (!file_exists($this->templatePath)) {
            throw new \RuntimeException("Master Template Excel tidak ditemukan di: {$this->templatePath}");
        }

        $uuid     = 'report_' . Str::uuid()->toString();
        $xlsxPath = $this->tempDir . DIRECTORY_SEPARATOR . "{$uuid}.xlsx";
        $pdfPath  = $this->tempDir . DIRECTORY_SEPARATOR . "{$uuid}.pdf";

        // 1. Copy Master Template (Read-Only)
        if (!copy($this->templatePath, $xlsxPath)) {
            throw new \RuntimeException("Gagal menyalin Master Template Excel.");
        }

        // 2. Isi seluruh cell monitoring & footer (PageSetup template tetap 100% utuh)
        $spreadsheet = IOFactory::load($xlsxPath);
        $sheet       = $spreadsheet->getActiveSheet();

        $this->fillMonitoringData($sheet, $semester);
        $this->fillFooterCells($sheet, $footer);
        $sheet->getPageSetup()->setScale(68);
        $sheet->getPageSetup()->setPrintArea('A1:J98');

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($xlsxPath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        // 3. Konversi ke PDF untuk Preview & Download
        $this->convertToPdf($xlsxPath, $pdfPath);

        Log::info("[ExcelReportService] Created report {$uuid} for {$semester}");

        return [
            'temp_id'   => $uuid,
            'xlsx_path' => $xlsxPath,
            'pdf_path'  => $pdfPath,
        ];
    }

    /**
     * Step 2: Edit Footer -> Buka existing temporary Excel -> Update HANYA 5 cell footer -> Save -> Convert PDF (< 800ms)
     */
    public function updateFooter(string $tempId, string $semester, array $footer): array
    {
        if (!$this->isValidTempId($tempId)) {
            return $this->createReport($semester, $footer);
        }

        $xlsxPath = $this->tempDir . DIRECTORY_SEPARATOR . "{$tempId}.xlsx";
        $pdfPath  = $this->tempDir . DIRECTORY_SEPARATOR . "{$tempId}.pdf";

        if (!file_exists($xlsxPath)) {
            return $this->createReport($semester, $footer);
        }

        // Open existing temporary Excel file
        $spreadsheet = IOFactory::load($xlsxPath);
        $sheet       = $spreadsheet->getActiveSheet();

        $this->fillFooterCells($sheet, $footer);
        $sheet->getPageSetup()->setScale(68);
        $sheet->getPageSetup()->setPrintArea('A1:J98');

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($xlsxPath);
        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        // Re-convert to PDF
        $this->convertToPdf($xlsxPath, $pdfPath);

        Log::info("[ExcelReportService] Updated footer for report {$tempId}");

        return [
            'temp_id'   => $tempId,
            'xlsx_path' => $xlsxPath,
            'pdf_path'  => $pdfPath,
        ];
    }

    /**
     * Dapatkan path ke file PDF temporary berdasarkan temp_id.
     */
    public function getPdfPath(string $tempId): ?string
    {
        if (!$this->isValidTempId($tempId)) {
            return null;
        }

        $pdfPath = $this->tempDir . DIRECTORY_SEPARATOR . "{$tempId}.pdf";
        return file_exists($pdfPath) ? $pdfPath : null;
    }

    /**
     * Hapus file temporary yang usianya > 30 menit.
     */
    public function cleanupTempFiles(): void
    {
        $threshold = time() - (30 * 60);
        foreach (glob($this->tempDir . DIRECTORY_SEPARATOR . 'report_*.{xlsx,pdf}', GLOB_BRACE) as $file) {
            if (filemtime($file) < $threshold) {
                @unlink($file);
            }
        }
    }

    // ─────────────────────────────────────────────────────────
    // INTERNAL HELPERS
    // ─────────────────────────────────────────────────────────

    private function isValidTempId(string $tempId): bool
    {
        return (bool) preg_match('/^report_[a-f0-9\-]{36}$/', $tempId);
    }

    private function fillMonitoringData($sheet, string $semester): void
    {
        $monthColsMap = config('report_template.month_columns', []);
        $monthColumns = $monthColsMap[$semester] ?? $monthColsMap['Semester 1'];
        $symbols      = config('report_template.status_symbols', [
            'complete'   => '✓',
            'mismatch'   => '!',
            'incomplete' => '-',
        ]);

        // 1. Fill Semester Title Header in Merged Cell E4
        $semTitle = (strcasecmp($semester, 'Semester 2') === 0) ? 'SEMESTER II' : 'SEMESTER I';
        $sheet->getCell('E4')->setValue($semTitle);

        // 2. Fill Month Name Headers in Row 5 (Columns E to J)
        $monthHeaderMap = [
            'Semester 1' => [
                'E' => 'DES',
                'F' => 'JAN',
                'G' => 'FEB',
                'H' => 'MAR',
                'I' => 'APR',
                'J' => 'MEI',
            ],
            'Semester 2' => [
                'E' => 'JUN',
                'F' => 'JUL',
                'G' => 'AGT',
                'H' => 'SEP',
                'I' => 'OKT',
                'J' => 'NOV',
            ],
        ];

        $headers = $monthHeaderMap[$semester] ?? $monthHeaderMap['Semester 1'];
        foreach ($headers as $col => $monthLabel) {
            $sheet->getCell($col . '5')->setValue($monthLabel);
        }

        // 3. Fill Monitoring Unit Status Data
        $statuses = MonitoringStatus::where('semester', $semester)->get();
        $statusMap = [];
        $unitToRegionMap = [];

        foreach ($statuses as $row) {
            $regNorm  = strtolower(trim(preg_replace('/[^a-z0-9]/i', '', $row->region)));
            $uName    = $row->unit;
            $uOrig    = $row->unit_original ?: $row->unit;
            $cleanU   = preg_replace('/^[a-z0-9]+\.\s*/i', '', trim($uName));
            $cleanOrig= preg_replace('/^[a-z0-9]+\.\s*/i', '', trim($uOrig));

            $st = strtolower($row->status);

            foreach ([$uName, $uOrig, $cleanU, $cleanOrig] as $variant) {
                $uNorm = strtolower(trim(preg_replace('/[^a-z0-9]/i', '', $variant)));
                if ($uNorm !== '') {
                    $statusMap[$regNorm][$uNorm][$row->bulan] = $st;
                    $unitToRegionMap[$uNorm][$row->bulan]     = $st;
                }
            }
        }

        $maxRow   = $sheet->getHighestRow();
        $rowStart = config('report_template.row_start', 6);
        $rowEnd   = config('report_template.row_end', 80);

        $currentRegion = '';

        for ($rowNum = $rowStart; $rowNum <= min($maxRow, $rowEnd); $rowNum++) {
            $colB = trim((string) $sheet->getCell('B' . $rowNum)->getValue());
            $colC = trim((string) $sheet->getCell('C' . $rowNum)->getValue());
            $cellText = $colC !== '' ? $colC : $colB;

            if ($cellText !== '' && (str_contains(strtoupper($cellText), 'PLN') || str_contains(strtoupper($cellText), 'UNIT INDUK'))) {
                $currentRegion = $cellText;
            }

            $colD = trim((string) $sheet->getCell('D' . $rowNum)->getValue());
            if ($colD === '') continue;

            $regNorm     = strtolower(trim(preg_replace('/[^a-z0-9]/i', '', $currentRegion)));
            $cleanD      = preg_replace('/^[a-z0-9]+\.\s*/i', '', $colD);
            $uNormD      = strtolower(trim(preg_replace('/[^a-z0-9]/i', '', $colD)));
            $uNormCleanD = strtolower(trim(preg_replace('/[^a-z0-9]/i', '', $cleanD)));

            $matchedStatuses = null;
            $regionMap = $statusMap[$regNorm] ?? [];

            // 1. Precise Region + Unit Match
            if (isset($regionMap[$uNormD])) {
                $matchedStatuses = $regionMap[$uNormD];
            } elseif (isset($regionMap[$uNormCleanD])) {
                $matchedStatuses = $regionMap[$uNormCleanD];
            } else {
                foreach ($regionMap as $k => $mMap) {
                    if (strlen($k) >= 3 && (str_contains($uNormCleanD, $k) || str_contains($k, $uNormCleanD))) {
                        $matchedStatuses = $mMap;
                        break;
                    }
                }
            }

            // 2. Global Unit Match (Fallback if region header offset in template)
            if ($matchedStatuses === null) {
                if (isset($unitToRegionMap[$uNormD])) {
                    $matchedStatuses = $unitToRegionMap[$uNormD];
                } elseif (isset($unitToRegionMap[$uNormCleanD])) {
                    $matchedStatuses = $unitToRegionMap[$uNormCleanD];
                } else {
                    foreach ($unitToRegionMap as $k => $mMap) {
                        if (strlen($k) >= 3 && (str_contains($uNormCleanD, $k) || str_contains($k, $uNormCleanD))) {
                            $matchedStatuses = $mMap;
                            break;
                        }
                    }
                }
            }

            $unitStatuses = $matchedStatuses ?? [];
            foreach ($monthColumns as $monthName => $col) {
                $st      = $unitStatuses[$monthName] ?? 'incomplete';
                $display = $symbols[strtolower($st)] ?? '-';
                $sheet->getCell($col . $rowNum)->setValue($display);
            }
        }
    }

    private function fillFooterCells($sheet, array $footer): void
    {
        $cells   = config('report_template.footer_cells', [
            'lokasi_tanggal'     => 'H82',
            'jabatan_penyusun'   => 'C84',
            'jabatan_mengetahui' => 'H84',
            'nama_penyusun'      => 'C90',
            'nama_mengetahui'    => 'H90',
        ]);

        $lokasi  = $footer['lokasi'] ?? 'Palembang';
        $tanggal = $footer['tanggal'] ?? now()->locale('id')->isoFormat('DD MMMM YYYY');

        $sheet->getCell($cells['lokasi_tanggal'])->setValue("{$lokasi}, {$tanggal}");
        $sheet->getCell($cells['jabatan_penyusun'])->setValue($footer['jabatan_penyusun'] ?? 'JABATAN PENYUSUN (DEMO)');
        $sheet->getCell($cells['jabatan_mengetahui'])->setValue($footer['jabatan'] ?? 'JABATAN PEJABAT (DEMO)');
        $sheet->getCell($cells['nama_penyusun'])->setValue($footer['disusun_oleh'] ?? 'Nama Penyusun (Demo)');
        $sheet->getCell($cells['nama_mengetahui'])->setValue($footer['mengetahui'] ?? 'Nama Pejabat (Demo)');
    }

    private function convertToPdf(string $xlsxPath, string $pdfPath): void
    {
        // DEMO: tanpa Excel/LibreOffice — konversi lewat PhpSpreadsheet+Dompdf. Kode asli di bawah tidak dihapus.
        if (config('demo.enabled')) {
            \App\Services\Demo\DemoPdfConverter::convert($xlsxPath, $pdfPath);
            return;
        }

        $xlsxPathWin = str_replace('/', '\\', $xlsxPath);
        $pdfPathWin  = str_replace('/', '\\', $pdfPath);
        $ps1Script   = storage_path('app/convert_excel.ps1');

        // 1. Primary: PowerShell Script + MS Excel Interop (pixel-perfect)
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
                    return;
                }
            } catch (\Throwable $e) {
                Log::warning('[ExcelReportService] PowerShell conversion failed: ' . $e->getMessage());
            }
        }

        // 2. Fallback: LibreOffice CLI
        if (file_exists($this->libreOfficePath)) {
            try {
                $outDir   = dirname($pdfPath);
                $xlsxFile = escapeshellarg($xlsxPath);
                $soffice  = escapeshellarg($this->libreOfficePath);
                $outDirQ  = escapeshellarg($outDir);

                $cmd      = "{$soffice} --headless --convert-to pdf --outdir {$outDirQ} {$xlsxFile} 2>&1";
                exec($cmd);

                $expected = $outDir . DIRECTORY_SEPARATOR . pathinfo($xlsxPath, PATHINFO_FILENAME) . '.pdf';
                if (file_exists($expected) && filesize($expected) > 0) {
                    if ($expected !== $pdfPath) {
                        rename($expected, $pdfPath);
                    }
                    return;
                }
            } catch (\Throwable $e) {
                Log::warning('[ExcelReportService] LibreOffice CLI failed: ' . $e->getMessage());
            }
        }

        throw new \RuntimeException('Gagal mengonversi Excel ke PDF. Pastikan Microsoft Excel atau LibreOffice terpasang pada server.');
    }
}
