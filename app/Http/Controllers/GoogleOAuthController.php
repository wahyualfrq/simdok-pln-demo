<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\GoogleAuthException;
use App\Services\CacheService;
use App\Services\Google\GoogleOAuthService;
use App\Services\Google\GoogleDriveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Log;

/**
 * GoogleOAuthController
 *
 * Menangani seluruh alur autentikasi Google OAuth 2.0:
 * - Redirect ke Google Login
 * - Handle Callback dari Google
 * - Disconnect (cabut akses)
 * - Status koneksi
 *
 * Controller ini hanya bertugas menerima request, memanggil GoogleOAuthService,
 * dan mengembalikan response yang sesuai. Tidak ada business logic di sini.
 *
 * Sesuai dengan: 11_GOOGLE_OAUTH_MIGRATION.md & 08_CODING_STANDARD.md
 */
class GoogleOAuthController extends Controller
{
    public function __construct(
        private readonly GoogleOAuthService $oAuthService,
        private readonly GoogleDriveService $driveService,
        private readonly \App\Services\Google\GoogleApiService $apiService,
        private readonly CacheService $cacheService
    ) {}

    /**
     * Menampilkan halaman status koneksi Google.
     *
     * @return View
     */
    public function status(): View
    {
        $isConnected = $this->oAuthService->hasToken();
        $email       = $this->oAuthService->getConnectedEmail();
        $settings    = $this->oAuthService->getSettings();
        
        $rootFolders = [];
        $driveError  = null;

        if ($isConnected) {
            try {
                $rootFolders = $this->driveService->listRootFolders();
            } catch (\Exception $e) {
                $driveError = 'Gagal memuat folder Google Drive: ' . $e->getMessage();
            }
        }

        return view('auth.google', compact('isConnected', 'email', 'settings', 'rootFolders', 'driveError'));
    }

    /**
     * Redirect pengguna ke halaman login Google OAuth.
     *
     * @return RedirectResponse
     */
    public function redirect(): RedirectResponse
    {
        try {
            $authUrl = $this->oAuthService->getAuthorizationUrl();
            return redirect()->away($authUrl);
        } catch (GoogleAuthException $e) {
            return redirect()->route('auth.google.status')
                ->with('error', 'Gagal membuat URL autentikasi Google: ' . $e->getMessage());
        }
    }

    /**
     * Handle callback dari Google setelah user login.
     * Menukar Authorization Code menjadi token dan menyimpan Refresh Token.
     *
     * @param  Request  $request
     * @return RedirectResponse
     */
    public function callback(Request $request): RedirectResponse
    {
        // Jika user menolak izin di halaman Google
        if ($request->has('error')) {
            return redirect()->route('auth.google.status')
                ->with('error', 'Akses Google ditolak: ' . $request->input('error'));
        }

        $code = $request->input('code');

        if (empty($code)) {
            return redirect()->route('auth.google.status')
                ->with('error', 'Authorization Code tidak ditemukan dari Google.');
        }

        try {
            $this->oAuthService->exchangeCodeForToken($code);

            // Invalidate semua cache karena koneksi Google baru
            $this->cacheService->invalidateAll();

            return redirect()->route('dashboard')
                ->with('success', 'Berhasil terhubung ke Google! Semua fitur Drive dan Monitoring sudah aktif.');
        } catch (GoogleAuthException $e) {
            return redirect()->route('auth.google.status')
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Mencabut akses OAuth dan menghapus token tersimpan.
     *
     * @return RedirectResponse
     */
    public function disconnect(): RedirectResponse
    {
        try {
            $this->oAuthService->revoke();

            // Invalidate semua cache setelah koneksi Google dicabut
            $this->cacheService->invalidateAll();

            return redirect()->route('auth.google.status')
                ->with('success', 'Akun Google berhasil diputuskan dari SIMDOK.');
        } catch (\Exception $e) {
            return redirect()->route('auth.google.status')
                ->with('error', 'Gagal memutus koneksi: ' . $e->getMessage());
        }
    }

    /**
     * Mengembalikan status koneksi sebagai JSON (untuk AJAX/polling di sidebar).
     *
     * @return JsonResponse
     */
    public function statusJson(): JsonResponse
    {
        return response()->json([
            'connected' => $this->oAuthService->hasToken(),
            'email'     => $this->oAuthService->getConnectedEmail(),
        ]);
    }

    /**
     * Menyimpan Root Folder ID dan metadata ke settings.json.
     *
     * @param  Request  $request
     * @return RedirectResponse
     */
    public function saveRoot(Request $request): RedirectResponse
    {
        $request->validate([
            'root_folder_id' => 'required|string',
        ]);

        $folderId = trim($request->input('root_folder_id'));

        try {
            $folderMetadata = $this->driveService->getFolder($folderId);
            
            if (!$folderMetadata['isFolder']) {
                return redirect()->route('auth.google.status')
                    ->with('error', 'ID yang dimasukkan bukan merupakan Folder.');
            }

            // Cari spreadsheet di dalam folder ini (Google Sheet / Excel / CSV / File Data)
            $files = $this->driveService->listFiles($folderId);
            $spreadsheetId = $this->apiService->findSpreadsheetInFiles($files);

            $this->oAuthService->saveRootFolder($folderId, $folderMetadata['name'], $spreadsheetId);

            // Invalidate semua cache karena root folder berubah
            $this->cacheService->invalidateAll();

            return redirect()->route('auth.google.status')
                ->with('success', 'Root Folder berhasil disimpan.');
        } catch (\Exception $e) {
            Log::error('GoogleOAuthController::saveRoot gagal', ['error' => $e->getMessage()]);
            return redirect()->route('auth.google.status')
                ->with('error', 'Gagal memverifikasi Folder ID. Pastikan ID benar dan akun Google Anda memiliki akses.');
        }
    }

    /**
     * Memperbarui metadata (nama) Root Folder dari API Google Drive.
     *
     * @return RedirectResponse
     */
    public function refreshRoot(): RedirectResponse
    {
        $settings = $this->oAuthService->getSettings();
        $folderId = $settings['root_folder_id'] ?? null;

        if (!$folderId) {
            return redirect()->route('auth.google.status')
                ->with('error', 'Belum ada Root Folder yang dipilih.');
        }

        try {
            $folderMetadata = $this->driveService->getFolder($folderId);
            $files = $this->driveService->listFiles($folderId);
            $spreadsheetId = $this->apiService->findSpreadsheetInFiles($files) ?? ($settings['spreadsheet_id'] ?? null);

            $this->oAuthService->saveRootFolder($folderId, $folderMetadata['name'], $spreadsheetId);

            return redirect()->route('auth.google.status')
                ->with('success', 'Metadata Root Folder berhasil diperbarui.');
        } catch (\Exception $e) {
            return redirect()->route('auth.google.status')
                ->with('error', 'Gagal memperbarui metadata Root Folder: ' . $e->getMessage());
        }
    }

    /**
     * Mengembalikan daftar semua folder dalam format JSON (untuk pencarian Folder Picker AJAX).
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function listFoldersJson(Request $request): JsonResponse
    {
        $search = $request->query('q', '');

        try {
            $folders = $this->driveService->listAllFolders($search);
            return response()->json($folders);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Gagal memuat folder: ' . $e->getMessage()
            ], 500);
        }
    }
}
