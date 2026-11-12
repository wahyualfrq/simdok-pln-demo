<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Exceptions\GoogleAuthException;
use Google\Client;

/**
 * GoogleApiService
 *
 * Provider terpusat untuk membuat Google\Client yang siap digunakan.
 * Semua service Google (GoogleDriveService, GoogleSheetService) wajib
 * mengambil Google\Client dari sini — tidak boleh membuat Client sendiri.
 *
 * Dependency Flow:
 *   GoogleOAuthService → GoogleApiService → GoogleDriveService / GoogleSheetService
 *
 * Sesuai dengan: 12_GOOGLE_API_SERVICE.md
 */
class GoogleApiService
{
    public function __construct(
        private readonly GoogleOAuthService $oAuthService
    ) {}

    /**
     * Menghasilkan Google\Client yang sudah terautentikasi dan siap dipakai.
     * Access Token diperbarui otomatis jika sudah expired.
     *
     * @return Client
     * @throws GoogleAuthException Jika token tidak ada atau refresh gagal.
     */
    public function getClient(): Client
    {
        return $this->oAuthService->getAuthenticatedClient();
    }

    /**
     * Mendapatkan Spreadsheet ID dari settings, fall back ke auto-discovery dari root folder atau env/config.
     *
     * @return string
     */
    public function getSpreadsheetId(): string
    {
        $settings = $this->getSettings();
        $spreadsheetId = $settings['spreadsheet_id'] ?? null;

        if (!empty($spreadsheetId)) {
            return $spreadsheetId;
        }

        $envSheetId = config('services.google.monitoring_spreadsheet_id', '');
        if (!empty($envSheetId)) {
            return $envSheetId;
        }

        // Auto-detect dari Root Folder jika belum tersimpan di settings.json
        $rootId = $settings['root_folder_id'] ?? null;
        if (!empty($rootId)) {
            try {
                $driveService = app(GoogleDriveService::class);
                $files = $driveService->listFiles($rootId);

                $detectedId = $this->findSpreadsheetInFiles($files);

                if (!empty($detectedId)) {
                    $settings['spreadsheet_id'] = $detectedId;
                    $this->oAuthService->saveSettings($settings);
                    return $detectedId;
                }
            } catch (\Throwable $e) {
                // Abaikan kesalahan pencarian otomatis
            }
        }

        return '';
    }

    /**
     * Helper untuk menemukan file spreadsheet (Google Sheet / Excel / CSV) dari daftar file.
     *
     * @param  array  $files
     * @return string|null
     */
    public function findSpreadsheetInFiles(array $files): ?string
    {
        // 1. Google Native Spreadsheet
        foreach ($files as $file) {
            if (($file['mimeType'] ?? '') === 'application/vnd.google-apps.spreadsheet') {
                return $file['id'];
            }
        }

        // 2. Excel / CSV File berdasarkan MimeType atau Ekstensi Nama / Kata Kunci
        foreach ($files as $file) {
            $mime = strtolower($file['mimeType'] ?? '');
            $name = strtolower($file['name'] ?? '');

            if (
                str_contains($mime, 'spreadsheet') ||
                str_contains($mime, 'excel') ||
                str_contains($mime, 'csv') ||
                str_ends_with($name, '.xlsx') ||
                str_ends_with($name, '.xls') ||
                str_ends_with($name, '.csv') ||
                str_contains($name, 'template') ||
                str_contains($name, 'datatester')
            ) {
                return $file['id'];
            }
        }

        // 3. Fallback file apapun jika hanya ada file non-folder di root
        foreach ($files as $file) {
            if (empty($file['isFolder'])) {
                return $file['id'];
            }
        }

        return null;
    }

    /**
     * Mendapatkan pengaturan Google secara dinamis.
     *
     * @return array
     */
    public function getSettings(): array
    {
        return $this->oAuthService->getSettings();
    }

    /**
     * Memeriksa apakah koneksi Google sudah aktif.
     *
     * @return bool
     */
    public function isConnected(): bool
    {
        return $this->oAuthService->hasToken();
    }

    /**
     * Mengambil email akun Google yang sedang terhubung.
     *
     * @return string|null
     */
    public function getConnectedEmail(): ?string
    {
        return $this->oAuthService->getConnectedEmail();
    }
}
