<?php

namespace App\Http\Controllers;

use App\Exceptions\GoogleAuthException;
use App\Jobs\MonitoringSyncJob;
use App\Services\CacheService;
use App\Services\DriveMonitoringSyncService;
use App\Services\MonitoringService;
use App\Services\PdfExportService;
use App\Services\FooterSettingsService;
use App\Services\Google\GoogleApiService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

use App\Services\ExcelReportService;
use Illuminate\Support\Facades\Log;

/**
 * MonitoringController
 *
 * Menampilkan halaman monitoring status upload per unit murni dari Database SQLite.
 * EXCEL-FIRST ARCHITECTURE: Master Template Excel adalah Single Source of Truth Layout.
 */
class MonitoringController extends Controller
{
    public function __construct(
        private readonly MonitoringService $monitoringService,
        private readonly DriveMonitoringSyncService $syncService,
        private readonly CacheService $cacheService,
        private readonly GoogleApiService $apiService,
        private readonly FooterSettingsService $footerSettingsService,
        private readonly ExcelReportService $excelReportService,
    ) {}

    /**
     * Menampilkan halaman monitoring dengan tabel status per unit dari database.
     *
     * @param  Request  $request
     * @return View
     */
    public function index(Request $request): View
    {
        $selectedSemesterId = $request->query('semester_id', 'Semester 1');

        // Handles header refresh button (?refresh=...)
        if ($request->query('refresh')) {
            $this->cacheService->invalidateAll();
        }

        $error              = null;
        $regions            = [];
        $months             = [];
        $summary            = ['total' => 0, 'complete' => 0, 'incomplete' => 0, 'mismatch' => 0, 'progress' => 0];
        $semesters          = ['Semester 1', 'Semester 2'];
        $isEmpty            = false;
        $rootWarning        = null;

        try {
            if ($this->apiService->isConnected()) {
                $settings = $this->apiService->getSettings();
                if (empty($settings['root_folder_id'])) {
                    $rootWarning = 'Root Folder Google Drive belum dipilih. <a href="' . route('auth.google.status') . '" class="underline font-bold">Klik di sini untuk memilih Root Folder Anda</a> agar sinkronisasi membaca dari folder Google Drive yang benar.';
                    
                    return view('monitoring.index', [
                        'regions'            => [],
                        'summary'            => ['total' => 0, 'complete' => 0, 'incomplete' => 0, 'mismatch' => 0, 'progress' => 0],
                        'semesters'          => $semesters,
                        'months'             => [],
                        'error'              => null,
                        'rootWarning'        => $rootWarning,
                        'selectedSemesterId' => $selectedSemesterId,
                        'isEmpty'            => true,
                    ]);
                }
            }

            $monitoringData = $this->monitoringService->getMonitoringData($selectedSemesterId);

            $regions = $monitoringData['regions'];
            $months  = $monitoringData['months'];
            $summary = $monitoringData['summary'];
            $isEmpty = $monitoringData['is_empty'] ?? false;
        } catch (GoogleAuthException $e) {
            $error = 'Belum terhubung ke Google. <a href="' . route('auth.google.status') . '" class="underline font-semibold">Klik di sini untuk menghubungkan akun Google</a>.';
        } catch (Exception $e) {
            $error = 'Gagal memuat data monitoring dari database: ' . $e->getMessage();
        }

        return view('monitoring.index', compact(
            'regions', 'summary', 'semesters', 'months', 'error', 'rootWarning', 'selectedSemesterId', 'isEmpty'
        ));
    }

    /**
     * Refresh Monitoring Page: bersihkan cache tampilan lokal dan muat ulang dari SQLite.
     *
     * @param  Request  $request
     * @return RedirectResponse
     */
    public function sync(Request $request): RedirectResponse
    {
        $selectedSemesterId = $request->query('semester_id', 'Semester 1');

        try {
            $this->cacheService->invalidateMonitoring($selectedSemesterId);

            return redirect()->route('monitoring', ['semester_id' => $selectedSemesterId])
                ->with('success', 'Tampilan monitoring berhasil diperbarui dari database lokal.');
        } catch (Exception $e) {
            return redirect()->route('monitoring', ['semester_id' => $selectedSemesterId])
                ->with('error', 'Gagal memperbarui tampilan: ' . $e->getMessage());
        }
    }

    /**
     * Full Sync Engine: Memicu sinkronisasi data dari Google Drive & Sheet ke SQLite Database secara ASYNC via Queue Job.
     * Pindaian hanya dilakukan untuk 6 bulan semester yang dipilih di background worker.
     *
     * @param  Request  $request
     * @return RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function fullSync(Request $request): mixed
    {
        $selectedSemesterId = $request->query('semester_id', 'Semester 1');

        try {
            // 1. Sync Lock Protection: Cek jika ada sync yang sedang PENDING atau RUNNING
            $activeSync = \App\Models\MonitoringSyncLog::where('semester', $selectedSemesterId)
                ->whereIn('status', ['PENDING', 'RUNNING'])
                ->latest('id')
                ->first();

            if ($activeSync) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Sinkronisasi sedang berjalan. Silakan tunggu hingga proses selesai.',
                        'status'  => $activeSync->status,
                    ], 409);
                }

                return redirect()->route('monitoring', ['semester_id' => $selectedSemesterId])
                    ->with('error', 'Sinkronisasi sedang berjalan. Silakan tunggu hingga proses selesai.');
            }

            // 2. Buat log status PENDING
            $syncLog = \App\Models\MonitoringSyncLog::create([
                'semester'   => $selectedSemesterId,
                'started_at' => now(),
                'status'     => 'PENDING',
                'progress'   => 0,
                'processed'  => 0,
                'total'      => 432,
            ]);

            // 3. Dispatch Job ke Queue Worker
            MonitoringSyncJob::dispatch($selectedSemesterId, $syncLog->id);

            // 4. Invalidate cache memori lokal
            $this->cacheService->invalidateAll();

            if ($request->wantsJson()) {
                return response()->json([
                    'success'     => true,
                    'message'     => 'Proses sinkronisasi Google Drive & Sheet telah dimulai di background.',
                    'sync_log_id' => $syncLog->id,
                ]);
            }

            return redirect()->route('monitoring', ['semester_id' => $selectedSemesterId])
                ->with('success', 'Proses sinkronisasi Google Drive & Sheet telah dimulai di background.');

        } catch (GoogleAuthException $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => 'Belum terhubung ke Google.'], 401);
            }
            return redirect()->route('monitoring', ['semester_id' => $selectedSemesterId])
                ->with('error', 'Belum terhubung ke Google. Silakan hubungkan akun Google Anda.');
        } catch (Exception $e) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }
            return redirect()->route('monitoring', ['semester_id' => $selectedSemesterId])
                ->with('error', 'Gagal memicu sinkronisasi: ' . $e->getMessage());
        }
    }

    /**
     * GET /monitoring/sync-status
     * Mengembalikan status progress sinkronisasi terbaru untuk polling AJAX.
     *
     * @param  Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncStatus(Request $request): \Illuminate\Http\JsonResponse
    {
        $selectedSemesterId = $request->query('semester_id', 'Semester 1');

        $syncLog = \App\Models\MonitoringSyncLog::where('semester', $selectedSemesterId)
            ->latest('id')
            ->first();

        if (! $syncLog) {
            return response()->json([
                'status'         => 'completed',
                'progress'       => 100,
                'processed'      => 0,
                'total'          => 0,
                'current_region' => null,
                'current_unit'   => null,
            ]);
        }

        $rawStatus = strtoupper($syncLog->status);
        $status = match ($rawStatus) {
            'RUNNING', 'PENDING' => 'running',
            'COMPLETED', 'SUCCESS' => 'completed',
            'FAILED' => 'failed',
            default => strtolower($rawStatus),
        };

        return response()->json([
            'status'         => $status,
            'progress'       => (int) ($status === 'completed' ? 100 : ($syncLog->progress ?? 0)),
            'processed'      => (int) ($syncLog->processed ?? 0),
            'total'          => (int) ($syncLog->total ?? 432),
            'current_region' => $syncLog->current_region,
            'current_unit'   => $syncLog->current_unit,
        ]);
    }

    // ─────────────────────────────────────────────────────────
    // EXCEL-FIRST PDF PREVIEW & EXPORT
    // ─────────────────────────────────────────────────────────

    /**
     * POST /monitoring/pdf/preview
     * Step 1: Copy Template -> Fill Cells -> Convert PDF -> Return temp_id & pdf_url (< 1.5s)
     */
    public function previewPdf(Request $request): JsonResponse
    {
        try {
            $semester = $request->input('semester', 'Semester 1');
            $footer   = $this->footerSettingsService->normalize($request->input('footer', []));

            $this->excelReportService->cleanupTempFiles();

            $result = $this->excelReportService->createReport($semester, $footer);
            $pdfUrl = route('monitoring.pdf.serve', ['filename' => $result['temp_id']]);

            return response()->json([
                'success' => true,
                'temp_id' => $result['temp_id'],
                'pdf_url' => $pdfUrl,
                'footer'  => $footer,
            ]);
        } catch (\Exception $e) {
            Log::error('[PDF Preview] Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal membuat preview PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /monitoring/pdf/update-preview
     * Step 2: Open report_{uuid}.xlsx -> Update 5 cell footer -> Save -> Re-convert PDF (< 800ms)
     */
    public function updatePreview(Request $request): JsonResponse
    {
        try {
            $semester    = $request->input('semester', 'Semester 1');
            $oldTempId   = $request->input('temp_id', '');
            $footer      = $this->footerSettingsService->normalize($request->input('footer', []));
            $saveDefault = $request->boolean('save_default', false);

            if ($saveDefault) {
                $this->footerSettingsService->save($footer);
            }

            $result = $this->excelReportService->updateFooter($oldTempId, $semester, $footer);
            $pdfUrl = route('monitoring.pdf.serve', ['filename' => $result['temp_id']]);

            return response()->json([
                'success' => true,
                'temp_id' => $result['temp_id'],
                'pdf_url' => $pdfUrl,
            ]);
        } catch (\Exception $e) {
            Log::error('[PDF Update Preview] Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal memperbarui preview: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /monitoring/pdf/serve/{filename}
     * Serve temporary PDF file ke browser (disajikan via iframe native Chromium).
     */
    public function servePdf(Request $request, string $filename): mixed
    {
        $pdfPath = $this->excelReportService->getPdfPath($filename);

        if (!$pdfPath) {
            abort(404, 'Preview PDF tidak ditemukan atau sudah kedaluwarsa.');
        }

        return response()->file($pdfPath, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview_monitoring.pdf"',
            'Cache-Control'       => 'no-cache, no-store',
        ]);
    }

    /**
     * POST /monitoring/pdf/generate
     * Stream/download file report_{uuid}.pdf yang sudah dibuat dari Excel.
     */
    public function generatePdf(Request $request): mixed
    {
        try {
            $semester    = $request->input('semester', 'Semester 1');
            $tempId      = $request->input('temp_id', '');
            $footer      = $this->footerSettingsService->normalize($request->input('footer', []));
            $saveDefault = $request->boolean('save_default', false);

            if ($saveDefault) {
                $this->footerSettingsService->save($footer);
            }

            $pdfPath = $this->excelReportService->getPdfPath($tempId);
            if (!$pdfPath) {
                $result  = $this->excelReportService->createReport($semester, $footer);
                $pdfPath = $result['pdf_path'];
            }

            $fileName = 'Monitoring_' . str_replace(' ', '_', $semester) . '_' . now()->format('Ymd_His') . '.pdf';

            return response()->download($pdfPath, $fileName, [
                'Content-Type' => 'application/pdf',
            ]);

        } catch (\Exception $e) {
            Log::error('[PDF Generate] Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghasilkan PDF: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /monitoring/excel/export
     * Stream/download file report_{uuid}.xlsx per semester.
     */
    public function exportExcel(Request $request): mixed
    {
        try {
            $semester    = $request->input('semester', 'Semester 1');
            $tempId      = $request->input('temp_id', '');
            $footer      = $this->footerSettingsService->normalize($request->input('footer', []));
            $saveDefault = $request->boolean('save_default', false);

            if ($saveDefault) {
                $this->footerSettingsService->save($footer);
            }

            $xlsxPath = null;
            if ($tempId && (bool) preg_match('/^report_[a-f0-9\-]{36}$/', $tempId)) {
                $candidate = storage_path('app/temp/' . $tempId . '.xlsx');
                if (file_exists($candidate)) {
                    $xlsxPath = $candidate;
                }
            }

            if (!$xlsxPath) {
                $result   = $this->excelReportService->createReport($semester, $footer);
                $xlsxPath = $result['xlsx_path'];
            }

            $fileName = 'Monitoring_' . str_replace(' ', '_', $semester) . '_' . now()->format('Ymd_His') . '.xlsx';

            return response()->download($xlsxPath, $fileName, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ]);

        } catch (\Exception $e) {
            Log::error('[Excel Export] Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengunduh Excel: ' . $e->getMessage(),
            ], 500);
        }
    }
}
