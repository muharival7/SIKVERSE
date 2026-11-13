<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('santris', function (Blueprint $table): void {
            $table->enum('jenis_kelamin', ['Laki-laki', 'Perempuan'])->nullable()->change();
            $table->string('program_studi')->nullable()->after('nama');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('santris')->whereNull('jenis_kelamin')->exists()) {
            throw new RuntimeException('Isi jenis kelamin santri yang masih kosong sebelum membatalkan migrasi.');
        }

        Schema::table('santris', function (Blueprint $table): void {
            $table->dropColumn('program_studi');
            $table->enum('jenis_kelamin', ['Laki-laki', 'Perempuan'])->nullable(false)->change();
        });
    }
};
