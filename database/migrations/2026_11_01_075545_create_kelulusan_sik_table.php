<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelulusan_sik', function (Blueprint $table) {
            $table->id();

            $table->foreignId('anggota_periode_sik_id')
                ->unique()
                ->constrained('anggota_periode_sik')
                ->restrictOnDelete();

            $table->string('status', 30)->default('Belum Lulus');

            $table->date('tanggal_kelulusan')->nullable();
            $table->text('keterangan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelulusan_sik');
    }
};