<?php

namespace App\Providers;

// DEMO: Fake service untuk versi demo (di-bind hanya saat config('demo.enabled'))
use App\Models\FolderGenerationLog;
use App\Models\MonitoringSyncLog;
use App\Services\Demo\DemoProgressSimulator;
use App\Services\Demo\FakeDriveMonitoringSyncService;
use App\Services\Demo\FakeFolderGeneratorService;
use App\Services\Demo\FakeGoogleDriveService;
use App\Services\Demo\FakeGoogleOAuthService;
use App\Services\Demo\FakeGoogleSheetService;
use App\Services\Google\GoogleDriveService;
use App\Services\Google\GoogleOAuthService;
use App\Services\DriveMonitoringSyncService;
use App\Services\FolderGeneratorService;
use App\Services\Google\GoogleSheetService;
use App\Services\UnitAliasService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // UnitAliasService: singleton agar file alias hanya dibaca SATU KALI per lifecycle
        $this->app->singleton(UnitAliasService::class, function () {
            return new UnitAliasService(base_path('unit_aliases_verified.txt'));
        });

        // DEMO: Google disimulasikan. Class Fake meng-extends class asli agar type-hint
        // di constructor controller/service tetap valid (rules/14 §2). GoogleApiService
        // otomatis ikut karena hanya mendelegasi ke GoogleOAuthService.
        if (config('demo.enabled')) {
            $this->app->bind(GoogleOAuthService::class, FakeGoogleOAuthService::class);
            $this->app->bind(GoogleDriveService::class, FakeGoogleDriveService::class);
            $this->app->bind(GoogleSheetService::class, FakeGoogleSheetService::class);
            $this->app->bind(DriveMonitoringSyncService::class, FakeDriveMonitoringSyncService::class);
            $this->app->bind(FolderGeneratorService::class, FakeFolderGeneratorService::class);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        // DEMO: tanpa worker/tabel jobs: queue dipaksa 'sync', dan progres sync/generate dihitung
        // berdasarkan waktu lewat listener Eloquent (controller tidak diubah). Lihat DemoProgressSimulator.
        if (config('demo.enabled')) {
            config(['queue.default' => 'sync']);

            MonitoringSyncLog::retrieved(fn (MonitoringSyncLog $log) => app(DemoProgressSimulator::class)->advanceSync($log));
            FolderGenerationLog::retrieved(fn (FolderGenerationLog $log) => app(DemoProgressSimulator::class)->advanceGeneration($log));
            FolderGenerationLog::created(fn (FolderGenerationLog $log) => app(DemoProgressSimulator::class)->runGenerationNow($log));
        }
    }
}
