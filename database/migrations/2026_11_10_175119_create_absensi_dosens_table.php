<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensi_dosen', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('infrastruktur_sik_id')
                ->constrained('infrastruktur_siks')
                ->restrictOnDelete();
            $table->date('tanggal');
            $table->time('waktu_masuk')->nullable();
            $table->time('waktu_pulang')->nullable();
            $table->enum('status', ['Hadir', 'Izin', 'Sakit', 'Alpha']);
            $table->text('keterangan')->nullable();
            $table->foreignId('dicatat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['infrastruktur_sik_id', 'tanggal'], 'absensi_dosen_tanggal_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_dosen');
    }
};
