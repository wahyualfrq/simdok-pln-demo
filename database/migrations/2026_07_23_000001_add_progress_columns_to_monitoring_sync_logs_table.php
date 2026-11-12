<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitoring_sync_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('monitoring_sync_logs', 'progress')) {
                $table->unsignedInteger('progress')->default(0)->after('files_scanned');
            }
            if (! Schema::hasColumn('monitoring_sync_logs', 'processed')) {
                $table->unsignedInteger('processed')->default(0)->after('progress');
            }
            if (! Schema::hasColumn('monitoring_sync_logs', 'total')) {
                $table->unsignedInteger('total')->default(0)->after('processed');
            }
        });
    }

    public function down(): void
    {
        Schema::table('monitoring_sync_logs', function (Blueprint $table) {
            $table->dropColumn(['progress', 'processed', 'total']);
        });
    }
};
