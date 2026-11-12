<?php

declare(strict_types=1);

namespace App\Services\Google;

use App\Exceptions\GoogleAuthException;
use Google\Client;
use Illuminate\Support\Facades\Log;

/**
 * GoogleOAuthService
 *
 * Bertanggung jawab untuk seluruh siklus autentikasi Google OAuth 2.0:
 * - Redirect ke halaman login Google
 * - Handle callback dan tukar Authorization Code menjadi token
 * - Menyimpan Refresh Token ke storage/app/google/token.json
 * - Mengambil Access Token baru dari Refresh Token secara otomatis
 * - Menyediakan Google\Client yang siap digunakan
 * - Revoke token (disconnect)
 *
 * Login hanya perlu dilakukan sekali. Access Token diperbarui otomatis.
 *
 * Sesuai dengan: 11_GOOGLE_OAUTH_MIGRATION.md & 12_GOOGLE_API_SERVICE.md
 */
class GoogleOAuthService
{
    /** Path ke file token yang menyimpan Refresh Token. */
    private string $tokenPath;

    /** Path ke file OAuth client credentials. */
    private string $clientPath;

    public function __construct()
    {
        $this->tokenPath  = config('services.google.token_path');
        $this->clientPath = config('services.google.client_path');
    }

    /**
     * Membuat instance Google\Client yang dikonfigurasi dengan OAuth credentials.
     *
     * @return Client
     * @throws GoogleAuthException
     */
    public function createClient(): Client
    {
        $clientId = config('services.google.client_id');
        $clientSecret = config('services.google.client_secret');
        $redirectUri = config('services.google.redirect_uri', 'http://localhost:8000/auth/google/callback');

        $client = new Client();
        $client->setApplicationName(config('app.name', 'SIMDOK'));

        if (!empty($clientId) && !empty($clientSecret)) {
            $client->setClientId($clientId);
            $client->setClientSecret($clientSecret);
            $client->setRedirectUri($redirectUri);
        } elseif (file_exists($this->clientPath)) {
            $client->setAuthConfig($this->clientPath);
            $client->setRedirectUri($redirectUri);
        } else {
            throw new GoogleAuthException(
                'Kredensial Google OAuth tidak lengkap. Masukkan GOOGLE_CLIENT_ID & GOOGLE_CLIENT_SECRET di Environment Variable Render, atau letakkan berkas oauth-client.json.'
            );
        }

        $client->setAccessType('offline');
        $client->setPrompt('select_account consent');
        $client->setIncludeGrantedScopes(true);

        // Scope yang diperlukan: Drive (read/write) + Spreadsheet (read/write)
        $client->addScope(\Google\Service\Drive::DRIVE);
        $client->addScope(\Google\Service\Sheets::SPREADSHEETS);

        return $client;
    }

    /**
     * Membuat Google\Client yang sudah terautentikasi dengan Access Token terbaru.
     * Access Token diperbarui otomatis dari Refresh Token jika sudah expired.
     *
     * @return Client
     * @throws GoogleAuthException
     */
    public function getAuthenticatedClient(): Client
    {
        $client = $this->createClient();

        if (! $this->hasToken()) {
            throw new GoogleAuthException(
                'Belum ada koneksi Google. Silakan login melalui /auth/google.'
            );
        }

        $tokenData = $this->readToken();

        if (empty($tokenData['refresh_token'])) {
            throw new GoogleAuthException(
                'Refresh Token tidak ditemukan. Silakan hubungkan ulang akun Google.'
            );
        }

        // If access_token is missing (old fallback), populate dummy values to satisfy the library format
        if (empty($tokenData['access_token'])) {
            $tokenData['access_token'] = 'dummy-token';
            $tokenData['expires_in'] = 0;
        }

        $client->setAccessToken($tokenData);

        // Jika Access Token sudah expired atau belum ada, perbarui menggunakan Refresh Token
        if ($client->isAccessTokenExpired()) {
            $newAccessToken = $client->fetchAccessTokenWithRefreshToken($tokenData['refresh_token']);

            if (isset($newAccessToken['error'])) {
                Log::error('GoogleOAuthService: Gagal memperbarui Access Token', [
                    'error' => $newAccessToken['error'],
                    'error_description' => $newAccessToken['error_description'] ?? '',
                ]);
                throw new GoogleAuthException(
                    'Gagal memperbarui token Google. Silakan hubungkan ulang akun Google.'
                );
            }

            // Simpan token baru (akan dimerge dengan yang lama)
            $this->persistToken($newAccessToken, $tokenData['email'] ?? '');
        }

        return $client;
    }

    /**
     * Menghasilkan URL redirect ke halaman login Google.
     *
     * @return string URL OAuth Google
     * @throws GoogleAuthException
     */
    public function getAuthorizationUrl(): string
    {
        $client = $this->createClient();
        return $client->createAuthUrl();
    }

    /**
     * Menukar Authorization Code menjadi Access Token + Refresh Token.
     * Menyimpan Refresh Token ke storage.
     *
     * @param  string  $code  Authorization Code dari Google callback
     * @return array  Token data dari Google
     * @throws GoogleAuthException
     */
    public function exchangeCodeForToken(string $code): array
    {
        $client = $this->createClient();

        $tokenData = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($tokenData['error'])) {
            Log::error('GoogleOAuthService: Gagal menukar code dengan token', [
                'error' => $tokenData['error'],
                'error_description' => $tokenData['error_description'] ?? '',
            ]);
            throw new GoogleAuthException(
                'Gagal mendapatkan token dari Google: ' . ($tokenData['error_description'] ?? $tokenData['error'])
            );
        }

        if (empty($tokenData['refresh_token'])) {
            throw new GoogleAuthException(
                'Google tidak memberikan Refresh Token. ' .
                'Pastikan prompt=consent dan access_type=offline sudah diset, ' .
                'atau cabut akses aplikasi di Google Account dan coba lagi.'
            );
        }

        // Ambil email user dari ID Token jika tersedia
        $email = '';
        if (! empty($tokenData['id_token'])) {
            $client->setAccessToken($tokenData);
            $payload = $client->verifyIdToken();
            $email   = $payload['email'] ?? '';
        }

        $this->persistToken($tokenData, $email);

        // Inisialisasi settings.json saat berhasil login
        $settings = [
            'email'            => $email,
            'root_folder_id'   => null,
            'root_folder_name' => null,
            'connected_at'     => now()->toDateString(),
        ];
        $this->saveSettings($settings);

        Log::info('GoogleOAuthService: Token & Settings berhasil disimpan', [
            'email' => $email,
        ]);

        return $tokenData;
    }

    /**
     * Mencabut akses OAuth dan menghapus token yang tersimpan.
     *
     * @return void
     * @throws GoogleAuthException
     */
    public function revoke(): void
    {
        if (! $this->hasToken()) {
            return;
        }

        try {
            $client = $this->createClient();
            $tokenData = $this->readToken();

            $client->setAccessToken([
                'refresh_token' => $tokenData['refresh_token'],
            ]);

            $client->revokeToken();
        } catch (\Exception $e) {
            Log::warning('GoogleOAuthService: Gagal mencabut token di Google', [
                'error' => $e->getMessage(),
            ]);
            // Tetap hapus token lokal meskipun revoke di Google gagal
        }

        $this->deleteToken();
        $this->deleteSettings();

        Log::info('GoogleOAuthService: Token & Settings berhasil dihapus (disconnect).');
    }

    /**
     * Memeriksa apakah token sudah tersimpan (artinya sudah pernah login).
     *
     * @return bool
     */
    public function hasToken(): bool
    {
        if (! file_exists($this->tokenPath)) {
            return false;
        }

        $data = $this->readToken();
        return ! empty($data['refresh_token']);
    }

    /**
     * Mengambil email dari token yang tersimpan.
     *
     * @return string|null
     */
    public function getConnectedEmail(): ?string
    {
        if (! $this->hasToken()) {
            return null;
        }

        $settings = $this->getSettings();
        if (! empty($settings['email'])) {
            return $settings['email'];
        }

        try {
            $drive = new \Google\Service\Drive($this->getAuthenticatedClient());
            $about = $drive->about->get(['fields' => 'user(emailAddress)']);
            $email = $about->getUser()->getEmailAddress();

            if ($email) {
                $settings['email'] = $email;
                $this->saveSettings($settings);
                return $email;
            }
        } catch (\Exception $e) {
            Log::warning('GoogleOAuthService: Gagal mengambil email dari Drive API', ['error' => $e->getMessage()]);
        }

        return $this->readToken()['email'] ?? null;
    }

    /**
     * Menyimpan Refresh Token & Token Bundle ke file storage.
     *
     * @param  array   $tokenBundle
     * @param  string  $email
     * @return void
     */
    private function persistToken(array $tokenBundle, string $email): void
    {
        $tokenDir = dirname($this->tokenPath);

        if (! is_dir($tokenDir)) {
            mkdir($tokenDir, 0700, true);
        }

        // Jika file lama ada, pertahankan refresh_token & email jika respons baru tidak memilikinya
        if (file_exists($this->tokenPath)) {
            $existing = json_decode(file_get_contents($this->tokenPath), true) ?? [];
            if (empty($tokenBundle['refresh_token']) && ! empty($existing['refresh_token'])) {
                $tokenBundle['refresh_token'] = $existing['refresh_token'];
            }
            if (empty($email) && ! empty($existing['email'])) {
                $email = $existing['email'];
            }
        }

        $tokenBundle['email']      = $email;
        $tokenBundle['created_at'] = now()->toDateTimeString();

        file_put_contents($this->tokenPath, json_encode($tokenBundle, JSON_PRETTY_PRINT));
    }

    /**
     * Membaca data token dari file storage.
     *
     * @return array
     */
    private function readToken(): array
    {
        if (! file_exists($this->tokenPath)) {
            return [];
        }

        $contents = file_get_contents($this->tokenPath);
        return json_decode($contents, true) ?? [];
    }

    /**
     * Menghapus file token dari storage.
     *
     * @return void
     */
    private function deleteToken(): void
    {
        if (file_exists($this->tokenPath)) {
            unlink($this->tokenPath);
        }
    }

    /**
     * Membaca pengaturan Google dari settings.json.
     * Jika file tidak ada, buat fallback dari token.json (jika ada).
     *
     * @return array
     */
    public function getSettings(): array
    {
        $settingsPath = config('services.google.settings_path');
        if (file_exists($settingsPath)) {
            return json_decode(file_get_contents($settingsPath), true) ?? [];
        }

        if ($this->hasToken()) {
            $tokenData = $this->readToken();
            $email = $tokenData['email'] ?? '';
            $createdAt = isset($tokenData['created_at']) 
                ? date('Y-m-d', strtotime($tokenData['created_at'])) 
                : now()->toDateString();
            
            $settings = [
                'email'            => $email,
                'root_folder_id'   => null,
                'root_folder_name' => null,
                'connected_at'     => $createdAt,
            ];
            $this->saveSettings($settings);
            return $settings;
        }

        return [];
    }

    /**
     * Menyimpan pengaturan Google ke settings.json.
     *
     * @param  array  $settings
     * @return void
     */
    public function saveSettings(array $settings): void
    {
        $settingsPath = config('services.google.settings_path');
        $dir = dirname($settingsPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        file_put_contents(
            $settingsPath,
            json_encode($settings, JSON_PRETTY_PRINT)
        );
    }

    /**
     * Menghapus file settings.json.
     *
     * @return void
     */
    public function deleteSettings(): void
    {
        $settingsPath = config('services.google.settings_path');
        if (file_exists($settingsPath)) {
            unlink($settingsPath);
        }
    }

    /**
     * Menyimpan konfigurasi Root Folder ke settings.json.
     *
     * @param  string  $id
     * @param  string  $name
     * @param  string|null  $spreadsheetId
     * @return void
     */
    public function saveRootFolder(string $id, string $name, ?string $spreadsheetId = null): void
    {
        $settings = $this->getSettings();
        $settings['root_folder_id']   = $id;
        $settings['root_folder_name'] = $name;
        $settings['spreadsheet_id']   = $spreadsheetId;
        $settings['connected_at']     = $settings['connected_at'] ?? now()->toDateString();
        $this->saveSettings($settings);
    }
}
