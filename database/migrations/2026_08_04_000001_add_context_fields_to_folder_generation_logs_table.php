<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('folder_generation_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('folder_generation_logs', 'folder_id')) {
                $table->string('folder_id')->nullable()->after('bulan');
            }
            if (! Schema::hasColumn('folder_generation_logs', 'context_level')) {
                $table->string('context_level')->nullable()->after('folder_id');
            }
            if (! Schema::hasColumn('folder_generation_logs', 'new_count')) {
                $table->integer('new_count')->default(0)->after('processed_folders');
            }
            if (! Schema::hasColumn('folder_generation_logs', 'existing_count')) {
                $table->integer('existing_count')->default(0)->after('new_count');
            }
            if (! Schema::hasColumn('folder_generation_logs', 'duration_seconds')) {
                $table->float('duration_seconds')->nullable()->after('existing_count');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('folder_generation_logs', function (Blueprint $table) {
            $columnsToDrop = array_filter(
                ['folder_id', 'context_level', 'new_count', 'existing_count', 'duration_seconds'],
                fn($col) => Schema::hasColumn('folder_generation_logs', $col)
            );
            if (! empty($columnsToDrop)) {
                $table->dropColumn(array_values($columnsToDrop));
            }
        });
    }
};
