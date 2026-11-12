<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonitoringSyncLog extends Model
{
    protected $table = 'monitoring_sync_logs';

    protected $fillable = [
        'semester',
        'started_at',
        'finished_at',
        'duration_seconds',
        'folders_scanned',
        'files_scanned',
        'progress',
        'processed',
        'total',
        'complete_count',
        'incomplete_count',
        'mismatch_count',
        'current_region',
        'current_unit',
        'status',
        'error_message',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
        'duration_seconds' => 'float',
        'folders_scanned' => 'integer',
        'files_scanned' => 'integer',
        'progress' => 'integer',
        'processed' => 'integer',
        'total' => 'integer',
        'complete_count' => 'integer',
        'incomplete_count' => 'integer',
        'mismatch_count' => 'integer',
    ];
}
