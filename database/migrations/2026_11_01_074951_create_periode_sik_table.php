<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periode_sik', function (Blueprint $table) {
            $table->id();
            $table->string('nama_periode', 100);
            $table->unsignedSmallInteger('tahun');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->string('status', 20)->default('Persiapan');
            $table->timestamps();

            $table->unique('nama_periode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periode_sik');
    }
};