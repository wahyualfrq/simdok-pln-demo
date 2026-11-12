<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonitoringStatus extends Model
{
    protected $table = 'monitoring_statuses';

    protected $fillable = [
        'semester',
        'bulan',
        'region',
        'unit',
        'sub_unit',
        'unit_original',
        'sheet_status',
        'drive_status',
        'status',
        'evidence_count',
        'sample_evidence_file',
        'folder_id',
        'folder_name',
        'folder_path',
        'last_sync',
    ];

    protected $casts = [
        'evidence_count' => 'integer',
        'last_sync' => 'datetime',
    ];
}
