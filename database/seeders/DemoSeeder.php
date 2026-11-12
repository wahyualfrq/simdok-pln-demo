<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Services\Demo\DemoDriveTree;
use App\Services\FooterSettingsService;
use App\Services\UnitNameNormalizerService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * DemoSeeder
 *
 * DEMO: Mengisi database demo secara DETERMINISTIK dan diturunkan dari pohon Drive dummy
 * (DemoDriveTree::slots()) — satu sumber yang sama dengan Drive Explorer & sheet palsu —
 * sehingga badge Monitoring konsisten dengan isi folder:
 *   hijau (COMPLETE)   = unit berisi file & sudah final (checklist 'v')
 *   kuning (PENDING)   = unit berisi file tetapi belum difinalisasi
 *   merah (INCOMPLETE) = folder unit kosong
 *
 * Tabel yang diisi: monitoring_statuses, monitoring_folder_cache, monitoring_folder_registry,
 * monitoring_sync_logs, folder_generation_logs, settings (footer laporan dummy).
 * Akun pengguna dibuat oleh AdminSeeder (env ADMIN_*). Tidak ada data nyata, tidak membaca
 * database.sqlite / data/.
 *
 * Dipanggil dari DatabaseSeeder hanya saat config('demo.enabled').
 */
class DemoSeeder extends Seeder
{
    /** Nama bulan standar menurut nomor bulan (sama dengan SEMESTER_MONTHS di service). */
    private const MONTH_NAMES = [
        1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April', 5 => 'Mei', 6 => 'Juni',
        7 => 'Juli', 8 => 'Agustus', 9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
    ];

    /** Riwayat sync (waktu mulai) per semester; yang terakhir = sync terbaru. Durasi detik berurutan. */
    private const SYNC_HISTORY = [
        'Semester 1' => ['2026-10-26 08:40:12', '2026-11-02 08:35:41', '2026-11-05 09:10:07'],
        'Semester 2' => ['2026-10-27 13:05:33', '2026-11-03 13:12:18', '2026-11-05 09:24:52'],
    ];

    private const SYNC_DURATIONS = [11.84, 12.37, 12.05];

    /** Pada sync lebih lama, sekian unit COMPLETE lebih sedikit (jadi INCOMPLETE) — agar riwayat tampak berkembang. */
    private const SYNC_COMPLETE_DELTA = [6, 3, 0];

    public function run(): void
    {
        $tree    = new DemoDriveTree();
        $slots   = $tree->slots();
        $lastSyncBySemester = [];
        foreach (self::SYNC_HISTORY as $semester => $times) {
            $lastSyncBySemester[$semester] = Carbon::parse($times[array_key_last($times)])
                ->addSeconds((int) round(self::SYNC_DURATIONS[array_key_last(self::SYNC_DURATIONS)]));
        }

        $this->clearDemoTables();
        $this->seedStatuses($slots, $lastSyncBySemester);
        $this->seedFolderCache($tree, $slots, $lastSyncBySemester);
        $this->seedRegistry($slots, $lastSyncBySemester);
        $this->seedSyncLogs($tree, $slots);
        $this->seedGenerationLogs($slots);

        // Footer laporan dummy lewat service yang sama dengan aplikasi (key 'pdf_footer_default').
        app(FooterSettingsService::class)->save((array) config('demo.footer'));
    }

    private function clearDemoTables(): void
    {
        foreach (['monitoring_statuses', 'monitoring_folder_cache', 'monitoring_folder_registry', 'monitoring_sync_logs', 'folder_generation_logs'] as $table) {
            DB::table($table)->delete();
        }
    }

    /**
     * @param  list<array>  $slots
     * @param  array<string, Carbon>  $lastSync
     */
    private function seedStatuses(array $slots, array $lastSync): void
    {
        $rows = [];

        foreach ($slots as $slot) {
            $semester = $this->semesterOf($slot['period']);
            $clean    = $this->cleanUnitName($slot['unit']);
            $status   = $this->statusOf($slot);
            $stamp    = $lastSync[$semester]->toDateTimeString();

            $rows[] = [
                'semester'             => $semester,
                'bulan'                => $this->monthNameOf($slot['period']),
                'region'               => strtoupper($slot['region']),
                'unit'                 => strtoupper($clean),
                'sub_unit'             => null,
                'unit_original'        => $slot['unit'],
                'sheet_status'         => $status === 'COMPLETE' ? 'v' : '',
                'drive_status'         => $slot['hasFiles'] ? 'COMPLETE' : 'INCOMPLETE',
                'status'               => $status,
                'evidence_count'       => $slot['fileCount'],
                'sample_evidence_file' => $slot['sample'],
                'folder_id'            => $slot['folderId'],
                'folder_name'          => strtolower($clean),
                'folder_path'          => $slot['region'] . ' / ' . $slot['unit'],
                'last_sync'            => $stamp,
                'created_at'           => $stamp,
                'updated_at'           => $stamp,
            ];
        }

        $this->insertChunked('monitoring_statuses', $rows);
    }

    /**
     * Satu baris per folder leaf: folder unit (mode folder) atau folder region (mode file).
     *
     * @param  list<array>  $slots
     * @param  array<string, Carbon>  $lastSync
     */
    private function seedFolderCache(DemoDriveTree $tree, array $slots, array $lastSync): void
    {
        $leaves = [];

        foreach ($slots as $slot) {
            $id = $slot['folderId'];
            if (! isset($leaves[$id])) {
                $leaves[$id] = [
                    'slot'  => $slot,
                    'files' => 0,
                    'name'  => $slot['mode'] === 'folder' ? strtolower($this->cleanUnitName($slot['unit'])) : strtolower($slot['region']),
                ];
            }
            $leaves[$id]['files'] += $slot['fileCount'];
        }

        $rows = [];
        foreach ($leaves as $id => $leaf) {
            $slot  = $leaf['slot'];
            $stamp = $lastSync[$this->semesterOf($slot['period'])]->toDateTimeString();

            $rows[] = [
                'drive_folder_id'   => $id,
                'month_folder_id'   => $slot['monthId'],
                'month_period'      => $slot['period'],
                'region_name'       => strtolower($slot['region']),
                'unit_name'         => $leaf['name'],
                'image_file_count'  => $leaf['files'],
                'is_leaf'           => 1,
                'last_scanned_at'   => $stamp,
                'drive_modified_at' => Carbon::parse($tree->find($id)['modified'])->toDateTimeString(),
                'created_at'        => $stamp,
                'updated_at'        => $stamp,
            ];
        }

        $this->insertChunked('monitoring_folder_cache', $rows);
    }

    /**
     * Registri folder (hanya mode folder: kunci unik folder_id = folder unit).
     *
     * @param  list<array>  $slots
     * @param  array<string, Carbon>  $lastSync
     */
    private function seedRegistry(array $slots, array $lastSync): void
    {
        $normalizer = app(UnitNameNormalizerService::class);
        $rows       = [];

        foreach ($slots as $slot) {
            if ($slot['mode'] !== 'folder') {
                continue;
            }

            $semester = $this->semesterOf($slot['period']);
            $stamp    = $lastSync[$semester]->toDateTimeString();

            $rows[] = [
                'normalized_name' => $normalizer->normalize($slot['unit']),
                'original_name'   => $slot['unit'],
                'folder_id'       => $slot['folderId'],
                'folder_path'     => $slot['region'] . ' / ' . $slot['unit'],
                'region'          => strtoupper($slot['region']),
                'bulan'           => $this->monthNameOf($slot['period']),
                'semester'        => $semester,
                'last_scan'       => $stamp,
                'created_at'      => $stamp,
                'updated_at'      => $stamp,
            ];
        }

        $this->insertChunked('monitoring_folder_registry', $rows);
    }

    /**
     * Riwayat sync sukses (3 per semester). Sync terbaru = angka saat ini; yang lebih lama
     * menunjukkan lebih sedikit unit COMPLETE.
     *
     * @param  list<array>  $slots
     */
    private function seedSyncLogs(DemoDriveTree $tree, array $slots): void
    {
        $nodes = $tree->nodes();
        $rows  = [];

        foreach (self::SYNC_HISTORY as $semester => $times) {
            $semSlots = array_values(array_filter($slots, fn (array $s) => $this->semesterOf($s['period']) === $semester));
            $total    = count($semSlots);
            $complete = count(array_filter($semSlots, fn (array $s) => $this->statusOf($s) === 'COMPLETE'));

            $monthIds = array_unique(array_column($semSlots, 'monthId'));
            $folders  = 0;
            $files    = 0;
            foreach ($nodes as $node) {
                $month = $this->monthIdOf($node, $nodes);
                if ($month !== null && in_array($month, $monthIds, true)) {
                    $node['folder'] ? $folders++ : $files++;
                }
            }

            foreach ($times as $i => $startedAt) {
                $delta       = self::SYNC_COMPLETE_DELTA[$i];
                $started     = Carbon::parse($startedAt);
                $duration    = self::SYNC_DURATIONS[$i];
                $doneComplete = max(0, $complete - $delta);

                $rows[] = [
                    'semester'         => $semester,
                    'started_at'       => $started->toDateTimeString(),
                    'finished_at'      => $started->copy()->addSeconds((int) round($duration))->toDateTimeString(),
                    'duration_seconds' => $duration,
                    'folders_scanned'  => $folders,
                    'files_scanned'    => $files,
                    'progress'         => 100,
                    'processed'        => $total,
                    'total'            => $total,
                    'complete_count'   => $doneComplete,
                    'incomplete_count' => $total - $doneComplete,
                    'mismatch_count'   => 0,
                    'current_region'   => null,
                    'current_unit'     => null,
                    'status'           => 'COMPLETED',
                    'error_message'    => null,
                    'created_at'       => $started->toDateTimeString(),
                    'updated_at'       => $started->copy()->addSeconds((int) round($duration))->toDateTimeString(),
                ];
            }
        }

        $this->insertChunked('monitoring_sync_logs', $rows);
    }

    /**
     * Riwayat Auto Generate Folder: pembuatan struktur bulan Agu–Nov 2026 (context MONTH) dan
     * satu pembuatan unit di bawah region (context PARENT).
     *
     * @param  list<array>  $slots
     */
    private function seedGenerationLogs(array $slots): void
    {
        $monthSlots = [];
        foreach ($slots as $slot) {
            $monthSlots[$slot['monthId']] ??= ['slot' => $slot, 'count' => 0];
            $monthSlots[$slot['monthId']]['count']++;
        }

        $rows  = [];
        $times = ['2026-07-28 10:02:11', '2026-08-28 10:05:40', '2026-09-28 09:58:03', '2026-10-28 10:01:27'];
        $i     = 0;

        foreach ($monthSlots as $monthId => $info) {
            if ($info['slot']['period'] < '2026-08') {
                continue; // riwayat hanya untuk bulan Agu–Nov 2026
            }
            $started = Carbon::parse($times[$i++ % count($times)]);
            $total   = 12 + count(array_filter($slots, fn (array $s) => $s['monthId'] === $monthId && $s['mode'] === 'folder'));

            $rows[] = [
                'semester'          => $this->semesterOf($info['slot']['period']),
                'bulan'             => $this->monthNameOf($info['slot']['period']),
                'folder_id'         => $monthId,
                'context_level'     => 'MONTH',
                'month_folder_id'   => $monthId,
                'status'            => 'COMPLETED',
                'progress'          => 100,
                'processed_folders' => $total,
                'new_count'         => $total,
                'existing_count'    => 0,
                'duration_seconds'  => 5.6 + $i * 0.4,
                'total_folders'     => $total,
                'current_folder'    => 'Generate selesai',
                'error_message'     => null,
                'created_at'        => $started->toDateTimeString(),
                'updated_at'        => $started->copy()->addSeconds(6)->toDateTimeString(),
            ];
        }

        $this->insertChunked('folder_generation_logs', $rows);
    }

    // ─────────────────────────────────────────────────────────
    // HELPER
    // ─────────────────────────────────────────────────────────

    /** COMPLETE / PENDING / INCOMPLETE dari slot pohon. */
    private function statusOf(array $slot): string
    {
        if (! $slot['hasFiles']) {
            return 'INCOMPLETE';
        }

        return $slot['pending'] ? 'PENDING' : 'COMPLETE';
    }

    private function monthNameOf(string $period): string
    {
        return self::MONTH_NAMES[(int) substr($period, 5, 2)];
    }

    /** Semester 1 = Des–Mei, Semester 2 = Jun–Nov. */
    private function semesterOf(string $period): string
    {
        $month = (int) substr($period, 5, 2);

        return ($month === 12 || $month <= 5) ? 'Semester 1' : 'Semester 2';
    }

    private function cleanUnitName(string $name): string
    {
        return preg_replace('/^[a-z0-9]+\.\s*/i', '', trim($name));
    }

    /** ID folder bulan leluhur sebuah node (node itu sendiri bila bulan), atau null. */
    private function monthIdOf(array $node, array $nodes): ?string
    {
        $guard = 0;
        while ($guard++ < 8) {
            if ($node['kind'] === 'month') {
                return $node['id'];
            }
            if (! isset($nodes[$node['parent']])) {
                return null;
            }
            $node = $nodes[$node['parent']];
        }

        return null;
    }

    /**
     * @param  list<array>  $rows
     */
    private function insertChunked(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 100) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}
