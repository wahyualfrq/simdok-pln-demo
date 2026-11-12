<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * SyncDiagnosticLoggerService
 *
 * Senior Engineer Architectural Service for SIMDOK Monitoring Sync.
 * Handles internal granular status evaluations, dashboard status mapping,
 * per-unit diagnostic logging, and mismatch breakdown reporting.
 */
class SyncDiagnosticLoggerService
{
    // Internal Status Constants
    public const STATUS_COMPLETE          = 'COMPLETE';
    public const STATUS_INCOMPLETE        = 'INCOMPLETE';
    public const STATUS_FILE_NOT_FOUND     = 'FILE_NOT_FOUND';
    public const STATUS_VALIDATION_FAILED  = 'VALIDATION_FAILED';
    public const STATUS_ALIAS_NOT_MATCH    = 'ALIAS_NOT_MATCH';
    public const STATUS_PARENT_NOT_FOUND   = 'PARENT_NOT_FOUND';
    public const STATUS_UNKNOWN            = 'UNKNOWN';

    // Dashboard Status Constants
    public const DASHBOARD_COMPLETE   = 'COMPLETE';
    public const DASHBOARD_INCOMPLETE = 'INCOMPLETE';
    public const DASHBOARD_MISMATCH   = 'MISMATCH';

    private array $mismatchBreakdown = [
        self::STATUS_FILE_NOT_FOUND    => 0,
        self::STATUS_VALIDATION_FAILED => 0,
        self::STATUS_ALIAS_NOT_MATCH   => 0,
        self::STATUS_PARENT_NOT_FOUND  => 0,
        self::STATUS_UNKNOWN           => 0,
    ];

    private int $totalCount      = 0;
    private int $completeCount   = 0;
    private int $incompleteCount = 0;
    private int $mismatchCount   = 0;

    public function reset(): void
    {
        $this->totalCount      = 0;
        $this->completeCount   = 0;
        $this->incompleteCount = 0;
        $this->mismatchCount   = 0;

        foreach ($this->mismatchBreakdown as $key => $val) {
            $this->mismatchBreakdown[$key] = 0;
        }
    }

    /**
     * Evaluates internal status based on Google Sheet complete check and Drive screenshot presence.
     */
    public function evaluateInternalStatus(
        bool $sheetIsComplete,
        bool $driveHasScreenshot,
        bool $parentExists = true,
        bool $unitFolderMatched = true
    ): string {
        if ($driveHasScreenshot) {
            return self::STATUS_COMPLETE;
        }

        return self::STATUS_INCOMPLETE;
    }

    /**
     * Maps internal status code to Dashboard status string (COMPLETE, INCOMPLETE, MISMATCH).
     */
    public function mapToDashboardStatus(string $internalStatus): string
    {
        return match ($internalStatus) {
            self::STATUS_COMPLETE   => self::DASHBOARD_COMPLETE,
            self::STATUS_INCOMPLETE => self::DASHBOARD_INCOMPLETE,
            default                 => self::DASHBOARD_MISMATCH,
        };
    }

    /**
     * Records per-unit diagnostic data and outputs human-readable log block.
     */
    public function recordUnitDiagnostic(
        string $region,
        string $sheetUnit,
        array $aliases,
        ?string $folderFound,
        ?string $evidenceFound,
        bool $sheetIsComplete,
        string $internalStatus,
        string $mode = 'FOLDER',
        ?string $matchedKeyword = null
    ): void {
        $this->totalCount++;
        $dashboardStatus = $this->mapToDashboardStatus($internalStatus);

        if ($dashboardStatus === self::DASHBOARD_COMPLETE) {
            $this->completeCount++;
        } elseif ($dashboardStatus === self::DASHBOARD_INCOMPLETE) {
            $this->incompleteCount++;
        } else {
            $this->mismatchCount++;
            if (isset($this->mismatchBreakdown[$internalStatus])) {
                $this->mismatchBreakdown[$internalStatus]++;
            } else {
                $this->mismatchBreakdown[self::STATUS_UNKNOWN]++;
            }
        }

        $aliasStr    = ! empty($aliases) ? implode(', ', $aliases) : '(Tidak ada alias)';
        $evidenceStr = $evidenceFound ?? 'Tidak ditemukan';
        $kwStr       = $matchedKeyword ?? ($aliases[0] ?? '-');
        $normFile    = strtolower(trim(preg_replace('/\.(png|jpg|jpeg)$/i', '', str_replace(['_', '-'], ' ', $evidenceStr))));

        $logOutput = "\n=====================================\n"
            . "Region          : {$region}\n"
            . "Mode            : " . strtoupper($mode) . "\n"
            . "Google Sheet    : {$sheetUnit}\n"
            . "Keywords        : {$aliasStr}\n"
            . "Filename        : {$evidenceStr}\n"
            . "Normalized      : {$normFile}\n"
            . "Matched Keyword : {$kwStr}\n"
            . "SQLite          : {$sheetUnit}\n"
            . "Status          : {$internalStatus}\n"
            . "=====================================";

        Log::info($logOutput);
    }

    /**
     * Generates formatted Summary Detail report for logs/metadata.
     */
    public function generateSummaryReport(): string
    {
        $report = "\n==================================\n"
            . "Sync Result\n"
            . "Total            : {$this->totalCount}\n"
            . "Complete         : {$this->completeCount}\n"
            . "Mismatch         : {$this->mismatchCount}\n"
            . "Incomplete       : {$this->incompleteCount}\n"
            . "----------------------------------\n"
            . "Mismatch Detail\n"
            . "File Not Found   : {$this->mismatchBreakdown[self::STATUS_FILE_NOT_FOUND]}\n"
            . "Validation Failed: {$this->mismatchBreakdown[self::STATUS_VALIDATION_FAILED]}\n"
            . "Alias Failed     : {$this->mismatchBreakdown[self::STATUS_ALIAS_NOT_MATCH]}\n"
            . "Parent Failed    : {$this->mismatchBreakdown[self::STATUS_PARENT_NOT_FOUND]}\n"
            . "Unknown          : {$this->mismatchBreakdown[self::STATUS_UNKNOWN]}\n"
            . "==================================\n";

        Log::info($report);

        return $report;
    }
}
