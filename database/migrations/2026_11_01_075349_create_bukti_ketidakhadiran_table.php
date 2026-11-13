<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bukti_ketidakhadiran', function (Blueprint $table) {
            $table->id();

            $table->foreignId('absensi_sik_id')
                ->constrained('absensi_sik')
                ->restrictOnDelete();

            $table->enum('jenis_bukti', [
                'Izin',
                'Sakit',
            ]);

            $table->string('file_bukti');

            $table->enum('status_verifikasi', [
                'Menunggu',
                'Diterima',
                'Ditolak',
            ])->default('Menunggu');

            $table->foreignId('diperiksa_oleh')
                ->nullable()
                ->constrained('infrastruktur_siks')
                ->restrictOnDelete();

            $table->timestamp('tanggal_pemeriksaan')->nullable();
            $table->text('catatan_pemeriksaan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bukti_ketidakhadiran');
    }
};