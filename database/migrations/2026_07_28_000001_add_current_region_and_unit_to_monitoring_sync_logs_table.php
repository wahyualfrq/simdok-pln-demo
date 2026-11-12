<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('monitoring_sync_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('monitoring_sync_logs', 'current_region')) {
                $table->string('current_region', 200)->nullable()->after('mismatch_count');
            }
            if (! Schema::hasColumn('monitoring_sync_logs', 'current_unit')) {
                $table->string('current_unit', 200)->nullable()->after('current_region');
            }
        });
    }

    public function down(): void
    {
        Schema::table('monitoring_sync_logs', function (Blueprint $table) {
            if (Schema::hasColumn('monitoring_sync_logs', 'current_region')) {
                $table->dropColumn('current_region');
            }
            if (Schema::hasColumn('monitoring_sync_logs', 'current_unit')) {
                $table->dropColumn('current_unit');
            }
        });
    }
};
