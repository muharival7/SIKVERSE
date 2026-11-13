<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensi_sik', function (Blueprint $table) {
            $table->id();

            $table->foreignId('anggota_periode_sik_id')
                ->constrained('anggota_periode_sik')
                ->restrictOnDelete();

            $table->foreignId('pertemuan_id')
                ->constrained('pertemuan')
                ->restrictOnDelete();

            $table->time('waktu_masuk')->nullable();
            $table->time('waktu_pulang')->nullable();

            $table->enum('metode_absensi', [
                'Fingerprint',
                'Manual',
            ])->default('Fingerprint');

            $table->enum('status', [
                'Hadir',
                'Izin',
                'Sakit',
                'Alpha',
                'Tidak Lengkap',
            ])->default('Tidak Lengkap');

            $table->foreignId('dicatat_oleh')
                ->nullable()
                ->constrained('infrastruktur_siks')
                ->restrictOnDelete();

            $table->text('keterangan')->nullable();

            $table->timestamps();

            $table->unique(
                ['anggota_periode_sik_id', 'pertemuan_id'],
                'absensi_anggota_pertemuan_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi_sik');
    }
};