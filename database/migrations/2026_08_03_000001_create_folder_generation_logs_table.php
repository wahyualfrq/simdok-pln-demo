<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('folder_generation_logs', function (Blueprint $table) {
            $table->id();
            $table->string('semester')->nullable();
            $table->string('bulan')->nullable();
            $table->string('folder_id')->nullable();
            $table->string('context_level')->nullable(); // ROOT, MONTH, PARENT, UNIT
            $table->string('month_folder_id')->nullable();
            $table->string('status')->default('PENDING'); // PENDING, RUNNING, COMPLETED, FAILED
            $table->integer('progress')->default(0);
            $table->integer('processed_folders')->default(0);
            $table->integer('new_count')->default(0);
            $table->integer('existing_count')->default(0);
            $table->float('duration_seconds')->nullable();
            $table->integer('total_folders')->default(0);
            $table->string('current_folder')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('folder_generation_logs');
    }
};
