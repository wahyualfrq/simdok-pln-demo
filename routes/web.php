<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DriveController;
use App\Http\Controllers\GoogleOAuthController;
use App\Http\Controllers\MonitoringController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes — SIMDOK
|--------------------------------------------------------------------------
*/

// Default redirect ke dashboard
Route::get('/', fn () => redirect()->route('dashboard'));

// Guest Routes (Hanya bisa diakses jika belum login)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', fn () => redirect()->route('login'))->name('register');
});

// Protected Routes (Wajib Login)
Route::middleware('auth')->group(function () {
    
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // Drive Explorer
    Route::prefix('drive')->name('drive.')->group(function () {
        Route::get('/', [DriveController::class, 'index'])
            ->name('index');

        Route::get('/pending-finalization', [DriveController::class, 'pendingFinalization'])
            ->name('pending');

        Route::get('/navigate', [DriveController::class, 'navigateToFolder'])
            ->name('navigate');

        Route::post('/folder/finalize', [DriveController::class, 'finalizeFolder'])
            ->name('folder.finalize');

        Route::post('/folder/cancel-finalize', [DriveController::class, 'cancelFinalizeFolder'])
            ->name('folder.cancel-finalize');

        Route::post('/folder/update-status', [DriveController::class, 'updateFolderStatus'])
            ->name('folder.update-status');

        Route::post('/folder/create', [DriveController::class, 'createFolder'])
            ->name('folder.create');

        Route::post('/file/upload', [DriveController::class, 'uploadFile'])
            ->name('file.upload');

        Route::get('/file/{fileId}/download/{fileName}', [DriveController::class, 'downloadFile'])
            ->name('file.download')
            ->where('fileId', '[a-zA-Z0-9_-]+');

        Route::get('/folder/generate-preview', [DriveController::class, 'generatePreview'])
            ->name('folder.generate-preview');

        Route::get('/folder/recommendations', [DriveController::class, 'getRecommendations'])
            ->name('folder.recommendations');

        Route::post('/folder/generate', [DriveController::class, 'generateFolder'])
            ->name('folder.generate');

        Route::get('/folder/generate-status', [DriveController::class, 'generateFolderStatus'])
            ->name('folder.generate-status');

        Route::post('/item/rename', [DriveController::class, 'renameItem'])
            ->name('item.rename');

        Route::post('/item/delete', [DriveController::class, 'deleteItem'])
            ->name('item.delete');

        // DEMO: halaman placeholder preview file (pengganti drive.google.com). Hanya terdaftar saat demo.
        if (config('demo.enabled')) {
            Route::get('/file/{fileId}/preview-demo', \App\Http\Controllers\Demo\DemoPreviewController::class)
                ->name('file.preview-demo')
                ->where('fileId', '[a-zA-Z0-9_-]+');
        }

        Route::get('/{folderId}', [DriveController::class, 'show'])
            ->name('show')
            ->where('folderId', '[a-zA-Z0-9_-]+');
    });

    // Monitoring
    Route::get('/monitoring', [MonitoringController::class, 'index'])
        ->name('monitoring');
    Route::get('/monitoring/sync-status', [MonitoringController::class, 'syncStatus'])
        ->name('monitoring.sync_status');
    Route::get('/monitoring/sync', [MonitoringController::class, 'sync'])
        ->name('monitoring.sync');
    Route::get('/monitoring/full-sync', [MonitoringController::class, 'fullSync'])
        ->name('monitoring.full_sync');

    // Excel-First Monitoring PDF Preview & Export
    Route::post('/monitoring/pdf/preview', [MonitoringController::class, 'previewPdf'])
        ->name('monitoring.pdf.preview');
    Route::post('/monitoring/pdf/update-preview', [MonitoringController::class, 'updatePreview'])
        ->name('monitoring.pdf.update');
    Route::get('/monitoring/pdf/serve/{filename}', [MonitoringController::class, 'servePdf'])
        ->name('monitoring.pdf.serve')
        ->where('filename', 'report_[a-f0-9\-]{36}');
    Route::post('/monitoring/pdf/generate', [MonitoringController::class, 'generatePdf'])
        ->name('monitoring.pdf.generate');
    Route::post('/monitoring/excel/export', [MonitoringController::class, 'exportExcel'])
        ->name('monitoring.excel.export');


    // Google OAuth 2.0
    Route::prefix('auth/google')->name('auth.google.')->group(function () {
        Route::get('/', [GoogleOAuthController::class, 'status'])
            ->name('status');

        Route::get('/connect', [GoogleOAuthController::class, 'redirect'])
            ->name('connect');

        Route::get('/callback', [GoogleOAuthController::class, 'callback'])
            ->name('callback');

        Route::post('/disconnect', [GoogleOAuthController::class, 'disconnect'])
            ->name('disconnect');

        Route::get('/status.json', [GoogleOAuthController::class, 'statusJson'])
            ->name('status.json');

        Route::post('/root/save', [GoogleOAuthController::class, 'saveRoot'])
            ->name('root.save');

        Route::post('/root/refresh', [GoogleOAuthController::class, 'refreshRoot'])
            ->name('root.refresh');

        Route::get('/folders', [GoogleOAuthController::class, 'listFoldersJson'])
            ->name('folders');
    });
});

