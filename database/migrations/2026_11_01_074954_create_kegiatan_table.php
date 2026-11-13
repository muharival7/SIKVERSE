<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kegiatan', function (Blueprint $table) {
            $table->id();

            $table->foreignId('periode_sik_id')
                ->constrained('periode_sik')
                ->restrictOnDelete();

            $table->foreignId('kelompok_id')
                ->nullable()
                ->constrained('kelompok')
                ->restrictOnDelete();

            $table->string('nama_kegiatan', 150);

            $table->enum('jenis_kegiatan', [
                'Kelas',
                'Aula',
            ]);

            $table->text('keterangan')->nullable();
            $table->string('status', 20)->default('Aktif');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kegiatan');
    }
};