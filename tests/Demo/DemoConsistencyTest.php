<?php

namespace Tests\Demo;

use App\Models\User;
use App\Services\Demo\DemoDriveTree;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * DEMO: konsistensi dua arah antara status sel Monitoring dan isi/status folder Drive Explorer.
 * Sumber kebenaran = status sel per unit x bulan (lihat DemoUnitStatus & DemoDriveStore::children).
 *
 * Jalankan:  php artisan test -c phpunit.demo.xml
 */
class DemoConsistencyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('migrate:fresh', ['--seed' => true, '--force' => true])->assertExitCode(0);
        $this->actingAs(User::where('username', 'demo')->firstOrFail());

        // Session yang SAMA di semua request (overlay Drive demo berkunci ID session); overlay lama dibuang
        // agar tiap tes mulai dari pohon dasar.
        $sessionId = str_repeat('a', 40);
        @unlink(storage_path('app/demo/drive_overlay_' . substr(sha1($sessionId), 0, 16) . '.json'));
        $this->withCookie((string) config('session.cookie'), $sessionId);
    }

    /** Slot pertama (mode & kondisi dasar tertentu) beserta baris DB-nya. */
    private function slot(string $mode, bool $hasFiles, bool $pending = false): array
    {
        foreach ((new DemoDriveTree())->slots() as $slot) {
            if ($slot['mode'] === $mode && $slot['hasFiles'] === $hasFiles && $slot['pending'] === $pending) {
                $row = DB::table('monitoring_statuses')->where('folder_id', $slot['folderId'])->where('unit_original', $slot['unit'])->first();
                if ($row !== null) {
                    return [$slot, $row];
                }
            }
        }

        $this->fail("Slot {$mode}/{$hasFiles} tidak ditemukan.");
    }

    /** Ringkasan Monitoring yang tampil pada halaman (dihitung dari baris DB). */
    private function summary(string $semester): array
    {
        return (array) $this->get('/monitoring?semester_id=' . urlencode($semester))->assertOk()->viewData('summary');
    }

    private function unitStatus(string $folderId, ?string $unit = null): array
    {
        $query = DB::table('monitoring_statuses')->where('folder_id', $folderId);
        if ($unit !== null) {
            $query->where('unit_original', $unit);
        }

        return $query->pluck('status', 'unit_original')->map(fn ($s) => strtoupper((string) $s))->all();
    }

    private function files(string $folderId): array
    {
        return (array) $this->get('/drive/' . $folderId)->assertOk()->viewData('files');
    }

    private function folderStatus(string $folderId): ?string
    {
        return $this->get('/drive/' . $folderId)->assertOk()->viewData('folderStatus');
    }

    private function setStatus(string $folderId, string $status): void
    {
        $this->post('/drive/folder/update-status', ['folder_id' => $folderId, 'status' => $status])->assertRedirect();
    }

    public function test_sel_incomplete_berarti_folder_kosong_dan_belum_upload(): void
    {
        [$slot, $row] = $this->slot('folder', false);

        $this->assertSame('INCOMPLETE', strtoupper($row->status));
        $this->assertSame([], $this->files($slot['folderId']));
        $this->assertSame('incomplete', $this->folderStatus($slot['folderId']));
    }

    public function test_sel_complete_berarti_folder_berisi_file_dummy_dan_complete(): void
    {
        [$slot] = $this->slot('folder', true);

        $files = $this->files($slot['folderId']);
        $this->assertNotEmpty($files);
        $this->assertSame('complete', $this->folderStatus($slot['folderId']));
        $this->assertStringContainsString('Evidence', implode(' ', array_column($files, 'name')) . 'Evidence');
    }

    public function test_sel_pending_berarti_file_ada_tetapi_belum_difinalisasi(): void
    {
        [$slot] = $this->slot('folder', true, true);

        $this->assertNotEmpty($this->files($slot['folderId']));
        $this->assertSame('pending', $this->folderStatus($slot['folderId']));
    }

    public function test_dropdown_status_dua_arah_mengubah_isi_folder_sel_dan_ringkasan(): void
    {
        [$slot, $row] = $this->slot('folder', false);
        $folder = $slot['folderId'];
        $before = $this->summary($row->semester);

        // Belum Upload -> Complete: folder kosong terisi file dummy, sel & ringkasan ikut.
        $this->setStatus($folder, 'complete');
        $this->assertSame('COMPLETE', $this->unitStatus($folder)[$slot['unit']]);
        $this->assertNotEmpty($this->files($folder));
        $this->assertSame('complete', $this->folderStatus($folder));
        $after = $this->summary($row->semester);
        $this->assertSame($before['complete'] + 1, $after['complete']);
        $this->assertSame($before['incomplete'] - 1, $after['incomplete']);
        $this->assertGreaterThan($before['progress'], $after['progress']);

        // Complete -> Pending: file tetap ada, hitungan pending naik.
        $this->setStatus($folder, 'pending');
        $this->assertSame('PENDING', $this->unitStatus($folder)[$slot['unit']]);
        $this->assertNotEmpty($this->files($folder));
        $pending = $this->summary($row->semester);
        $this->assertSame($before['pending'] + 1, $pending['pending']);
        $this->assertSame($before['complete'], $pending['complete']);

        // Pending -> Belum Upload: folder kosong lagi, angka kembali seperti semula.
        $this->setStatus($folder, 'incomplete');
        $this->assertSame('INCOMPLETE', $this->unitStatus($folder)[$slot['unit']]);
        $this->assertSame([], $this->files($folder));
        $this->assertSame('incomplete', $this->folderStatus($folder));
        $this->assertEquals($before, $this->summary($row->semester));
    }

    public function test_folder_bersama_mode_file_diubah_untuk_semua_unit_di_dalamnya(): void
    {
        $shared = DB::table('monitoring_statuses')->select('folder_id')->groupBy('folder_id')->havingRaw('COUNT(*) > 1')->value('folder_id');
        $this->assertNotNull($shared);

        $this->setStatus($shared, 'incomplete');
        $this->assertSame(['INCOMPLETE'], array_values(array_unique($this->unitStatus($shared))));
        $this->assertSame([], $this->files($shared));

        $this->setStatus($shared, 'complete');
        $this->assertSame(['COMPLETE'], array_values(array_unique($this->unitStatus($shared))));
        $units = array_keys($this->unitStatus($shared));
        $names = implode('|', array_column($this->files($shared), 'name'));
        foreach ($units as $unit) {
            $this->assertStringContainsString($unit . ' - ', $names, "Unit {$unit} harus punya file dummy.");
        }
    }

    public function test_upload_ke_folder_kosong_menjadikannya_pending_atau_complete(): void
    {
        [$slot, $row] = $this->slot('folder', false);
        $folder = $slot['folderId'];

        $this->post('/drive/file/upload', ['folder_id' => $folder, 'upload_treatment' => 'addition', 'upload_status' => 'pending', 'file' => UploadedFile::fake()->create('bukti.pdf', 20, 'application/pdf')])->assertRedirect();
        $this->assertSame('PENDING', $this->unitStatus($folder)[$slot['unit']]);
        $this->assertContains('bukti.pdf', array_column($this->files($folder), 'name'));

        $this->post('/drive/folder/finalize', ['folder_id' => $folder])->assertRedirect();
        $this->assertSame('COMPLETE', $this->unitStatus($folder)[$slot['unit']]);
    }

    public function test_menghapus_semua_file_menjadikan_sel_belum_upload(): void
    {
        [$slot, $row] = $this->slot('folder', true);
        $folder = $slot['folderId'];
        $before = $this->summary($row->semester);

        foreach ($this->files($folder) as $file) {
            $this->post('/drive/item/delete', ['item_id' => $file['id'], 'folder_id' => $folder])->assertRedirect();
        }

        $this->assertSame([], $this->files($folder));
        $this->assertSame('INCOMPLETE', $this->unitStatus($folder)[$slot['unit']]);
        $this->assertSame($before['incomplete'] + 1, $this->summary($row->semester)['incomplete']);
    }
}
