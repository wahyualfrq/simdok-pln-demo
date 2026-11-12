<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitoring_statuses', function (Blueprint $table) {
            $table->index('folder_id', 'idx_folder_id_status');
        });
    }

    public function down(): void
    {
        Schema::table('monitoring_statuses', function (Blueprint $table) {
            $table->dropIndex('idx_folder_id_status');
        });
    }
};
