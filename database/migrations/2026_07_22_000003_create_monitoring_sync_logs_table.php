<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_sync_logs', function (Blueprint $table) {
            $table->id();

            $table->string('semester', 50)->index();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->float('duration_seconds')->nullable();

            $table->unsignedInteger('folders_scanned')->default(0);
            $table->unsignedInteger('files_scanned')->default(0);
            $table->unsignedInteger('complete_count')->default(0);
            $table->unsignedInteger('incomplete_count')->default(0);
            $table->unsignedInteger('mismatch_count')->default(0);

            $table->string('status', 20)->default('running'); // 'running', 'success', 'failed'
            $table->text('error_message')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_sync_logs');
    }
};
