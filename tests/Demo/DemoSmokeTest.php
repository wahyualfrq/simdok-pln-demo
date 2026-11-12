<?php

namespace Tests\Demo;

use App\Models\User;
use App\Services\Demo\DemoDriveTree;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DEMO: smoke test alur utama versi demo (DEMO_MODE=true, Google disimulasikan, data dummy).
 *
 * Alur: login terisi → dashboard → drive → monitoring → sync (progres berbasis waktu) →
 * generate folder → koneksi Google simulasi → export Excel/PDF → logout.
 *
 * Jalankan:  php artisan test -c phpunit.demo.xml
 */
class DemoSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Database demo baru di memori: migrasi + seeder (AdminSeeder + DemoSeeder via DatabaseSeeder).
        $this->artisan('migrate:fresh', ['--seed' => true, '--force' => true])->assertExitCode(0);
    }

    private function demoUser(): User
    {
        return User::where('username', 'demo')->firstOrFail();
    }

    /** Id node pertama bertipe tertentu pada pohon Drive dummy (deterministik). */
    private function firstNodeId(string $kind): string
    {
        foreach ((new DemoDriveTree())->nodes() as $id => $node) {
            if (($node['kind'] ?? null) === $kind) {
                return (string) $id;
            }
        }

        $this->fail("Node bertipe {$kind} tidak ditemukan pada DemoDriveTree.");
    }

    public function test_login_terisi_otomatis_lalu_masuk_dashboard(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('value="demo"', false)
            ->assertSee('value="demo12345"', false);

        $this->post('/login', ['username' => 'demo', 'password' => 'salah'])
            ->assertSessionHasErrors();

        $this->post('/login', ['username' => 'demo', 'password' => 'demo12345'])
            ->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_dashboard_drive_monitoring_dan_pending_tampil(): void
    {
        $this->actingAs($this->demoUser());

        $this->get('/dashboard')->assertOk()->assertSee('Versi Demo');
        $this->get('/drive')->assertOk();
        $this->get('/drive/' . config('demo.root_folder_id'))->assertOk();
        $this->get('/drive/' . $this->firstNodeId('month'))->assertOk();
        $this->get('/drive/' . $this->firstNodeId('region'))->assertOk();
        $this->get('/drive/pending-finalization')->assertOk();

        foreach (['Semester 1', 'Semester 2'] as $semester) {
            $this->get('/monitoring?semester_id=' . urlencode($semester))->assertOk();
        }

        $this->assertGreaterThan(0, DB::table('monitoring_statuses')->where('status', 'COMPLETE')->count());
        $this->assertGreaterThan(0, DB::table('monitoring_statuses')->where('status', 'PENDING')->count());
    }

    public function test_full_sync_progres_berbasis_waktu_dan_status_unit_berubah(): void
    {
        $this->actingAs($this->demoUser());
        $semester = 'Semester 2';
        $complete = fn () => DB::table('monitoring_statuses')->where('semester', $semester)->where('status', 'COMPLETE')->count();
        $before = $complete();

        $this->getJson('/monitoring/full-sync?semester_id=' . urlencode($semester))->assertOk()->assertJson(['success' => true]);

        $this->getJson('/monitoring/sync-status?semester_id=' . urlencode($semester))
            ->assertOk()
            ->assertJsonStructure(['status', 'progress']);

        // Maju waktu: sinkronisasi dianggap dimulai 60 detik lalu (> sync_duration_seconds).
        DB::table('monitoring_sync_logs')->where('status', 'RUNNING')->update(['started_at' => now()->subSeconds(60)]);

        $this->getJson('/monitoring/sync-status?semester_id=' . urlencode($semester))->assertOk()->assertJson(['progress' => 100]);

        $after = $complete();
        $this->assertGreaterThanOrEqual($before, $after);

        // Idempoten: polling berulang tidak mengubah apa pun lagi.
        $this->getJson('/monitoring/sync-status?semester_id=' . urlencode($semester))->assertOk();
        $this->assertSame($after, $complete());
    }

    public function test_generate_folder_berjalan_sinkron(): void
    {
        $this->actingAs($this->demoUser());
        $monthId = $this->firstNodeId('month');

        $this->getJson('/drive/folder/generate-preview?folder_id=' . $monthId)
            ->assertOk()
            ->assertJsonStructure(['can_generate']);

        $response = $this->postJson('/drive/folder/generate', ['folder_id' => $monthId]);
        $this->assertContains($response->getStatusCode(), [200, 400]);

        if ($response->getStatusCode() === 200) {
            $logId = $response->json('log_id');
            DB::table('folder_generation_logs')->where('id', $logId)->update(['created_at' => now()->subSeconds(60), 'updated_at' => now()->subSeconds(60)]);
            $this->getJson('/drive/folder/generate-status?log_id=' . $logId)->assertOk()->assertJsonStructure(['status', 'progress']);
        }
    }

    public function test_koneksi_google_simulasi(): void
    {
        $this->actingAs($this->demoUser());

        $this->get('/auth/google')->assertOk();
        $this->getJson('/auth/google/status.json')->assertOk()->assertJson(['connected' => true]);

        $this->post('/auth/google/disconnect')->assertRedirect();
        $this->getJson('/auth/google/status.json')->assertJson(['connected' => false]);

        $this->get('/auth/google/connect')->assertRedirect();
    }

    public function test_buat_folder_pada_overlay_drive_dan_pratinjau_demo(): void
    {
        $this->actingAs($this->demoUser());
        $monthId = $this->firstNodeId('month');
        $fileId = $this->firstNodeId('file');

        $this->post('/drive/folder/create', ['folder_id' => $monthId, 'folder_name' => 'Folder Uji Demo'])->assertRedirect();
        $this->get('/drive/' . $monthId)->assertOk()->assertSee('Folder Uji Demo');

        $this->get('/drive/file/' . $fileId . '/preview-demo')->assertOk();
        $this->get('/drive/file/' . $fileId . '/download/uji.pdf')->assertOk();
    }

    public function test_export_excel_dan_pdf_tanpa_libreoffice(): void
    {
        config(['demo.pdf.fake_delay_ms' => 0]); // jeda animasi tidak diperlukan pada tes
        $this->actingAs($this->demoUser());
        $footer = ['disusun_oleh' => 'Nama Penyusun (Demo)', 'mengetahui' => 'Nama Pejabat (Demo)'];

        $excel = $this->post('/monitoring/excel/export', ['semester' => 'Semester 1', 'footer' => $footer])->assertOk();
        $this->assertStringStartsWith('PK', (string) file_get_contents($excel->baseResponse->getFile()->getPathname()));

        $preview = $this->postJson('/monitoring/pdf/preview', ['semester' => 'Semester 2', 'footer' => $footer])
            ->assertOk()
            ->assertJson(['success' => true]);

        $pdf = $this->get('/monitoring/pdf/serve/' . $preview->json('temp_id'))->assertOk();
        $bytes = (string) file_get_contents($pdf->baseResponse->getFile()->getPathname());
        $this->assertStringStartsWith('%PDF', $bytes);

        // PDF statis: isi = berkas contoh semester yang sesuai, dan dompdf tidak pernah dimuat.
        $this->assertSame(file_get_contents(base_path('resources/demo/pdf/monitoring_semester_2.pdf')), $bytes);
        $this->assertFalse(class_exists(\Dompdf\Dompdf::class, false));
    }

    public function test_logout_kembali_ke_login(): void
    {
        $this->actingAs($this->demoUser());

        $this->post('/logout')->assertRedirect();
        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect(route('login'));
    }
}
