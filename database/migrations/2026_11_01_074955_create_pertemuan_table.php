<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pertemuan', function (Blueprint $table) {
            $table->id();

            $table->foreignId('kegiatan_id')
                ->constrained('kegiatan')
                ->restrictOnDelete();

            $table->unsignedTinyInteger('pertemuan_ke');
            $table->date('tanggal');

            $table->foreignId('instruktur_pengganti_id')
                ->nullable()
                ->constrained('infrastruktur_siks')
                ->restrictOnDelete();

            $table->foreignId('kelompok_host_id')
                ->nullable()
                ->constrained('kelompok')
                ->restrictOnDelete();

            $table->time('waktu_mulai')->nullable();
            $table->time('waktu_selesai')->nullable();

            $table->string('status', 20)->default('Terjadwal');
            $table->text('keterangan')->nullable();

            $table->timestamps();

            $table->unique(
                ['kegiatan_id', 'pertemuan_ke'],
                'pertemuan_kegiatan_ke_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pertemuan');
    }
};