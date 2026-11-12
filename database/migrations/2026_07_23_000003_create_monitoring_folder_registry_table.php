<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_folder_registry', function (Blueprint $table) {
            $table->id();

            $table->string('normalized_name', 200)->index()
                ->comment('Nama kanonikal bersih, contoh: TELANAIPURA');
            $table->string('original_name', 250)
                ->comment('Nama mentah di Sheet/Drive, contoh: 01. MULP TELANAIPURA');
            $table->string('folder_id', 200)->unique()
                ->comment('Google Drive Folder ID');
            $table->string('folder_path', 500)->nullable()
                ->comment('Path hirarki folder');
            $table->string('region', 200)->index()
                ->comment('Region kanonikal, contoh: UP3 JAMBI');
            $table->string('bulan', 50)->index()
                ->comment('Bulan standar, contoh: Januari');
            $table->string('semester', 50)->index()
                ->comment('Semester 1 atau Semester 2');
            
            $table->timestamp('last_scan')->nullable()
                ->comment('Waktu terakhir folder ini di-scan');
            $table->timestamps();

            // Composite index untuk lookup instan
            $table->index(['semester', 'bulan', 'region', 'normalized_name'], 'idx_registry_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_folder_registry');
    }
};
