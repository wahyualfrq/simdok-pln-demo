<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Models\FolderGenerationLog;
use App\Models\MonitoringSyncLog;
use App\Services\CacheService;
use App\Services\FolderGeneratorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * DemoProgressSimulator
 *
 * DEMO: Progres sinkronisasi & generate folder berbasis WAKTU, tanpa worker, tanpa sleep(),
 * tanpa callback pasca-respons. Dipanggil dari listener Eloquent yang didaftarkan
 * AppServiceProvider (hanya saat config('demo.enabled')):
 *
 *   - MonitoringSyncLog::retrieved   → advanceSync()
 *   - FolderGenerationLog::retrieved → advanceGeneration()
 *   - FolderGenerationLog::created   → runGenerationNow()
 *
 * Controller tidak diubah: endpoint polling membaca model seperti biasa, dan model yang
 * dibaca sudah menunjukkan progres terbaru. Semua pembaruan DB memakai query builder
 * bersyarat (status RUNNING), sehingga idempoten dan aman dipanggil berulang; hanya satu
 * pemanggil yang "memenangkan" penyelesaian.
 */
class DemoProgressSimulator
{
    private const MONTH_NAMES = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /** Nama berkas bukti yang "ditemukan" sinkronisasi pada unit yang berubah menjadi hijau. */
    private const FOUND_EVIDENCE = 'Evidence_Diseminasi_001.jpg';

    /** Ukuran berkas bukti dummy (byte). */
    private const FOUND_EVIDENCE_SIZE = 131072;

    public function __construct(
        private readonly DemoDriveTree $tree,
        private readonly DemoDriveStore $store,
    ) {}

    // ─────────────────────────────────────────────────────────
    // SINKRONISASI
    // ─────────────────────────────────────────────────────────

    /**
     * Memajukan progres sync yang sedang RUNNING berdasarkan waktu berjalan.
     * Melewati log berstatus lain (PENDING/COMPLETED/FAILED).
     */
    public function advanceSync(MonitoringSyncLog $log): void
    {
        if (! config('demo.enabled') || strtoupper((string) $log->status) !== 'RUNNING' || $log->started_at === null) {
            return;
        }

        $semester = (string) $log->semester;
        $slots    = $this->semesterSlots($semester);
        if ($slots === []) {
            return;
        }

        $total    = count($slots);
        $duration = max(1, (int) config('demo.sync_duration_seconds'));
        $elapsed  = max(0, now()->getTimestamp() - $log->started_at->getTimestamp());

        if ($elapsed >= $duration) {
            $this->completeSync($log, $semester, $slots, $duration);

            return;
        }

        $fraction  = $elapsed / $duration;
        $processed = (int) floor($fraction * $total);
        $rows      = $this->unitRows($slots);
        $perRow    = max(1, intdiv($total, max(1, count($rows))));
        $row       = $rows[min(count($rows) - 1, intdiv($processed, $perRow))];

        $attributes = [
            'processed'      => $processed,
            'total'          => $total,
            'progress'       => max(1, min(99, (int) floor($fraction * 100))),
            'current_region' => $row['region'],
            'current_unit'   => $this->cleanUnitName($row['unit']),
        ];

        DB::table('monitoring_sync_logs')
            ->where('id', $log->id)
            ->where('status', 'RUNNING')
            ->update($attributes + ['updated_at' => now()]);

        $this->fill($log, $attributes);
    }

    /**
     * Menyelesaikan sync: tandai sukses, ubah sebagian kecil unit merah menjadi hijau
     * (konsisten dengan Drive & sheet palsu), lalu invalidasi cache lewat jalur asli.
     *
     * @param  list<array>  $slots
     */
    private function completeSync(MonitoringSyncLog $log, string $semester, array $slots, int $duration): void
    {
        $total    = count($slots);
        $finished = $log->started_at->copy()->addSeconds($duration)->toDateTimeString();

        $claimed = DB::table('monitoring_sync_logs')
            ->where('id', $log->id)
            ->where('status', 'RUNNING')
            ->update([
                'status'           => 'COMPLETED',
                'progress'         => 100,
                'processed'        => $total,
                'total'            => $total,
                'current_region'   => null,
                'current_unit'     => null,
                'finished_at'      => $finished,
                'duration_seconds' => (float) $duration,
                'updated_at'       => now(),
            ]);

        if ($claimed === 1) {
            $this->flipRedUnits($semester, $slots);

            $count = fn (string $status) => DB::table('monitoring_statuses')->where('semester', $semester)->where('status', $status)->count();

            DB::table('monitoring_sync_logs')->where('id', $log->id)->update([
                'folders_scanned'  => $this->countFolders($slots),
                'files_scanned'    => array_sum(array_column($slots, 'fileCount')),
                'complete_count'   => $count('COMPLETE'),
                'incomplete_count' => $count('INCOMPLETE'),
                'mismatch_count'   => $count('MISMATCH'),
            ]);

            // Jalur asli: dashboard & monitoring membaca ulang dari SQLite.
            app(CacheService::class)->invalidateAll();

            Log::info('DemoProgressSimulator: sync demo selesai', ['semester' => $semester, 'logId' => $log->id]);
        }

        $fresh = DB::table('monitoring_sync_logs')->where('id', $log->id)->first();
        if ($fresh !== null) {
            $log->setRawAttributes((array) $fresh, true);
        }
    }

    /**
     * Deterministik: N unit INCOMPLETE terakhir (id terbesar = bulan terbaru) pada semester ini
     * dianggap "baru ditemukan buktinya": status → COMPLETE, sheet 'v', satu file bukti dibuat di
     * overlay Drive pengunjung, dan checklist sheet palsu ikut tercentang.
     *
     * @param  list<array>  $slots
     */
    private function flipRedUnits(string $semester, array $slots): void
    {
        $limit = max(0, (int) config('demo.sync_flip_count'));
        if ($limit === 0) {
            return;
        }

        $bySlot = [];
        foreach ($slots as $slot) {
            $bySlot[self::MONTH_NAMES[(int) substr($slot['period'], 5, 2)] . '|' . strtoupper($slot['region']) . '|' . $slot['unit']] = $slot;
        }

        $rows = DB::table('monitoring_statuses')
            ->where('semester', $semester)
            ->where('status', 'INCOMPLETE')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $now = now()->toDateTimeString();

        foreach ($rows as $row) {
            $slot = $bySlot[$row->bulan . '|' . $row->region . '|' . $row->unit_original] ?? null;
            $file = ($slot !== null && $slot['mode'] === 'file' ? $slot['unit'] . ' - ' : '') . self::FOUND_EVIDENCE;

            DB::table('monitoring_statuses')->where('id', $row->id)->update([
                'status'               => 'COMPLETE',
                'sheet_status'         => 'v',
                'drive_status'         => 'COMPLETE',
                'evidence_count'       => 1,
                'sample_evidence_file' => $file,
                'last_sync'            => $now,
                'updated_at'           => $now,
            ]);

            if (! empty($row->folder_id)) {
                DB::table('monitoring_folder_cache')->where('drive_folder_id', $row->folder_id)->increment('image_file_count');
            }

            if ($slot !== null) {
                $this->store->create($file, $slot['folderId'], false, 'image/jpeg', self::FOUND_EVIDENCE_SIZE);
                $this->store->setChecklist([$slot['key'] => 'v']);
            }
        }
    }

    // ─────────────────────────────────────────────────────────
    // GENERATE FOLDER
    // ─────────────────────────────────────────────────────────

    /**
     * Saat controller membuat log generate (PENDING), pembuatan folder dijalankan SINKRON di dalam
     * request memakai FolderGeneratorService asli di atas Drive palsu (folder masuk overlay), lalu
     * log dikembalikan ke RUNNING agar UI menampilkan progres berbasis waktu. Hasil akhir
     * (new_count / existing_count / total_folders) sudah tersimpan di log dan tampil saat selesai.
     */
    public function runGenerationNow(FolderGenerationLog $log): void
    {
        if (! config('demo.enabled') || strtoupper((string) $log->status) !== 'PENDING' || empty($log->folder_id)) {
            return;
        }

        try {
            app(FolderGeneratorService::class)->generateContextAwareFolders((string) $log->folder_id, (int) $log->id);
        } catch (\Throwable $e) {
            // Generator sudah menandai log FAILED; pesan tampil lewat polling status.
            Log::warning('DemoProgressSimulator: generate demo gagal: ' . $e->getMessage(), ['logId' => $log->id]);

            return;
        }

        DB::table('folder_generation_logs')
            ->where('id', $log->id)
            ->where('status', 'COMPLETED')
            ->update([
                'status'           => 'RUNNING',
                'progress'         => 5,
                'processed_folders' => 0,
                'duration_seconds' => null,
                'current_folder'   => '5% Mendeteksi posisi folder (' . ($log->context_level ?? '') . ')',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
    }

    /**
     * Memajukan progres generate yang sedang RUNNING berdasarkan waktu sejak log dibuat.
     */
    public function advanceGeneration(FolderGenerationLog $log): void
    {
        if (! config('demo.enabled') || strtoupper((string) $log->status) !== 'RUNNING' || $log->created_at === null) {
            return;
        }

        $duration = max(1, (int) config('demo.generate_duration_seconds'));
        $elapsed  = max(0, now()->getTimestamp() - $log->created_at->getTimestamp());
        $total    = (int) $log->total_folders;

        if ($elapsed >= $duration) {
            DB::table('folder_generation_logs')
                ->where('id', $log->id)
                ->where('status', 'RUNNING')
                ->update([
                    'status'            => 'COMPLETED',
                    'progress'          => 100,
                    'processed_folders' => $total,
                    'duration_seconds'  => (float) $duration,
                    'current_folder'    => 'Generate selesai',
                    'updated_at'        => now(),
                ]);

            $fresh = DB::table('folder_generation_logs')->where('id', $log->id)->first();
            if ($fresh !== null) {
                $log->setRawAttributes((array) $fresh, true);
            }

            return;
        }

        $fraction   = $elapsed / $duration;
        $progress   = max(5, min(99, (int) floor($fraction * 100)));
        $processed  = (int) floor($fraction * $total);
        $attributes = [
            'progress'          => $progress,
            'processed_folders' => $processed,
            'current_folder'    => sprintf('%d%% Membuat struktur folder (%d / %d)', $progress, $processed, $total),
        ];

        DB::table('folder_generation_logs')
            ->where('id', $log->id)
            ->where('status', 'RUNNING')
            ->update($attributes + ['updated_at' => now()]);

        $this->fill($log, $attributes);
    }

    // ─────────────────────────────────────────────────────────
    // HELPER
    // ─────────────────────────────────────────────────────────

    /**
     * @return list<array> Slot pohon dummy untuk satu semester (Sem 1 = Des–Mei, Sem 2 = Jun–Nov).
     */
    private function semesterSlots(string $semester): array
    {
        return array_values(array_filter($this->tree->slots(), function (array $slot) use ($semester) {
            $month = (int) substr($slot['period'], 5, 2);

            return (($month === 12 || $month <= 5) ? 'Semester 1' : 'Semester 2') === $semester;
        }));
    }

    /**
     * Daftar (region, unit) unik berurutan, sesuai urutan baris sheet.
     *
     * @param  list<array>  $slots
     * @return list<array{region: string, unit: string}>
     */
    private function unitRows(array $slots): array
    {
        $rows = [];
        foreach ($slots as $slot) {
            $rows[$slot['region'] . '|' . $slot['unit']] ??= ['region' => $slot['region'], 'unit' => $slot['unit']];
        }

        return array_values($rows);
    }

    /**
     * @param  list<array>  $slots
     */
    private function countFolders(array $slots): int
    {
        return count(array_unique(array_merge(
            array_column($slots, 'monthId'),
            array_column($slots, 'regionId'),
            array_column($slots, 'folderId'),
        )));
    }

    private function cleanUnitName(string $name): string
    {
        return preg_replace('/^[a-z0-9]+\.\s*/i', '', trim($name));
    }

    /**
     * Menyelaraskan instance model yang sedang dibaca dengan nilai terbaru (tanpa memicu event).
     *
     * @param  array<string, mixed>  $attributes
     */
    private function fill(\Illuminate\Database\Eloquent\Model $model, array $attributes): void
    {
        $model->setRawAttributes(array_merge($model->getAttributes(), $attributes), true);
    }
}
