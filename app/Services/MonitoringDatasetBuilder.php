<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\MonitoringStatus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * MonitoringDatasetBuilder
 *
 * Mengambil data monitoring dari SQLite / Cache dan menyusun JSON dataset
 * untuk WYSIWYG Report Renderer (< 100ms response time).
 */
class MonitoringDatasetBuilder
{
    private FooterSettingsService $footerSettingsService;

    public const SEMESTER_MONTHS = [
        'Semester 1' => ['Desember', 'Januari', 'Februari', 'Maret', 'April', 'Mei'],
        'Semester 2' => ['Juni', 'Juli', 'Agustus', 'September', 'Oktober', 'November'],
    ];

    public function __construct(FooterSettingsService $footerSettingsService)
    {
        $this->footerSettingsService = $footerSettingsService;
    }

    /**
     * Build or return cached dataset untuk semester tertentu.
     *
     * @param  string  $semester  "Semester 1" atau "Semester 2"
     * @return array
     */
    public function getDataset(string $semester): array
    {
        $slug     = strtolower(str_replace(' ', '_', $semester));
        $cacheKey = "simdok:report_dataset_{$slug}";

        return Cache::remember($cacheKey, 1800, function () use ($semester) {
            return $this->buildDataset($semester);
        });
    }

    /**
     * Susun dataset secara langsung dari SQLite database.
     */
    public function buildDataset(string $semester): array
    {
        $startTime = microtime(true);
        $months    = self::SEMESTER_MONTHS[$semester] ?? self::SEMESTER_MONTHS['Semester 1'];
        $statuses  = MonitoringStatus::where('semester', $semester)->get();

        // Map status: [unit_key => [bulan => status]]
        $unitStatusMap = [];
        foreach ($statuses as $row) {
            $unitName = $row->unit_original ?: $row->unit;
            $unitKey  = $this->normalizeUnitKey($unitName);
            $unitStatusMap[$unitKey][$row->bulan] = strtolower($row->status);
        }

        $footer = $this->footerSettingsService->getDefault();

        $elapsed = round((microtime(true) - $startTime) * 1000, 2);
        Log::info("[MonitoringDatasetBuilder] Dataset built for {$semester} in {$elapsed} ms");

        return [
            'semester'     => $semester,
            'months'       => $months,
            'total_units'  => count($unitStatusMap),
            'status_map'   => $unitStatusMap,
            'footer'       => $footer,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Hapus cache dataset report.
     */
    public function clearCache(?string $semester = null): void
    {
        if ($semester) {
            $slug = strtolower(str_replace(' ', '_', $semester));
            Cache::forget("simdok:report_dataset_{$slug}");
        } else {
            Cache::forget('simdok:report_dataset_semester_1');
            Cache::forget('simdok:report_dataset_semester_2');
        }
        Log::info('[MonitoringDatasetBuilder] Report dataset cache cleared');
    }

    private function normalizeUnitKey(string $name): string
    {
        $s = strtolower(trim($name));
        $s = preg_replace('/^[a-z0-9]+\.\s*/i', '', $s);
        $s = preg_replace('/[^a-z0-9 ]/i', '', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim($s);
    }
}
