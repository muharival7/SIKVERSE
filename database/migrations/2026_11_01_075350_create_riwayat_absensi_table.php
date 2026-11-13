<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_absensi', function (Blueprint $table) {
            $table->id();

            $table->foreignId('absensi_sik_id')
                ->constrained('absensi_sik')
                ->restrictOnDelete();

            $table->foreignId('instruktur_id')
                ->constrained('infrastruktur_siks')
                ->restrictOnDelete();

            $table->string('status_sebelumnya', 30)->nullable();
            $table->string('status_sesudahnya', 30);

            $table->text('alasan_perubahan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_absensi');
    }
};