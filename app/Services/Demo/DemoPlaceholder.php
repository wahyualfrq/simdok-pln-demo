<?php

declare(strict_types=1);

namespace App\Services\Demo;

/**
 * DemoPlaceholder
 *
 * DEMO: Pembuat berkas contoh kecil secara on-the-fly (tanpa menyimpan apa pun,
 * tanpa dependency baru) untuk unduhan dan pratinjau file demo.
 *
 * - PDF  : satu halaman berisi teks "File contoh — Demo SIMDOK".
 * - Gambar: PNG gradien biru kecil.
 * - Lainnya: teks biasa.
 */
class DemoPlaceholder
{
    /** Kalimat penanda pada semua berkas contoh. */
    public const TEXT = 'File contoh — Demo SIMDOK';

    /**
     * Isi berkas contoh sesuai ekstensi nama berkas.
     *
     * @return array{0: string, 1: string} [konten, Content-Type]
     */
    public function forFile(string $fileName): array
    {
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        return match (true) {
            $ext === 'pdf'                                   => [$this->pdf($fileName), 'application/pdf'],
            in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true) => [$this->png(), 'image/png'],
            default                                          => [$this->text($fileName), 'text/plain; charset=UTF-8'],
        };
    }

    public function text(string $fileName): string
    {
        return self::TEXT . "\n"
            . 'Nama berkas : ' . $fileName . "\n"
            . "Berkas ini hanyalah contoh pada versi demo SIMDOK.\n"
            . "Isi dokumen asli tidak disimpan maupun diambil dari Google Drive.\n";
    }

    /**
     * PDF minimal satu halaman (Helvetica, WinAnsi) berisi teks contoh.
     */
    public function pdf(string $fileName): string
    {
        $lines = [self::TEXT, 'Nama berkas: ' . $fileName, 'Berkas ini hanyalah contoh pada versi demo SIMDOK.'];

        $content = "BT\n/F1 18 Tf\n72 760 Td\n";
        foreach ($lines as $i => $line) {
            if ($i === 1) {
                $content .= "/F1 12 Tf\n0 -34 Td\n";
            } elseif ($i > 1) {
                $content .= "0 -20 Td\n";
            }
            $content .= '(' . $this->escapePdf($line) . ") Tj\n";
        }
        $content .= "ET\n";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . 'endstream',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        ];

        $pdf     = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[$i + 1] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $object . "\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }
        $pdf .= 'trailer << /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xref . "\n%%EOF\n";

        return $pdf;
    }

    /**
     * PNG gradien biru PLN (murni PHP + zlib, tanpa GD).
     */
    public function png(int $width = 320, int $height = 200): string
    {
        $raw = '';
        for ($y = 0; $y < $height; $y++) {
            $raw .= "\x00"; // filter: none
            $t = $y / max(1, $height - 1);
            $r = (int) (219 - 204 * $t); // #DBEAFE → #0F6CBD
            $g = (int) (234 - 126 * $t);
            $b = (int) (254 - 65 * $t);
            $raw .= str_repeat(chr($r) . chr($g) . chr($b), $width);
        }

        return "\x89PNG\r\n\x1a\n"
            . $this->pngChunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            . $this->pngChunk('IDAT', (string) gzcompress($raw, 9))
            . $this->pngChunk('IEND', '');
    }

    private function pngChunk(string $type, string $data): string
    {
        return pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    }

    private function escapePdf(string $text): string
    {
        $ansi = (string) mb_convert_encoding($text, 'Windows-1252', 'UTF-8');

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ansi);
    }
}
