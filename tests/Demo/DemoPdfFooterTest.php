<?php

namespace Tests\Demo;

use App\Models\User;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

/**
 * DEMO: footer kustom pada PDF statis (DemoPdfFooter, FPDI).
 * Footer = default panel -> PDF statis apa adanya; footer berbeda -> salinan PDF statis dengan footer baru
 * di halaman terakhir (jumlah halaman sama, tabel/Notes tidak berubah).
 *
 * Jalankan:  php artisan test -c phpunit.demo.xml
 */
class DemoPdfFooterTest extends TestCase
{
    private const CUSTOM = [
        'lokasi'           => 'Jambi',
        'tanggal'          => '01 Januari 2027',
        'disusun_oleh'     => 'Budi Santoso',
        'jabatan_penyusun' => 'STAF ADMINISTRASI',
        'mengetahui'       => 'Siti Aminah',
        'jabatan'          => 'MANAJER UNIT',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        config(['demo.pdf.fake_delay_ms' => 0]);
        $this->artisan('migrate:fresh', ['--seed' => true, '--force' => true])->assertExitCode(0);
        $this->actingAs(User::where('username', 'demo')->firstOrFail());
    }

    private function static(int $semester): string
    {
        return (string) file_get_contents(base_path("resources/demo/pdf/monitoring_semester_{$semester}.pdf"));
    }

    /** Alur preview -> (update-preview) -> serve; mengembalikan isi PDF. */
    private function pdf(string $semester, ?array $footer, bool $viaUpdate = false): string
    {
        $payload = ['semester' => $semester] + ($footer === null ? [] : ['footer' => $footer]);
        $preview = $this->postJson('/monitoring/pdf/preview', $payload)->assertOk()->assertJson(['success' => true]);
        $tempId  = $preview->json('temp_id');

        if ($viaUpdate) {
            $tempId = $this->postJson('/monitoring/pdf/update-preview', $payload + ['temp_id' => $tempId])->assertOk()->json('temp_id');
        }

        $response = $this->get('/monitoring/pdf/serve/' . $tempId)->assertOk();

        return (string) file_get_contents($response->baseResponse->getFile()->getPathname());
    }

    private function pageCount(string $pdf): int
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($path, $pdf);
        $count = (new Fpdi())->setSourceFile($path);
        @unlink($path);

        return $count;
    }

    private function text(string $pdf): ?string
    {
        if (! function_exists('shell_exec') || trim((string) @shell_exec('pdftotext -v 2>&1')) === '' || ! str_contains((string) @shell_exec('pdftotext -v 2>&1'), 'pdftotext')) {
            return null;
        }

        $path = tempnam(sys_get_temp_dir(), 'pdf');
        file_put_contents($path, $pdf);
        $text = (string) @shell_exec('pdftotext -raw ' . escapeshellarg($path) . ' -');
        @unlink($path);

        return $text;
    }

    public function test_footer_default_menyajikan_pdf_statis_byte_identik(): void
    {
        foreach ([1 => 'Semester 1', 2 => 'Semester 2'] as $n => $semester) {
            $this->assertSame($this->static($n), $this->pdf($semester, null));
            $this->assertSame($this->static($n), $this->pdf($semester, [])); // panel mengirim footer kosong -> default
            $this->assertSame($this->static($n), $this->pdf($semester, null, true));
        }

        $this->assertFalse(class_exists(\Dompdf\Dompdf::class, false), 'Dompdf tidak boleh termuat.');
    }

    public function test_footer_berubah_menghasilkan_pdf_valid_dengan_halaman_sama_dan_teks_baru(): void
    {
        foreach ([1 => 'Semester 1', 2 => 'Semester 2'] as $n => $semester) {
            $static = $this->static($n);
            $custom = $this->pdf($semester, self::CUSTOM, true);

            $this->assertStringStartsWith('%PDF', $custom);
            $this->assertNotSame($static, $custom);
            $this->assertSame($this->pageCount($static), $this->pageCount($custom));

            if (($text = $this->text($custom)) !== null) {
                foreach (['Jambi, 01 Januari 2027', 'Budi Santoso', 'STAF ADMINISTRASI', 'Siti Aminah', 'MANAJER UNIT', 'Disusun oleh', 'Mengetahui', 'Notes:'] as $needle) {
                    $this->assertStringContainsString($needle, $text, "Teks '{$needle}' harus ada pada PDF semester {$n}.");
                }
                $this->assertStringContainsString('SEMESTER ' . ($n === 1 ? 'I' : 'II'), $text);
            }
        }
    }

    public function test_footer_dengan_karakter_di_luar_latin1_dan_teks_panjang_tidak_error(): void
    {
        $footer = self::CUSTOM + [];
        $footer['disusun_oleh']     = "Zoë Müller – 日本語 😀 «test»\x00\x1F";
        $footer['jabatan_penyusun'] = str_repeat('JABATAN SANGAT PANJANG ', 30);
        $footer['mengetahui']       = str_repeat('N', 200);
        $footer['lokasi']           = str_repeat('Lokasi ', 20);

        $pdf = $this->pdf('Semester 1', $footer, true);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame(2, $this->pageCount($pdf));
    }

    public function test_ekspor_excel_tidak_terpengaruh_footer(): void
    {
        $response = $this->post('/monitoring/excel/export', ['semester' => 'Semester 1', 'footer' => self::CUSTOM])->assertOk();

        $this->assertStringStartsWith('PK', (string) file_get_contents($response->baseResponse->getFile()->getPathname()));
    }
}
