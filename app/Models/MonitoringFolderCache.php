<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonitoringFolderCache extends Model
{
    protected $table = 'monitoring_folder_cache';

    protected $fillable = [
        'drive_folder_id',
        'month_folder_id',
        'month_period',
        'region_name',
        'unit_name',
        'image_file_count',
        'is_leaf',
        'last_scanned_at',
        'drive_modified_at',
    ];

    protected $casts = [
        'image_file_count' => 'integer',
        'is_leaf' => 'boolean',
        'last_scanned_at' => 'datetime',
        'drive_modified_at' => 'datetime',
    ];
}
