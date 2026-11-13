<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anggota_periode_sik', function (Blueprint $table) {
            $table->id();

            $table->foreignId('santri_id')
                ->constrained('santris')
                ->restrictOnDelete();

            $table->foreignId('periode_sik_id')
                ->constrained('periode_sik')
                ->restrictOnDelete();

            $table->foreignId('kelompok_id')
                ->constrained('kelompok')
                ->restrictOnDelete();

            $table->date('tanggal_masuk')->nullable();
            $table->string('status', 20)->default('Aktif');
            $table->timestamps();

            $table->unique(
                ['santri_id', 'periode_sik_id'],
                'anggota_santri_periode_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anggota_periode_sik');
    }
};