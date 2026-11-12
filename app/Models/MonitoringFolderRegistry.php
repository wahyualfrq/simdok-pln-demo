<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonitoringFolderRegistry extends Model
{
    protected $table = 'monitoring_folder_registry';

    protected $fillable = [
        'normalized_name',
        'original_name',
        'folder_id',
        'folder_path',
        'region',
        'bulan',
        'semester',
        'last_scan',
    ];

    protected $casts = [
        'last_scan' => 'datetime',
    ];
}
