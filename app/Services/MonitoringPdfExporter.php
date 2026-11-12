<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MonitoringStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * MonitoringPdfExporter
 *
 * Mengisi Master Template Excel PLN S2JB dan mengonversi ke PDF final saat
 * tombol "Generate PDF" ditekan oleh pengguna.
 */
class MonitoringPdfExporter
{
    private string $templatePath;
    private string $tempDir;
    private string $libreOfficePath;

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
        $this->templatePath = resource_path('templates/Monitoring_Template.xlsx');
        $this->tempDir      = storage_path('app/temp');
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
     * Export PDF final dari Master Template Excel.
     *
     * @param  string  $semester  "Semester 1" atau "Semester 2"
     * @param  array   $footer    Footer data dari form user
     * @return string  Path ke file PDF temporary yang siap di-stream/download
     */
    public function export(string $semester, array $footer): string
    {
        if (!file_exists($this->templatePath)) {
            throw new \RuntimeException("Master Template Excel tidak ditemukan di {$this->templatePath}");
        }

        $uuid     = Str::uuid()->toString();
        $xlsxPath = $this->tempDir . DIRECTORY_SEPARATOR . "Export_{$uuid}.xlsx";
        $pdfPath  = $this->tempDir . DIRECTORY_SEPARATOR . "Export_{$uuid}.pdf";

        // 1. Copy Master Template (Read-Only) ke file temporary
        if (!copy($this->templatePath, $xlsxPath)) {
            throw new \RuntimeException('Gagal menyalin Master Template Excel.');
        }

        // 2. Isi data monitoring & footer
        $spreadsheet = IOFactory::load($xlsxPath);
        $sheet       = $spreadsheet->getActiveSheet();

        $this->fillMonitoringData($sheet, $semester);
        $this->fillFooterData($sheet, $footer);

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($xlsxPath);

        // 3. Konversi ke PDF via MS Excel / LibreOffice Interop
        $this->convertToPdf($xlsxPath, $pdfPath);

        // Hapus xlsx temporary
        if (file_exists($xlsxPath)) {
            @unlink($xlsxPath);
        }

        Log::info("[MonitoringPdfExporter] PDF final berhasil diexport: {$pdfPath}");

        return $pdfPath;
    }

    private function fillMonitoringData($sheet, string $semester): void
    {
        $monthColumns = self::MONTH_COLUMN_MAP[$semester] ?? self::MONTH_COLUMN_MAP['Semester 1'];
        $statuses     = MonitoringStatus::where('semester', $semester)->get();

        if ($statuses->isEmpty()) {
            return;
        }

        $unitStatusMap = [];
        foreach ($statuses as $row) {
            $unitName = $row->unit_original ?: $row->unit;
            $normKey  = strtolower(trim(preg_replace('/[^a-z0-9 ]/i', '', $unitName)));
            $unitStatusMap[$normKey][$row->bulan] = strtolower($row->status);
        }

        $maxRow = $sheet->getHighestRow();
        for ($rowNum = 6; $rowNum <= min($maxRow, 80); $rowNum++) {
            $unitCell = $sheet->getCell('D' . $rowNum)->getValue();
            if (empty($unitCell)) continue;

            $unitName  = trim((string) $unitCell);
            $normalKey = strtolower(trim(preg_replace('/[^a-z0-9 ]/i', '', $unitName)));

            // Find matching key in map
            $matchedKey = null;
            if (isset($unitStatusMap[$normalKey])) {
                $matchedKey = $normalKey;
            } else {
                foreach (array_keys($unitStatusMap) as $k) {
                    if (strlen($k) >= 4 && (str_contains($normalKey, $k) || str_contains($k, $normalKey))) {
                        $matchedKey = $k;
                        break;
                    }
                }
            }

            if ($matchedKey === null) continue;

            $unitStatuses = $unitStatusMap[$matchedKey];
            foreach ($monthColumns as $monthName => $col) {
                $st      = $unitStatuses[$monthName] ?? 'incomplete';
                $display = match (strtolower($st)) {
                    'complete'   => '✓',
                    'mismatch'   => '!',
                    'incomplete' => '-',
                    default      => '-',
                };
                $sheet->getCell($col . $rowNum)->setValue($display);
            }
        }
    }

    private function fillFooterData($sheet, array $footer): void
    {
        $lokasi  = $footer['lokasi'] ?? 'Palembang';
        $tanggal = $footer['tanggal'] ?? now()->locale('id')->isoFormat('DD MMMM YYYY');

        $sheet->getCell('H82')->setValue("{$lokasi}, {$tanggal}");
        $sheet->getCell('C84')->setValue($footer['jabatan_penyusun'] ?? 'JABATAN PENYUSUN (DEMO)');
        $sheet->getCell('H84')->setValue($footer['jabatan'] ?? 'JABATAN PEJABAT (DEMO)');
        $sheet->getCell('C90')->setValue($footer['disusun_oleh'] ?? 'Nama Penyusun (Demo)');
        $sheet->getCell('H90')->setValue($footer['mengetahui'] ?? 'Nama Pejabat (Demo)');
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

        // 1. Primary: PowerShell Script + MS Excel Interop
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
                Log::warning('[MonitoringPdfExporter] PowerShell conversion failed: ' . $e->getMessage());
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
                Log::warning('[MonitoringPdfExporter] LibreOffice CLI failed: ' . $e->getMessage());
            }
        }

        throw new \RuntimeException('Gagal memproses file PDF. Pastikan Microsoft Excel atau LibreOffice terpasang pada server.');
    }
}
