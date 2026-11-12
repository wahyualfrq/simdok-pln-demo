<?php

declare(strict_types=1);

namespace App\Services\Demo;

use setasign\Fpdi\Fpdi;

/**
 * DemoPdfFooter
 *
 * DEMO: Menulis footer kustom (lokasi/tanggal, jabatan & nama penyusun/mengetahui) di HALAMAN TERAKHIR salinan
 * PDF statis demo memakai FPDI + FPDF (PHP murni: tanpa ext-gd/binary). Blok footer lama ditutup kotak putih,
 * lalu teks baru ditulis dengan Helvetica, rata tengah per blok seperti aslinya. Tabel dan "Notes" tidak
 * tersentuh: kotak penutup hanya mencakup area antara tabel dan Notes.
 *
 * Koordinat (pt, titik asal kiri-atas) diukur dari monitoring_semester_{1|2}.pdf dan disimpan per semester di
 * config('demo.pdf.footer_layout'). Teks UTF-8 dikonversi ke ISO-8859-1 (karakter di luar rentang menjadi '?'),
 * lalu dibungkus/dipotong agar tidak keluar dari kotaknya.
 */
class DemoPdfFooter
{
    /**
     * @param  string  $source    PDF statis
     * @param  string  $target    Tujuan (di storage path demo)
     * @param  int     $semester  1 atau 2
     * @param  array{lokasi: string, tanggal: string, disusun_oleh: string, jabatan_penyusun: string, mengetahui: string, jabatan: string}  $footer
     */
    public static function stamp(string $source, string $target, int $semester, array $footer): void
    {
        $layout = (array) config("demo.pdf.footer_layout.{$semester}");

        $pdf = new Fpdi('P', 'pt');
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);

        $pages = $pdf->setSourceFile($source);

        for ($page = 1; $page <= $pages; $page++) {
            $template = $pdf->importPage($page);
            $size     = $pdf->getTemplateSize($template);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($template);

            if ($page === $pages) {
                self::drawFooter($pdf, $layout, $footer);
            }
        }

        $pdf->Output('F', $target);
    }

    private static function drawFooter(Fpdi $pdf, array $layout, array $footer): void
    {
        $fontSize   = (float) $layout['font_size'];
        $lineHeight = (float) $layout['line_height'];
        $baseline   = (array) $layout['baseline'];
        $maxLines   = (array) $layout['max_lines'];

        // 1) Tutup blok footer lama (hanya area di antara tabel dan "Notes").
        $pdf->SetFillColor(255, 255, 255);
        foreach ((array) $layout['cover'] as $box) {
            $pdf->Rect((float) $box['x'], (float) $box['y'], (float) $box['w'], (float) $box['h'], 'F');
        }

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('Helvetica', '', $fontSize);

        $left  = (array) $layout['left'];
        $right = (array) $layout['right'];

        // 2) Blok kanan: "{Lokasi}, {Tanggal}", "Mengetahui,", jabatan, nama.
        self::centered($pdf, self::encode($footer['lokasi'] . ', ' . $footer['tanggal']), $right, (float) $baseline['lokasi_tanggal'], $fontSize, true);
        self::centered($pdf, 'Mengetahui,', $right, (float) $baseline['label'], $fontSize, true);
        self::block($pdf, $footer['jabatan'], $right, (float) $baseline['jabatan'], $lineHeight, (int) $maxLines['jabatan']);
        self::block($pdf, $footer['mengetahui'], $right, (float) $baseline['nama'], $lineHeight, (int) $maxLines['nama']);

        // 3) Blok kiri: "Disusun oleh,", jabatan penyusun, nama penyusun.
        self::centered($pdf, 'Disusun oleh,', $left, (float) $baseline['label'], $fontSize, true);
        self::block($pdf, $footer['jabatan_penyusun'], $left, (float) $baseline['jabatan'], $lineHeight, (int) $maxLines['jabatan']);
        self::block($pdf, $footer['disusun_oleh'], $left, (float) $baseline['nama'], $lineHeight, (int) $maxLines['nama']);
    }

    /**
     * Beberapa baris (dibungkus) rata tengah mulai dari baseline $y.
     *
     * @param  array{center: float, width: float}  $box
     */
    private static function block(Fpdi $pdf, string $text, array $box, float $y, float $lineHeight, int $maxLines): void
    {
        foreach (self::wrap($pdf, self::encode($text), (float) $box['width'], max(1, $maxLines)) as $i => $line) {
            self::centered($pdf, $line, $box, $y + $i * $lineHeight, 0.0, false);
        }
    }

    /**
     * Satu baris rata tengah pada baseline $y. Bila $shrink, ukuran font diperkecil (min 6 pt) sampai muat,
     * lalu dipotong dengan "...".
     *
     * @param  array{center: float, width: float}  $box
     */
    private static function centered(Fpdi $pdf, string $text, array $box, float $y, float $baseSize, bool $shrink): void
    {
        $width = (float) $box['width'];

        if ($shrink && $baseSize > 0) {
            $size = $baseSize;
            while ($size > 6.0 && self::measure($pdf, $text, $size) > $width) {
                $size -= 0.2;
            }
            $pdf->SetFont('Helvetica', '', $size);
            $text = self::truncate($pdf, $text, $width);
        }

        $x = (float) $box['center'] - $pdf->GetStringWidth($text) / 2;
        $pdf->Text($x, $y, $text);

        if ($shrink && $baseSize > 0) {
            $pdf->SetFont('Helvetica', '', $baseSize);
        }
    }

    private static function measure(Fpdi $pdf, string $text, float $size): float
    {
        $pdf->SetFont('Helvetica', '', $size);

        return $pdf->GetStringWidth($text);
    }

    private static function truncate(Fpdi $pdf, string $text, float $width): string
    {
        if ($pdf->GetStringWidth($text) <= $width) {
            return $text;
        }

        while ($text !== '' && $pdf->GetStringWidth($text . '...') > $width) {
            $text = substr($text, 0, -1);
        }

        return rtrim($text) . '...';
    }

    /**
     * Membungkus teks per kata (kata yang terlalu panjang dipecah per karakter); baris berlebih dipotong dengan "...".
     *
     * @return list<string>
     */
    private static function wrap(Fpdi $pdf, string $text, float $width, int $maxLines): array
    {
        $lines   = [];
        $current = '';

        foreach (preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            while ($pdf->GetStringWidth($word) > $width && strlen($word) > 1) {
                $cut = strlen($word) - 1;
                while ($cut > 1 && $pdf->GetStringWidth(substr($word, 0, $cut)) > $width) {
                    $cut--;
                }
                if ($current !== '') {
                    $lines[]  = $current;
                    $current  = '';
                }
                $lines[] = substr($word, 0, $cut);
                $word    = substr($word, $cut);
            }

            $try = $current === '' ? $word : $current . ' ' . $word;
            if ($pdf->GetStringWidth($try) <= $width) {
                $current = $try;
            } else {
                $lines[] = $current;
                $current = $word;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] = self::truncate($pdf, $lines[$maxLines - 1] . '...', $width);
        }

        return $lines;
    }

    /**
     * UTF-8 → ISO-8859-1 yang aman: UTF-8 rusak dibersihkan lebih dulu, karakter kontrol dibuang, dan karakter
     * di luar rentang menjadi '?' (tanpa error).
     */
    private static function encode(string $text): string
    {
        $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
        $text = (string) preg_replace('/[\x00-\x1F\x7F]+/', ' ', $text);

        return mb_convert_encoding($text, 'ISO-8859-1', 'UTF-8');
    }
}
