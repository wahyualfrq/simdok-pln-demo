<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Exceptions\GoogleAuthException;
use App\Services\Google\GoogleOAuthService;
use Google\Client;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Log;

/**
 * FakeGoogleOAuthService
 *
 * DEMO: Pengganti GoogleOAuthService untuk versi demo (portofolio).
 * Di-bind lewat AppServiceProvider hanya saat config('demo.enabled').
 *
 * - Tidak ada jaringan, tidak ada Google\Client, tidak ada akses
 *   token.json / settings.json / oauth-client.json.
 * - Status koneksi & pengaturan disimpan di SESSION Laravel milik pengunjung
 *   (per sesi, tidak saling memengaruhi). Nilai awal bila session belum
 *   punya data: TERHUBUNG sebagai akun demo dengan Root Folder demo.
 * - Tanpa session (konsol/artisan) dipakai nilai awal tersebut tanpa error;
 *   perubahan hanya bertahan selama instance ini hidup.
 * - Return shape setiap method publik identik dengan versi asli.
 *
 * Sesuai dengan: rules/14_DEMO_MOCK_SPEC.md §5.1
 */
class FakeGoogleOAuthService extends GoogleOAuthService
{
    /** Key session: state koneksi demo (['connected' => bool, 'settings' => array]). */
    private const SESSION_KEY = 'demo_google';

    /** Kode otorisasi simulasi yang diterima exchangeCodeForToken(). */
    private const DEMO_CODE = 'demo-code';

    /** Pesan untuk jalur yang meminta Google\Client nyata di mode demo. */
    private const CLIENT_UNAVAILABLE = 'Mode demo: klien Google tidak tersedia karena integrasi Google Drive/Sheet disimulasikan.';

    /** State cadangan bila tidak ada session (konsol). Null = belum diubah. */
    private ?array $fallbackState = null;

    /**
     * DEMO: sengaja tidak memanggil parent::__construct() agar tidak membaca
     * konfigurasi path token/credentials Google.
     */
    public function __construct() {}

    /**
     * DEMO: Google\Client tidak pernah dibuat. Jalur yang masih memintanya
     * (service Drive/Sheet/scanner asli) harus gagal keras agar terdeteksi.
     *
     * @throws GoogleAuthException
     */
    public function createClient(): Client
    {
        throw new GoogleAuthException(self::CLIENT_UNAVAILABLE);
    }

    /**
     * DEMO: lihat createClient().
     *
     * @throws GoogleAuthException
     */
    public function getAuthenticatedClient(): Client
    {
        throw new GoogleAuthException(self::CLIENT_UNAVAILABLE);
    }

    /**
     * URL internal ke callback demo (tetap di domain aplikasi, bukan Google).
     */
    public function getAuthorizationUrl(): string
    {
        return route('auth.google.callback', ['code' => self::DEMO_CODE]);
    }

    /**
     * Menandai terhubung sebagai akun demo. Tidak ada token sungguhan.
     *
     * @param  string  $code  Harus 'demo-code'
     * @return array Data token simulasi (tanpa secret)
     * @throws GoogleAuthException
     */
    public function exchangeCodeForToken(string $code): array
    {
        if ($code !== self::DEMO_CODE) {
            throw new GoogleAuthException('Kode otorisasi demo tidak valid.');
        }

        $email = (string) config('demo.email');

        // Sama seperti asli: setelah (re)connect, Root Folder harus dipilih ulang.
        $this->writeState([
            'connected' => true,
            'settings'  => [
                'email'            => $email,
                'root_folder_id'   => null,
                'root_folder_name' => null,
                'connected_at'     => now()->toDateString(),
            ],
        ]);

        Log::info('FakeGoogleOAuthService: koneksi demo ditandai terhubung', ['email' => $email]);

        return [
            'demo'       => true,
            'email'      => $email,
            'created_at' => now()->toDateTimeString(),
        ];
    }

    /**
     * Menandai tidak terhubung (hanya pada session pengunjung ini) dan
     * menghapus pengaturan demo.
     */
    public function revoke(): void
    {
        if (! $this->hasToken()) {
            return;
        }

        $this->writeState(['connected' => false, 'settings' => []]);

        Log::info('FakeGoogleOAuthService: koneksi demo diputuskan (disconnect).');
    }

    public function hasToken(): bool
    {
        return (bool) ($this->readState()['connected'] ?? false);
    }

    public function getConnectedEmail(): ?string
    {
        if (! $this->hasToken()) {
            return null;
        }

        $settings = $this->getSettings();

        return ! empty($settings['email']) ? (string) $settings['email'] : (string) config('demo.email');
    }

    /**
     * Pengaturan demo. Kosong ([]) bila belum terhubung — sama seperti asli.
     */
    public function getSettings(): array
    {
        $state = $this->readState();

        return ($state['connected'] ?? false) ? (array) ($state['settings'] ?? []) : [];
    }

    public function saveSettings(array $settings): void
    {
        $state = $this->readState();
        $state['settings'] = $settings;
        $this->writeState($state);
    }

    public function deleteSettings(): void
    {
        $state = $this->readState();
        $state['settings'] = [];
        $this->writeState($state);
    }

    public function saveRootFolder(string $id, string $name, ?string $spreadsheetId = null): void
    {
        $settings = $this->getSettings();
        $settings['root_folder_id']   = $id;
        $settings['root_folder_name'] = $name;
        $settings['spreadsheet_id']   = $spreadsheetId;
        $settings['connected_at']     = $settings['connected_at'] ?? now()->toDateString();
        $this->saveSettings($settings);
    }

    // ─────────────────────────────────────────────────────────
    // PENYIMPANAN STATE (session pengunjung)
    // ─────────────────────────────────────────────────────────

    /**
     * State awal: terhubung sebagai akun demo + Root Folder & spreadsheet dummy.
     */
    private function defaultState(): array
    {
        return [
            'connected' => true,
            'settings'  => [
                'email'            => (string) config('demo.email'),
                'root_folder_id'   => (string) config('demo.root_folder_id'),
                'root_folder_name' => (string) config('demo.root_folder_name'),
                'spreadsheet_id'   => (string) config('demo.spreadsheet_id'),
                'connected_at'     => now()->toDateString(),
            ],
        ];
    }

    private function readState(): array
    {
        $session = $this->session();

        if ($session !== null) {
            $stored = $session->get(self::SESSION_KEY);

            return is_array($stored) ? $stored : $this->defaultState();
        }

        return $this->fallbackState ?? $this->defaultState();
    }

    private function writeState(array $state): void
    {
        $session = $this->session();

        if ($session !== null) {
            $session->put(self::SESSION_KEY, $state);

            return;
        }

        $this->fallbackState = $state;
    }

    /**
     * Session request aktif, atau null bila berjalan tanpa session (konsol).
     */
    private function session(): ?Session
    {
        $request = request();

        return $request->hasSession() ? $request->session() : null;
    }
}
