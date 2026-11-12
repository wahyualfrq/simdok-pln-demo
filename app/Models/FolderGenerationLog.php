<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FolderGenerationLog extends Model
{
    protected $table = 'folder_generation_logs';

    protected $fillable = [
        'semester',
        'bulan',
        'folder_id',
        'context_level',
        'month_folder_id',
        'status',
        'progress',
        'processed_folders',
        'new_count',
        'existing_count',
        'duration_seconds',
        'total_folders',
        'current_folder',
        'error_message',
    ];

    protected $casts = [
        'progress'          => 'integer',
        'processed_folders' => 'integer',
        'new_count'         => 'integer',
        'existing_count'    => 'integer',
        'duration_seconds'  => 'float',
        'total_folders'     => 'integer',
    ];
}
