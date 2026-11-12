<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * EvidenceMatchingService
 *
 * Senior Engineer Unified Matching Engine for SIMDOK Monitoring Sync.
 * Serves as the single source of truth for evidence search (mode=file vs mode=folder).
 * Driven by config/unit_matching_rules.json with in-memory caching and pre-normalized keywords.
 */
class EvidenceMatchingService
{
    private ?array $parentsConfig = null;
    private ?Collection $rulesCollection = null;

    public function __construct(
        private readonly UnitNameNormalizerService $normalizer,
    ) {}

    /**
     * Standard String Normalizer:
     * - lowercase
     * - strip image extensions (.png, .jpg, .jpeg)
     * - trim
     * - replace '_' and '-' with space
     * - collapse multiple spaces
     */
    public function normalizeString(string $s): string
    {
        $str = strtolower(trim($s));
        $str = preg_replace('/\.(png|jpg|jpeg|pdf|webp)$/i', '', $str);
        $str = str_replace(['_', '-'], ' ', $str);
        $str = preg_replace('/\s+/', ' ', $str);
        return trim($str);
    }

    /**
     * Ensures JSON configuration file is loaded into in-memory collections once.
     */
    private function ensureLoaded(): void
    {
        if ($this->parentsConfig !== null && $this->rulesCollection !== null) {
            return;
        }

        $configPath = base_path('config/unit_matching_rules.json');
        if (! file_exists($configPath)) {
            Log::error("EvidenceMatchingService: Configuration file missing at {$configPath}");
            $this->parentsConfig   = [];
            $this->rulesCollection = collect();
            return;
        }

        $jsonStr = file_get_contents($configPath);
        $data    = json_decode($jsonStr, true) ?? [];

        $this->parentsConfig = $data['parents'] ?? [];
        
        // Pre-normalize keywords/aliases once during load for optimal performance
        $rawRules = $data['rules'] ?? [];
        $processedRules = array_map(function ($rule) {
            $mode = $rule['mode'] ?? 'folder';
            $type = $rule['match']['type'] ?? ($mode === 'file' ? 'keyword' : 'folder');
            
            $items = $mode === 'file' 
                ? ($rule['match']['keywords'] ?? []) 
                : ($rule['match']['aliases'] ?? []);

            $normalizedItems = array_map(fn ($item) => $this->normalizeString($item), $items);

            $rule['match']['type']             = $type;
            $rule['match']['raw_items']        = $items;
            $rule['match']['normalized_items'] = $normalizedItems;

            return $rule;
        }, $rawRules);

        $this->rulesCollection = collect($processedRules);
    }

    /**
     * Returns matching mode ('file' or 'folder') for a given parent region name.
     */
    public function getParentMode(string $parentName): string
    {
        $this->ensureLoaded();

        $normRegion = $this->normalizer->normalizeRegion($parentName);

        foreach ($this->parentsConfig as $configParentKey => $info) {
            $normConfigKey = $this->normalizer->normalizeRegion($configParentKey);
            if ($normRegion === $normConfigKey) {
                return $info['mode'] ?? 'folder';
            }

            foreach ($info['aliases'] ?? [] as $alias) {
                if ($this->normalizer->normalizeRegion($alias) === $normRegion) {
                    return $info['mode'] ?? 'folder';
                }
            }
        }

        return 'folder';
    }

    /**
     * MODE 2: Keyword-based File Matching for parents with mode=file (UID, UP2D, UP2K).
     *
     * Finds Google Sheet unit name matching a filename within the specified parent scope.
     * Uses two-way keyword matching: contains(filename, keyword) OR contains(keyword, filename).
     *
     * @param  string  $filename
     * @param  string  $parentName
     * @return array{unit: string, matched_keyword: string, keywords: array}|null
     */
    public function matchFileKeywords(string $filename, string $parentName): ?array
    {
        $this->ensureLoaded();

        $normFile = $this->normalizeString($filename);
        $mode     = $this->getParentMode($parentName);

        if ($mode !== 'file') {
            return null;
        }

        $normParent = $this->normalizer->normalizeRegion($parentName);

        // Filter rules scoped to parent
        $scopedRules = $this->rulesCollection->filter(function ($rule) use ($normParent) {
            $ruleParentNorm = $this->normalizer->normalizeRegion($rule['parent']);
            return $ruleParentNorm === $normParent;
        });

        foreach ($scopedRules as $rule) {
            $rawKeywords  = $rule['match']['raw_items'];
            $normKeywords = $rule['match']['normalized_items'];

            foreach ($normKeywords as $index => $normKw) {
                if ($normKw === '') {
                    continue;
                }

                $isMatch = str_contains($normFile, $normKw) || str_contains($normKw, $normFile);

                if ($isMatch) {
                    $matchedKeyword = $rawKeywords[$index] ?? $normKw;

                    Log::info('EvidenceMatchingService [KEYWORD MATCH SUCCESS]', [
                        'Region'          => $parentName,
                        'Mode'            => 'FILE',
                        'Google Sheet'    => $rule['unit'],
                        'Keywords'        => implode(', ', $rawKeywords),
                        'Filename'        => $filename,
                        'Normalized'      => $normFile,
                        'Matched Keyword' => $matchedKeyword,
                        'SQLite'          => $rule['unit'],
                        'Status'          => 'COMPLETE',
                    ]);

                    return [
                        'unit'            => $rule['unit'],
                        'matched_keyword' => $matchedKeyword,
                        'keywords'        => $rawKeywords,
                    ];
                }
            }
        }

        return null;
    }

    /**
     * MODE 1: Folder-based Alias Matching for parents with mode=folder (UP3s).
     */
    public function matchFolderAlias(string $folderName, string $sheetUnitName, ?string $parentName = null): bool
    {
        $this->ensureLoaded();

        $normFolder = $this->normalizeString($folderName);
        $cleanUnit  = preg_replace('/^[a-z0-9]+\.\s*/i', '', trim($sheetUnitName));
        $normUnit   = $this->normalizeString($cleanUnit);

        // Find rule for this unit
        $rule = $this->rulesCollection->first(function ($r) use ($cleanUnit, $sheetUnitName) {
            return $r['unit'] === $sheetUnitName || $r['unit'] === $cleanUnit;
        });

        $aliases = $rule ? $rule['match']['normalized_items'] : [$normUnit];

        foreach ($aliases as $normAlias) {
            if ($normAlias === '') {
                continue;
            }

            if ($normFolder === $normAlias || str_contains($normFolder, $normAlias) || str_contains($normAlias, $normFolder)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Gets all configured rules for a parent.
     */
    public function getRulesForParent(string $parentName): array
    {
        $this->ensureLoaded();

        $normParent = $this->normalizer->normalizeRegion($parentName);

        return $this->rulesCollection->filter(function ($rule) use ($normParent) {
            return $this->normalizer->normalizeRegion($rule['parent']) === $normParent;
        })->values()->toArray();
    }
}
