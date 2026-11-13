<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelulusan', function (Blueprint $table) {
            $table->id();

            $table->foreignId('santri_id')
                ->unique()
                ->constrained('santris')
                ->cascadeOnDelete();

            $table->string('status')->default('Belum Lulus');
            $table->unsignedSmallInteger('tahun_lulus')->nullable();
            $table->date('tanggal_kelulusan')->nullable();
            $table->text('keterangan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelulusan');
    }
};