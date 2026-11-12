<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Services\FooterSettingsService;
use Dompdf\Adapter\CPDF;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\Log;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Drawing as SharedDrawing;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Dompdf as DompdfWriter;
use RuntimeException;

/**
 * DemoPdfConverter
 *
 * DEMO: Konversi XLSX → PDF murni PHP (PhpSpreadsheet + Dompdf) sebagai pengganti
 * Microsoft Excel/PowerShell dan LibreOffice yang tidak tersedia di Vercel.
 * Dipanggil dari awal convertToPdf() di ExcelReportService, PdfExportService dan
 * MonitoringPdfExporter hanya saat config('demo.enabled'). Kode exec()/soffice asli
 * tetap ada (tidak dihapus) dan tidak dijalankan pada mode demo.
 *
 * Memuat XLSX hasil isian template (termasuk footer penandatangan dummy) dan menulis PDF
 * ke path yang diminta. Orientasi, ukuran kertas, dan area cetak mengikuti pageSetup
 * template; skala dihitung DINAMIS (fit-to-width) agar lebar tabel ≈ lebar area cetak
 * (parameter di config('demo.pdf')). Semua berkas sementara (font cache, temp dompdf)
 * berada di storage_path('app/temp/dompdf') — tidak ke vendor/ atau repo.
 */
class DemoPdfConverter
{
    /**
     * Konversi PDF pada mode demo = PDF STATIS. Yang disimulasikan hanya jeda (animasi
     * "Mengonversi ke PDF…"); isinya adalah PDF contoh yang sudah disiapkan dari data dummy
     * (resources/demo/pdf/monitoring_semester_{1|2}.pdf), sehingga cepat dan stabil di Vercel.
     * Dompdf/PhpSpreadsheet writer TIDAK dimuat pada alur ini; semester dibaca murah dari
     * sharedStrings.xml lewat ZipArchive. Konversi sungguhan tersedia di renderWithDompdf().
     *
     * @param  string  $xlsxPath  Berkas XLSX hasil isian template
     * @param  string  $pdfPath   Tujuan PDF
     *
     * @throws RuntimeException bila PDF statis tidak ditemukan / tidak dapat disalin
     */
    public static function convert(string $xlsxPath, string $pdfPath): void
    {
        $semester = self::detectSemester($xlsxPath);
        $source   = base_path('resources/demo/pdf/monitoring_semester_' . $semester . '.pdf');

        if (! is_file($source)) {
            throw new RuntimeException('PDF contoh demo tidak ditemukan.');
        }

        // Footer = default panel → PDF statis apa adanya. Footer diubah → salinan PDF statis diberi footer baru
        // (FPDI, PHP murni) di halaman terakhir; bila gagal, jatuh ke PDF statis.
        $footer  = self::customFooter();
        $stamped = false;

        if ($footer !== null) {
            try {
                DemoPdfFooter::stamp($source, $pdfPath, $semester, $footer);
                $stamped = is_file($pdfPath) && filesize($pdfPath) > 0;
            } catch (\Throwable $e) {
                Log::warning('DemoPdfConverter: footer kustom gagal, memakai PDF statis: ' . $e->getMessage());
            }
        }

        if (! $stamped && ! @copy($source, $pdfPath)) {
            throw new RuntimeException('PDF contoh demo tidak dapat disalin.');
        }

        // Ekspor Excel memakai alur laporan yang sama (createReport selalu memanggil convertToPdf) tetapi
        // tidak menampilkan animasi PDF, jadi tanpa jeda agar tetap cepat.
        if (! request()->routeIs('monitoring.excel.export')) {
            usleep((int) config('demo.pdf.fake_delay_ms', 1800) * 1000);
        }
    }

    /**
     * Footer dari request (dinormalisasi persis seperti controller/panel), atau null bila sama dengan
     * footer default panel (normalize([])), tidak ada footer pada request, atau ini ekspor Excel.
     *
     * @return array<string, string>|null
     */
    private static function customFooter(): ?array
    {
        $request = request();

        if (! $request->has('footer') || $request->routeIs('monitoring.excel.export')) {
            return null;
        }

        $service = app(FooterSettingsService::class);
        $footer  = $service->normalize((array) $request->input('footer', []));

        return $footer === $service->normalize([]) ? null : $footer;
    }

    /**
     * Semester (1/2) dari judul "SEMESTER I"/"SEMESTER II" yang ditulis laporan ke sharedStrings.xml.
     * Tanpa memuat PhpSpreadsheet; bila penanda tidak ditemukan, dianggap Semester 1.
     */
    private static function detectSemester(string $xlsxPath): int
    {
        $zip = new \ZipArchive();
        if ($zip->open($xlsxPath) !== true) {
            return 1;
        }
        $strings = (string) $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();

        return preg_match('/SEMESTER\s+II\b/', $strings) === 1 ? 2 : 1;
    }

    /**
     * Konversi XLSX → PDF sungguhan (PhpSpreadsheet + Dompdf, fit-to-width). TIDAK dipanggil pada
     * runtime demo (lihat convert()); dipertahankan untuk membuat ulang PDF statis di
     * resources/demo/pdf/ bila template/data dummy berubah.
     *
     * @throws RuntimeException bila konversi gagal
     */
    public static function renderWithDompdf(string $xlsxPath, string $pdfPath): void
    {
        $workDir = storage_path('app/temp/dompdf');
        if (! is_dir($workDir) && ! @mkdir($workDir, 0775, true) && ! is_dir($workDir)) {
            throw new RuntimeException('Direktori sementara PDF demo tidak dapat dibuat.');
        }

        $spreadsheet = IOFactory::load($xlsxPath);

        // Dompdf menyematkan gambar PNG lewat ekstensi GD. Bila GD tidak ada pada runtime,
        // logo dibuang agar PDF tetap berhasil dibuat (tabel & footer tetap utuh).
        if (! function_exists('imagecreatefrompng')) {
            foreach ($spreadsheet->getAllSheets() as $sheet) {
                foreach (iterator_to_array($sheet->getDrawingCollection()) as $drawing) {
                    $drawing->setWorksheet(null, true);
                }
            }
        }

        $tableWidth = self::applyPageScale($spreadsheet);
        self::useSymbolFontForStatusCells($spreadsheet);

        $writer = new class($spreadsheet) extends DompdfWriter {
            /** Direktori kerja dompdf (font cache, temp) — selalu di bawah storage_path(). */
            public string $workDir = '';

            /** Lebar tabel target (pt) hasil fit-to-width; 0 = tanpa penguncian lebar. */
            public float $tableWidth = 0.0;

            /** Margin halaman [atas/bawah, kiri/kanan] dalam pt (dari config demo.pdf). */
            public array $pageMargin = [0.0, 0.0];

            /**
             * Baris tabel tidak boleh terbelah di batas halaman; header tabel (thead) diulang di tiap halaman.
             * Dompdf mengabaikan margin pada @page bernama (page0) dan memakai margin bawaannya (1,2 cm), serta
             * menambah padding td 1px; keduanya dinolkan/ditetapkan eksplisit agar posisi & lebar tabel di PDF
             * sama dengan hitungan fit-to-width. Tabel diletakkan di tengah area cetak.
             */
            public function generateHTMLAll(): string
            {
                $css = 'tr { page-break-inside: avoid; } thead { display: table-header-group; }'
                    . ' td, th { padding: 0 !important; } body, body > div { margin: 0 !important; }';
                if ($this->tableWidth > 0) {
                    $css .= sprintf(
                        ' @page { margin: %.2fpt %.2fpt; } table.sheet0 { margin: 0 auto !important; width: %.2fpt; }',
                        $this->pageMargin[0],
                        $this->pageMargin[1],
                        $this->tableWidth
                    );
                }

                $html = str_replace('</head>', '<style>' . $css . '</style></head>', parent::generateHTMLAll());

                $rowStart = (int) config('report_template.row_start', 6);

                return $this->repeatHeader($html, $rowStart - 2, $rowStart - 1);
            }

            /**
             * Writer HTML hanya membuat <thead> bila baris pengulangan dimulai dari baris 1 (judul laporan
             * ikut berulang). Di sini hanya header kolom (baris $firstRow..$lastRow, 1-based) yang diulang:
             * tabel dipecah tepat sebelum header, lalu header dibungkus <thead> pada tabel kedua (kolom
             * & kelas sama) — dompdf mengulang <thead> di setiap halaman. Bila pola HTML tidak ditemukan,
             * HTML dikembalikan apa adanya.
             */
            private function repeatHeader(string $html, int $firstRow, int $lastRow): string
            {
                $startTag = '<tr class="row' . ($firstRow - 1) . '">';
                $endTag   = '<tr class="row' . $lastRow . '">';
                $p1       = strpos($html, $startTag);
                $p2       = strpos($html, $endTag);
                $table    = strpos($html, '<table');

                if ($firstRow < 1 || $p1 === false || $p2 === false || $p2 <= $p1 || $table === false) {
                    return $html;
                }

                $tableEnd = strpos($html, '>', $table) + 1;
                $tbody    = strpos($html, '<tbody>', $tableEnd);
                if ($tbody === false || $tbody > $p1) {
                    return $html;
                }

                return substr($html, 0, $p1)
                    . '</tbody></table>'
                    . substr($html, $table, $tableEnd - $table)
                    . substr($html, $tableEnd, $tbody - $tableEnd)
                    . '<thead>' . substr($html, $p1, $p2 - $p1) . '</thead><tbody>'
                    . substr($html, $p2);
            }

            protected function createExternalWriterInstance(): Dompdf
            {
                $options = new Options();
                $options->set('fontDir', $this->workDir);
                $options->set('fontCache', $this->workDir);
                $options->set('tempDir', $this->workDir);
                $options->set('chroot', [$this->workDir, storage_path('app/temp')]);
                $options->set('isRemoteEnabled', false);
                $options->set('defaultFont', 'Helvetica'); // metrik setara Arial (font template) → baris tidak melebar

                return new Dompdf($options);
            }
        };

        $writer->workDir    = $workDir;
        $writer->tableWidth = $tableWidth;
        $writer->pageMargin = [
            (float) config('demo.pdf.margin_top_bottom_inch', 0.35) * 72,
            (float) config('demo.pdf.margin_left_right_inch', 0.3) * 72,
        ];
        $writer->save($pdfPath);

        $spreadsheet->disconnectWorksheets();

        if (! is_file($pdfPath) || filesize($pdfPath) === 0) {
            throw new RuntimeException('Konversi PDF demo gagal menghasilkan berkas.');
        }
    }

    /**
     * Fit-to-width dinamis. Dompdf tidak mengenal skala cetak Excel (pageSetup scale), sehingga
     * seluruh dimensi diskalakan langsung pada spreadsheet di memori (lebar kolom, tinggi baris,
     * ukuran font, ukuran logo) — berkas XLSX asli tidak berubah.
     *
     * Faktor skala k = (lebar area cetak × config('demo.pdf.fit_target')) / lebar alami tabel,
     * dengan lebar alami dihitung PERSIS seperti writer HTML (piksel → pt). Karena writer juga
     * menurunkan lebar kolom dari ukuran font bawaan, lebar kolom tidak boleh sekadar dikali k
     * (akan menyusut dua kali, k²): lebar target dalam piksel dikonversi balik ke satuan kolom
     * Excel memakai font bawaan yang sudah diskalakan.
     *
     * @return float Lebar tabel target (pt) = jumlah lebar kolom area cetak; 0 bila tidak dapat dihitung
     */
    private static function applyPageScale(Spreadsheet $spreadsheet): float
    {
        $cfg   = (array) config('demo.pdf', []);
        $sheet = $spreadsheet->getActiveSheet();
        $setup = $sheet->getPageSetup();

        $marginSide   = (float) ($cfg['margin_left_right_inch'] ?? 0.3);
        $marginTopBot = (float) ($cfg['margin_top_bottom_inch'] ?? 0.35);
        $fitTarget    = (float) ($cfg['fit_target'] ?? 0.98);
        $fontTrim     = (float) ($cfg['font_trim'] ?? 0.93);
        $rowTrim      = (float) ($cfg['row_height_trim'] ?? 0.93);

        $margins = $sheet->getPageMargins();
        $margins->setLeft($marginSide);
        $margins->setRight($marginSide);
        $margins->setTop($marginTopBot);
        $margins->setBottom($marginTopBot);

        // Kolom area cetak (mis. "A1:J98" → 1..10); bila tidak ada, kolom berisi data.
        $firstCol = 1;
        $lastCol  = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
        $lastRow  = $sheet->getHighestDataRow();
        $printArea = (string) $setup->getPrintArea();
        if ($printArea !== '' && ! str_contains($printArea, ',')) {
            [$start, $end] = Coordinate::rangeBoundaries($printArea);
            $firstCol = (int) $start[0];
            $lastCol  = (int) $end[0];
            $lastRow  = (int) $end[1];
        }

        // Kolom pertama yang kosong di seluruh area cetak (kolom pemisah/margin pada template) dipadatkan
        // menjadi ±1 px: kalau tidak, ia memakan lebar halaman dan isi tabel tampak bergeser dari tengah.
        $collapsed = [];
        $isEmpty   = true;
        for ($row = 1; $row <= $lastRow && $isEmpty; $row++) {
            $isEmpty = trim((string) $sheet->getCell([$firstCol, $row])->getValue()) === '';
        }
        if ($isEmpty && $lastCol > $firstCol) {
            $collapsed[$firstCol] = true;
        }

        // 1) Lebar alami tabel (px) memakai font bawaan SEBELUM diskalakan — sama dengan writer HTML.
        $defaultFont     = $spreadsheet->getDefaultStyle()->getFont();
        $defaultColWidth = $sheet->getDefaultColumnDimension()->getWidth();
        $naturalPx       = [];
        for ($c = $firstCol; $c <= $lastCol; $c++) {
            $dimension = $sheet->getColumnDimensionByColumn($c);
            if (! $dimension->getVisible() || isset($collapsed[$c])) {
                continue;
            }
            $width = $dimension->getWidth() > 0 ? $dimension->getWidth() : $defaultColWidth;
            $naturalPx[$c] = SharedDrawing::cellDimensionToPixels((float) $width, $defaultFont);
        }

        $naturalPt = SharedDrawing::pixelsToPoints((int) array_sum($naturalPx));
        $printable = self::pageWidthPoints($setup) - 2 * $marginSide * 72;
        if ($naturalPt <= 0 || $printable <= 0) {
            return 0.0;
        }

        $k = $fitTarget * $printable / $naturalPt;

        // 2) Ukuran font (termasuk font bawaan, dasar konversi lebar kolom).
        $seenFonts = [];
        foreach ($spreadsheet->getCellXfCollection() as $style) {
            $font = $style->getFont();
            if (isset($seenFonts[spl_object_id($font)])) {
                continue; // objek Font bisa dipakai bersama beberapa xf
            }
            $seenFonts[spl_object_id($font)] = true;
            $font->setSize(max(3.0, round((float) $font->getSize() * $k * $fontTrim, 1)));
        }
        $defaultFont = $spreadsheet->getDefaultStyle()->getFont(); // ukuran sudah diskalakan

        // 3) Lebar kolom: target px = alami × k, dikonversi balik dengan font bawaan yang baru.
        $columnPoints = [];
        foreach ($naturalPx as $c => $px) {
            $cellWidth = SharedDrawing::pixelsToCellDimension((int) max(1, round($px * $k)), $defaultFont);
            $sheet->getColumnDimensionByColumn($c)->setWidth((float) $cellWidth);
            $columnPoints[$c] = SharedDrawing::pixelsToPoints(SharedDrawing::cellDimensionToPixels((float) $cellWidth, $defaultFont));
        }
        foreach (array_keys($collapsed) as $c) {
            $cellWidth = SharedDrawing::pixelsToCellDimension(1, $defaultFont);
            $sheet->getColumnDimensionByColumn($c)->setWidth((float) $cellWidth);
            $columnPoints[$c] = SharedDrawing::pixelsToPoints(SharedDrawing::cellDimensionToPixels((float) $cellWidth, $defaultFont));
        }
        ksort($columnPoints);

        // 4) Tinggi baris. rowTrim: metrik font Helvetica/dompdf membuat sebagian baris bungkus lebih
        //    tinggi daripada Excel, sehingga tinggi dasar dirapatkan sedikit.
        $rowK = $k * $rowTrim;

        $defaultRow = $sheet->getDefaultRowDimension();
        if ($defaultRow->getRowHeight() > 0) {
            $defaultRow->setRowHeight($defaultRow->getRowHeight() * $rowK);
        }
        foreach ($sheet->getRowDimensions() as $dimension) {
            if ($dimension->getRowHeight() > 0) {
                $dimension->setRowHeight($dimension->getRowHeight() * $rowK);
            }
        }

        // 5) Logo.
        foreach ($sheet->getDrawingCollection() as $drawing) {
            $drawing->setResizeProportional(false);
            $drawing->setWidth((int) round($drawing->getWidth() * $k));
            $drawing->setHeight((int) round($drawing->getHeight() * $k));
        }

        self::clampMergesToPrintArea($sheet, $firstCol, $lastCol);
        self::splitVerticalMerges($sheet);
        self::mergeOverflowingFooterText($sheet, $columnPoints, $lastCol);

        return (float) array_sum($columnPoints);
    }

    /**
     * Sel gabungan yang melewati kolom terakhir area cetak (mis. catatan C94:O94) dipotong sampai kolom
     * terakhir. Bila tidak, tabel HTML memiliki kolom tambahan di luar area cetak dan dompdf tidak
     * menggambar garis tepi kanan kolom terakhir (tetangga kanan sel tabel tidak ada).
     */
    private static function clampMergesToPrintArea(Worksheet $sheet, int $firstCol, int $lastCol): void
    {
        foreach (array_values($sheet->getMergeCells()) as $range) {
            [[$col1, $row1], [$col2, $row2]] = Coordinate::rangeBoundaries($range);
            if ((int) $col2 <= $lastCol || (int) $col1 < $firstCol || (int) $col1 > $lastCol) {
                continue;
            }

            $sheet->unmergeCells($range);
            if ((int) $col1 < $lastCol || (int) $row2 > (int) $row1) {
                $sheet->mergeCells([(int) $col1, (int) $row1, $lastCol, (int) $row2]);
            }
        }
    }

    /**
     * Sel gabungan vertikal (rowspan) pada area data — nama region & nomor — dipecah per baris. Dompdf
     * merusak tata letak rowspan yang melewati batas halaman (sel-sel melorot ke kolom yang salah).
     * Teks diletakkan pada baris tengah grup; garis horizontal di dalam grup dihapus, sehingga tampilan
     * tetap seperti sel gabungan, tetapi baris dapat dipisah antarhalaman dengan rapi.
     */
    private static function splitVerticalMerges(Worksheet $sheet): void
    {
        $rowStart = (int) config('report_template.row_start', 6);
        $rowEnd   = (int) config('report_template.row_end', 80);

        foreach (array_values($sheet->getMergeCells()) as $range) {
            [[$col1, $row1], [$col2, $row2]] = Coordinate::rangeBoundaries($range);
            $col1 = (int) $col1;
            $col2 = (int) $col2;
            $row1 = (int) $row1;
            $row2 = (int) $row2;

            if ($row2 <= $row1 || $row1 < $rowStart || $row2 > $rowEnd) {
                continue;
            }

            $topLeft = Coordinate::stringFromColumnIndex($col1) . $row1;
            $value   = $sheet->getCell($topLeft)->getValue();

            $sheet->unmergeCells($range);
            $sheet->duplicateStyle($sheet->getStyle($topLeft), $range);

            for ($row = $row1; $row <= $row2; $row++) {
                for ($col = $col1; $col <= $col2; $col++) {
                    $borders = $sheet->getStyle([$col, $row])->getBorders();
                    if ($row > $row1) {
                        $borders->getTop()->setBorderStyle(Border::BORDER_NONE);
                    }
                    if ($row < $row2) {
                        $borders->getBottom()->setBorderStyle(Border::BORDER_NONE);
                    }
                }
            }

            $sheet->getCell($topLeft)->setValueExplicit('', DataType::TYPE_STRING);
            $sheet->getCell([$col1, $row1 + intdiv($row2 - $row1, 2)])->setValue($value);
        }
    }

    /**
     * Lebar halaman (pt) menurut ukuran kertas & orientasi pageSetup (A4 portrait pada template).
     */
    private static function pageWidthPoints(PageSetup $setup): float
    {
        $paper = match ($setup->getPaperSize()) {
            PageSetup::PAPERSIZE_A3     => 'a3',
            PageSetup::PAPERSIZE_A5     => 'a5',
            PageSetup::PAPERSIZE_LETTER => 'letter',
            PageSetup::PAPERSIZE_LEGAL  => 'legal',
            default                     => 'a4',
        };
        $size   = CPDF::$PAPER_SIZES[$paper];
        $width  = (float) ($size[2] - $size[0]);
        $height = (float) ($size[3] - $size[1]);

        return $setup->getOrientation() === PageSetup::ORIENTATION_LANDSCAPE ? max($width, $height) : min($width, $height);
    }

    /**
     * Helvetica (font inti PDF) tidak memiliki glyph simbol status (✓ / !). Hanya sel-sel simbol
     * itu yang diberi DejaVu Sans (bawaan dompdf); seluruh teks lain tetap Helvetica.
     */
    private static function useSymbolFontForStatusCells(Spreadsheet $spreadsheet): void
    {
        $sheet   = $spreadsheet->getActiveSheet();
        $symbols = array_values(array_filter(
            (array) config('report_template.status_symbols', []),
            fn ($symbol) => is_string($symbol) && ! preg_match('/^[\x20-\x7E]+$/', $symbol)
        ));

        if ($symbols === []) {
            return;
        }

        foreach ($sheet->getCoordinates() as $coordinate) {
            if (in_array((string) $sheet->getCell($coordinate)->getValue(), $symbols, true)) {
                $sheet->getStyle($coordinate)->getFont()->setName('DejaVu Sans');
            }
        }
    }

    /**
     * Excel membiarkan teks `nowrap` meluap ke sel kosong di sebelahnya; tabel HTML tidak, dan
     * dompdf akan membungkusnya di kolom sempit. Untuk area di bawah tabel data (footer
     * penandatangan & catatan), teks yang lebih lebar dari kolomnya digabung ke kanan melewati
     * sel kosong sampai muat — mengikuti tampilan Excel.
     *
     * @param  array<int, float>  $columnPoints  Lebar kolom area cetak dalam pt (indeks kolom 1-based)
     */
    private static function mergeOverflowingFooterText(Worksheet $sheet, array $columnPoints, int $lastCol): void
    {
        $firstRow = (int) config('report_template.row_end', 80) + 1;
        $lastRow  = $sheet->getHighestRow();

        for ($row = $firstRow; $row <= $lastRow; $row++) {
            for ($col = 1; $col <= $lastCol; $col++) {
                $cell  = $sheet->getCell([$col, $row]);
                $value = trim((string) $cell->getValue());

                if ($value === '' || $cell->isInMergeRange() || $sheet->getStyle([$col, $row])->getAlignment()->getWrapText()) {
                    continue;
                }

                $textPoints = mb_strlen($value) * (float) $sheet->getStyle([$col, $row])->getFont()->getSize() * 0.62;
                $end        = $col;
                $available  = $columnPoints[$col] ?? 0.0;

                while ($textPoints > $available && $end < $lastCol) {
                    $next = $sheet->getCell([$end + 1, $row]);
                    if (trim((string) $next->getValue()) !== '' || $next->isInMergeRange()) {
                        break;
                    }
                    $end++;
                    $available += $columnPoints[$end] ?? 0.0;
                }

                if ($end > $col) {
                    $sheet->mergeCells([$col, $row, $end, $row]);
                    $col = $end;
                }
            }
        }
    }
}
