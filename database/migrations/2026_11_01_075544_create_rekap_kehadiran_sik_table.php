<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rekap_kehadiran_sik', function (Blueprint $table) {
            $table->id();

            $table->foreignId('anggota_periode_sik_id')
                ->constrained('anggota_periode_sik')
                ->restrictOnDelete();

            $table->foreignId('kegiatan_id')
                ->constrained('kegiatan')
                ->restrictOnDelete();

            $table->unsignedTinyInteger('total_pertemuan')->default(16);
            $table->unsignedTinyInteger('jumlah_hadir')->default(0);
            $table->unsignedTinyInteger('jumlah_izin')->default(0);
            $table->unsignedTinyInteger('jumlah_sakit')->default(0);
            $table->unsignedTinyInteger('jumlah_alpha')->default(0);
            $table->unsignedTinyInteger('jumlah_tidak_lengkap')->default(0);

            $table->decimal('persentase_kehadiran', 5, 2)->default(0);

            $table->string('status_kehadiran', 30)->default('Belum Memenuhi');

            $table->timestamps();

            $table->unique(
                ['anggota_periode_sik_id', 'kegiatan_id'],
                'rekap_anggota_kegiatan_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekap_kehadiran_sik');
    }
};