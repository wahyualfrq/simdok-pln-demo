<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FooterSettingsService
 *
 * Menyimpan dan mengambil konfigurasi footer laporan PDF (default) dari tabel settings.
 * Footer mencakup: lokasi, tanggal, nama penyusun, jabatan penyusun, nama mengetahui, jabatan mengetahui.
 */
class FooterSettingsService
{
    private const SETTING_KEY = 'pdf_footer_default';

    /**
     * Default footer values.
     */
    private const DEFAULTS = [
        'lokasi'           => 'Palembang',
        'tanggal'          => '', // kosong = hari ini
        'disusun_oleh'     => 'Nama Penyusun (Demo)',
        'jabatan_penyusun' => 'JABATAN PENYUSUN (DEMO)',
        'mengetahui'       => 'Nama Pejabat (Demo)',
        'jabatan'          => 'JABATAN PEJABAT (DEMO)',
    ];

    /**
     * Ambil footer default dari database.
     * Jika belum ada, kembalikan nilai default hardcoded.
     */
    public function getDefault(): array
    {
        try {
            $row = DB::table('settings')->where('key', self::SETTING_KEY)->first();
            if ($row && !empty($row->value)) {
                $decoded = json_decode($row->value, true);
                if (is_array($decoded)) {
                    return array_merge(self::DEFAULTS, $decoded);
                }
            }
        } catch (\Exception $e) {
            Log::warning('[FooterSettingsService] Gagal membaca settings: ' . $e->getMessage());
        }

        $defaults = self::DEFAULTS;
        $defaults['tanggal'] = now()->locale('id')->isoFormat('DD MMMM YYYY');
        return $defaults;
    }

    /**
     * Simpan footer sebagai default ke database.
     */
    public function save(array $footer): void
    {
        try {
            $cleanFooter = [
                'lokasi'           => trim($footer['lokasi'] ?? self::DEFAULTS['lokasi']),
                'tanggal'          => trim($footer['tanggal'] ?? ''),
                'disusun_oleh'     => trim($footer['disusun_oleh'] ?? self::DEFAULTS['disusun_oleh']),
                'jabatan_penyusun' => trim($footer['jabatan_penyusun'] ?? self::DEFAULTS['jabatan_penyusun']),
                'mengetahui'       => trim($footer['mengetahui'] ?? self::DEFAULTS['mengetahui']),
                'jabatan'          => trim($footer['jabatan'] ?? self::DEFAULTS['jabatan']),
            ];

            DB::table('settings')->updateOrInsert(
                ['key' => self::SETTING_KEY],
                ['value' => json_encode($cleanFooter), 'updated_at' => now(), 'created_at' => now()]
            );

            Log::info('[FooterSettingsService] Footer default disimpan.', $cleanFooter);
        } catch (\Exception $e) {
            Log::error('[FooterSettingsService] Gagal menyimpan settings: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Validasi & normalisasi footer input dari user.
     * Jika field kosong, gunakan default.
     */
    public function normalize(array $input): array
    {
        $defaults = $this->getDefault();
        $today    = now()->locale('id')->isoFormat('DD MMMM YYYY');

        return [
            'lokasi'           => trim($input['lokasi'] ?? '') ?: $defaults['lokasi'],
            'tanggal'          => trim($input['tanggal'] ?? '') ?: $today,
            'disusun_oleh'     => trim($input['disusun_oleh'] ?? '') ?: $defaults['disusun_oleh'],
            'jabatan_penyusun' => trim($input['jabatan_penyusun'] ?? '') ?: $defaults['jabatan_penyusun'],
            'mengetahui'       => trim($input['mengetahui'] ?? '') ?: $defaults['mengetahui'],
            'jabatan'          => trim($input['jabatan'] ?? '') ?: $defaults['jabatan'],
        ];
    }
}
