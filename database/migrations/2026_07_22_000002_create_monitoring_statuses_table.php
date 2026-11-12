<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_statuses', function (Blueprint $table) {
            $table->id();

            // Hirarki & Identitas Monitoring
            $table->string('semester', 50)->index();      // 'Semester 1', 'Semester 2'
            $table->string('bulan', 50)->index();         // 'Desember', 'Januari', ... 'November'
            $table->string('region', 200)->index();       // e.g. 'UP3 PALEMBANG', 'KANTOR UID'
            $table->string('unit', 200)->index();         // e.g. 'ULP AMPERA'
            $table->string('sub_unit', 200)->nullable();  // e.g. 'MULP BKO'
            $table->string('unit_original', 250);         // Nama mentah sel Google Sheet (e.g. '1. ULP AMPERA')

            // Data Status Sheet vs Drive
            $table->string('sheet_status', 20)->default('');     // 'v', '', '✓'
            $table->string('drive_status', 20)->default('INCOMPLETE'); // 'COMPLETE', 'INCOMPLETE'
            $table->string('status', 20)->index()->default('INCOMPLETE'); // 'COMPLETE', 'INCOMPLETE', 'MISMATCH'

            // Evidence & Metadata
            $table->unsignedInteger('evidence_count')->default(0);
            $table->string('sample_evidence_file', 250)->nullable();
            $table->string('folder_id', 200)->nullable();
            $table->string('folder_name', 200)->nullable();
            $table->string('folder_path', 500)->nullable();

            $table->timestamp('last_sync')->nullable();
            $table->timestamps();

            // Unique key untuk upsert
            $table->unique(['semester', 'bulan', 'region', 'unit'], 'idx_status_unique');

            // Composite Indexes untuk query ultra-cepat (< 10ms)
            $table->index(['semester', 'bulan'], 'idx_sem_bulan');
            $table->index(['semester', 'region'], 'idx_sem_region');
            $table->index(['semester', 'bulan', 'unit'], 'idx_sem_bulan_unit');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_statuses');
    }
};
