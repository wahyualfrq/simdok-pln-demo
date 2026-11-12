<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MonitoringFolderRegistry;
use Illuminate\Support\Facades\Log;

class FolderRegistryService
{
    public function __construct(
        private readonly UnitNameNormalizerService $normalizer,
    ) {}

    /**
     * Mencari folder_id yang sudah terdaftar di Registry berdasarkan hirarki & nama unit.
     *
     * @param  string  $semester  'Semester 1' atau 'Semester 2'
     * @param  string  $bulan     'Januari', 'Februari', dst.
     * @param  string  $region    'UP3 PALEMBANG', 'KANTOR UID', dst.
     * @param  string  $unitName  Nama unit mentah dari Sheet/Drive
     * @return string|null  Google Drive Folder ID jika ditemukan
     */
    public function findFolderId(
        string $semester,
        string $bulan,
        string $region,
        string $unitName
    ): ?string {
        $normUnit   = $this->normalizer->normalize($unitName);
        $normRegion = $this->normalizer->normalizeRegion($region);

        if ($normUnit === '') {
            return null;
        }

        // 1. Direct Lookup by Exact Normalized Name
        $record = MonitoringFolderRegistry::where('semester', $semester)
            ->where('bulan', $bulan)
            ->where(function ($q) use ($region, $normRegion) {
                $q->where('region', strtoupper(trim($region)))
                  ->orWhere('region', 'LIKE', "%{$normRegion}%");
            })
            ->where('normalized_name', $normUnit)
            ->first();

        if ($record) {
            return $record->folder_id;
        }

        // 2. Fallback Lookup by Match Check
        $candidates = MonitoringFolderRegistry::where('semester', $semester)
            ->where('bulan', $bulan)
            ->get();

        foreach ($candidates as $candidate) {
            if (
                $this->normalizer->isMatch($candidate->region, $region) &&
                $this->normalizer->isMatch($candidate->original_name, $unitName)
            ) {
                return $candidate->folder_id;
            }
        }

        return null;
    }

    /**
     * Daftarkan atau perbarui ID folder ke dalam Registry.
     *
     * @param  string       $semester
     * @param  string       $bulan
     * @param  string       $region
     * @param  string       $originalName
     * @param  string       $folderId
     * @param  string|null  $folderPath
     * @return MonitoringFolderRegistry
     */
    public function registerFolder(
        string $semester,
        string $bulan,
        string $region,
        string $originalName,
        string $folderId,
        ?string $folderPath = null
    ): MonitoringFolderRegistry {
        $normUnit = $this->normalizer->normalize($originalName);

        return MonitoringFolderRegistry::updateOrCreate(
            ['folder_id' => $folderId],
            [
                'normalized_name' => $normUnit,
                'original_name'   => trim($originalName),
                'folder_path'     => $folderPath,
                'region'          => strtoupper(trim($region)),
                'bulan'           => $bulan,
                'semester'        => $semester,
                'last_scan'       => now(),
            ]
        );
    }

    /**
     * Memeriksa apakah Registry sudah terisi untuk semester tertentu.
     *
     * @param  string  $semester
     * @return bool
     */
    public function hasRegistryForSemester(string $semester): bool
    {
        return MonitoringFolderRegistry::where('semester', $semester)->exists();
    }

    /**
     * Mengambil seluruh record Registry untuk semester tertentu.
     *
     * @param  string  $semester
     * @return \Illuminate\Database\Eloquent\Collection<int, MonitoringFolderRegistry>
     */
    public function getRegistryForSemester(string $semester)
    {
        return MonitoringFolderRegistry::where('semester', $semester)->get();
    }

    /**
     * Hapus seluruh data Registry untuk semester tertentu (force refresh).
     *
     * @param  string|null  $semester
     */
    public function clearRegistry(?string $semester = null): void
    {
        if ($semester) {
            MonitoringFolderRegistry::where('semester', $semester)->delete();
        } else {
            MonitoringFolderRegistry::truncate();
        }

        Log::info('FolderRegistryService: Registry dibersihkan', ['semester' => $semester ?? 'ALL']);
    }
}
