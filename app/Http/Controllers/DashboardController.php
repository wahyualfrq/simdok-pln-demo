<?php

namespace App\Http\Controllers;

use App\Exceptions\GoogleAuthException;
use App\Services\CacheService;
use App\Services\Google\GoogleOAuthService;
use App\Services\Google\GoogleDriveService;
use App\Services\MonitoringService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * DashboardController
 *
 * Bertanggung jawab menampilkan halaman dashboard utama.
 * Data diambil dari cache (TTL 30 menit) untuk performa optimal.
 * Cache di-invalidate secara event-driven saat terjadi upload/delete.
 *
 * Sesuai dengan: 08_CODING_STANDARD.md (Controller tipis)
 */
class DashboardController extends Controller
{
    public function __construct(
        private readonly GoogleOAuthService $oAuthService,
        private readonly GoogleDriveService $driveService,
        private readonly MonitoringService $monitoringService,
        private readonly CacheService $cacheService
    ) {}

    /**
     * Menampilkan halaman dashboard utama dengan ringkasan status.
     * Menggunakan cache untuk menghindari request Google API berulang.
     *
     * @return View
     */
    public function index(Request $request): View
    {
        // Handling header refresh button (?refresh=...)
        if ($request->query('refresh')) {
            $this->cacheService->invalidateAll();
        }

        $isConnected = $this->oAuthService->hasToken();
        $settings    = $this->oAuthService->getSettings();
        $rootFolderId   = $settings['root_folder_id'] ?? null;
        $rootFolderName = $settings['root_folder_name'] ?? null;
        $email          = $settings['email'] ?? null;

        $rootFolders = [];
        $summary     = ['total' => 0, 'complete' => 0, 'pending' => 0, 'incomplete' => 0, 'progress' => 0, 'total_units' => 0];
        $monthlyProgress = [];
        $error       = null;
        $isRootFolderConfigured = ! empty($rootFolderId);

        // Tentukan semester aktif berdasarkan bulan saat ini
        $currentMonth   = (int) now()->format('n');
        $activeSemester = in_array($currentMonth, [6, 7, 8, 9, 10, 11]) ? 'Semester 2' : 'Semester 1';

        if ($isConnected && $isRootFolderConfigured) {
            try {
                // Coba ambil dari cache dashboard
                $cached = $this->cacheService->getDashboard();

                if ($cached !== null) {
                    $rootFolders     = $cached['rootFolders'];
                    $summary         = $cached['summary'];
                    $monthlyProgress = $cached['monthlyProgress'] ?? [];
                } else {
                    // Murni dari database SQLite lokal (ZERO Google API Call)
                    $rootFolders     = $this->monitoringService->getRootFoldersFromDb();
                    $summary         = $this->monitoringService->getOverallSummary();
                    $monthlyProgress = $this->monitoringService->getMonthlyProgressForDashboard();

                    // Simpan ke cache
                    $this->cacheService->putDashboard([
                        'rootFolders'     => $rootFolders,
                        'summary'         => $summary,
                        'monthlyProgress' => $monthlyProgress,
                    ]);
                }
            } catch (GoogleAuthException $e) {
                $error = 'Belum terhubung ke Google. <a href="' . route('auth.google.status') . '" class="underline font-semibold">Klik di sini untuk menghubungkan akun Google</a>.';
            } catch (Exception $e) {
                $code = (int) $e->getCode();
                $msg  = strtolower($e->getMessage());

                if ($code === 404 || str_contains($msg, 'not found') || str_contains($msg, 'notfound')) {
                    $error = 'Root Folder tidak ditemukan. Silakan pilih ulang Root Folder.';
                } elseif ($code === 403 || str_contains($msg, 'access') || str_contains($msg, 'permission') || str_contains($msg, 'forbidden')) {
                    $error = 'Anda tidak lagi memiliki akses ke folder ini.';
                } else {
                    $error = 'Tidak dapat terhubung ke Google API. Silakan periksa konfigurasi.';
                }
            }
        }

        return view('dashboard.index', compact(
            'isConnected',
            'isRootFolderConfigured',
            'rootFolderId',
            'rootFolderName',
            'email',
            'rootFolders',
            'summary',
            'monthlyProgress',
            'activeSemester',
            'error'
        ));
    }
}
