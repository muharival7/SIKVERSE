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
        Schema::table('nilai', function (Blueprint $table): void {
            // Baris lama sengaja dibiarkan NULL dan dibaca sebagai "Harian"
            // oleh aplikasi; tidak ada backfill data historis.
            $table->string('jenis_nilai', 20)->nullable()->after('mata_pelajaran_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nilai', function (Blueprint $table): void {
            $table->dropColumn('jenis_nilai');
        });
    }
};
