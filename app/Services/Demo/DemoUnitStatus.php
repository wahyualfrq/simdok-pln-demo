<?php

declare(strict_types=1);

namespace App\Services\Demo;

use App\Services\CacheService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * DemoUnitStatus
 *
 * DEMO: menjaga konsistensi dua arah antara status sel Monitoring (monitoring_statuses) dan isi/status
 * folder di Drive Explorer. Sumber kebenaran = status sel per unit × bulan; isi folder diturunkan darinya
 * oleh DemoDriveStore::children(). Kelas ini dipanggil saat status berubah lewat jalur asli
 * (dropdown status, finalize, upload, hapus file) — controller asli tidak diubah.
 *
 * Pemetaan status:
 *   COMPLETE   ("Sudah Ceklist")  = folder berisi file dummy, sheet 'v'.
 *   PENDING    ("Pending Finalisasi") = file ada, tetapi belum diceklist/difinalisasi.
 *   INCOMPLETE ("Belum Upload")   = folder kosong.
 *   MISMATCH   tidak dipakai: pada aplikasi asli status ini hanya turunan dari evaluasi internal yang
 *              selalu menghasilkan COMPLETE/INCOMPLETE (lihat SyncDiagnosticLoggerService).
 *
 * Basis data yang dipakai adalah basis data SESI pengunjung (lihat DemoSessionIsolation), sehingga
 * perubahan tidak bocor antar pengunjung.
 */
class DemoUnitStatus
{
    public const COMPLETE   = 'COMPLETE';
    public const PENDING    = 'PENDING';
    public const INCOMPLETE = 'INCOMPLETE';

    public function __construct(private readonly DemoDriveStore $store) {}

    /**
     * Status tiap unit pada folder (unit_original => status). Kosong bila folder bukan folder unit.
     *
     * @return array<string, string>
     */
    public function forFolder(string $folderId): array
    {
        try {
            return array_map('strtoupper', DB::table('monitoring_statuses')->where('folder_id', $folderId)->pluck('status', 'unit_original')->all());
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Mengatur status semua unit pada folder (folder bersama mode file: seluruh unit di dalamnya).
     * Bila $onlyFrom diberikan, hanya unit yang berstatus saat ini ada di daftar itu yang diubah.
     * Kolom turunan (sheet/drive/jumlah & contoh bukti) dihitung ulang dari isi folder terbaru.
     *
     * @param  list<string>|null  $onlyFrom
     */
    public function setFolderStatus(string $folderId, string $status, ?array $onlyFrom = null): void
    {
        $query = DB::table('monitoring_statuses')->where('folder_id', $folderId);
        if ($onlyFrom !== null) {
            $query->whereIn('status', $onlyFrom);
        }

        $ids = $query->pluck('id')->all();
        if ($ids === []) {
            return;
        }

        $now = now()->toDateTimeString();

        // Tahap 1: status baru (isi folder yang tampil bergantung padanya).
        DB::table('monitoring_statuses')->whereIn('id', $ids)->update([
            'status'       => $status,
            'sheet_status' => $status === self::COMPLETE ? 'v' : '',
            'last_sync'    => $now,
            'updated_at'   => $now,
        ]);

        // Tahap 2: jumlah/contoh bukti dari file yang kini tampil.
        $files = array_values(array_filter($this->store->children($folderId), fn (array $n) => ! $n['folder']));
        $units = DB::table('monitoring_statuses')->where('folder_id', $folderId)->count();

        foreach (DB::table('monitoring_statuses')->whereIn('id', $ids)->get(['id', 'unit_original']) as $row) {
            $own = array_values(array_filter($files, fn (array $f) => isset($f['unit']) ? $f['unit'] === $row->unit_original : $units === 1));

            DB::table('monitoring_statuses')->where('id', $row->id)->update([
                'drive_status'         => $own === [] ? 'INCOMPLETE' : 'COMPLETE',
                'evidence_count'       => count($own),
                'sample_evidence_file' => $own[0]['name'] ?? null,
            ]);
        }

        app(CacheService::class)->invalidateAll();
    }

    /**
     * Nilai checklist sheet ('v' atau '') yang ditulis lewat jalur asli → status unit:
     * 'v' = COMPLETE; '' = cabut centang (COMPLETE → PENDING, unit lain tidak berubah).
     */
    public function applyChecklist(string $folderId, string $value): void
    {
        if ($value === 'v') {
            $this->setFolderStatus($folderId, self::COMPLETE);
        } else {
            $this->setFolderStatus($folderId, self::PENDING, [self::COMPLETE]);
        }
    }

    /**
     * Folder bersama (lebih dari satu unit) hanya punya SATU dropdown status di Drive Explorer, dan
     * controller asli membaca status manual dari cache lebih dulu. Status yang ditampilkan dibuat
     * mewakili seluruh unit: semua COMPLETE → complete; semua INCOMPLETE → (folder kosong); selain itu pending.
     */
    public function syncSharedFolderStatus(string $folderId): void
    {
        $statuses = $this->forFolder($folderId);
        if (count($statuses) < 2) {
            return;
        }

        $values = array_unique($statuses);
        $shown  = $values === [self::COMPLETE] ? self::COMPLETE : ($values === [self::INCOMPLETE] ? self::INCOMPLETE : self::PENDING);

        Cache::put("drive.folder.manual_status.{$folderId}", $shown, now()->addHours(24));
    }
}
