<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel cache untuk menyimpan hasil rekursif traversal (DFS) Google Drive.
 *
 * Setiap baris merepresentasikan satu LEAF FOLDER monitoring yang ditemukan
 * di bawah folder bulan tertentu. Digunakan oleh DriveLeafScannerService
 * untuk menghindari full DFS setiap kali sync dilakukan.
 *
 * TTL: 24 jam (bulan aktif), 7 hari (bulan lalu — sudah tutup).
 * Invalidasi: saat upload/delete file terjadi pada folder tersebut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('monitoring_folder_cache', function (Blueprint $table) {
            $table->id();

            // Identitas folder di Google Drive
            $table->string('drive_folder_id', 200)->unique()
                ->comment('Google Drive Folder ID dari leaf folder monitoring');

            // Konteks hirarki
            $table->string('month_folder_id', 200)->index()
                ->comment('ID folder bulan (root DFS), e.g., folder "4. APR 2026"');
            $table->string('month_period', 20)->index()
                ->comment('Format: YYYY-MM, e.g., 2026-04');
            $table->string('region_name', 200)
                ->comment('Nama region (lowercase), e.g., "up3 palembang"');
            $table->string('unit_name', 200)
                ->comment('Nama unit/sub-unit leaf (lowercase), e.g., "ulp ampera"');

            // Data konten
            $table->unsignedInteger('image_file_count')->default(0)
                ->comment('Jumlah file .jpg/.jpeg/.png valid di leaf folder');
            $table->boolean('is_leaf')->default(true)
                ->comment('True jika folder ini adalah leaf (tidak punya subfolder monitoring)');

            // Waktu
            $table->timestamp('last_scanned_at')->nullable()
                ->comment('Kapan terakhir folder ini di-scan via Drive API');
            $table->timestamp('drive_modified_at')->nullable()
                ->comment('modifiedTime dari Drive API — untuk deteksi perubahan');

            $table->timestamps();

            // Composite index untuk lookup monitoring per bulan
            $table->index(['month_period', 'region_name', 'unit_name'], 'idx_monitoring_lookup');
            $table->index(['last_scanned_at'], 'idx_scan_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('monitoring_folder_cache');
    }
};
