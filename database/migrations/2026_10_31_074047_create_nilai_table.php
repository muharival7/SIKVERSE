<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nilai', function (Blueprint $table) {
            $table->id();

            $table->foreignId('santri_id')
                ->constrained('santris')
                ->cascadeOnDelete();

            $table->foreignId('mata_pelajaran_id')
                ->constrained('mata_pelajaran')
                ->cascadeOnDelete();

            $table->foreignId('infrastruktur_sik_id')
                ->constrained('infrastruktur_siks')
                ->cascadeOnDelete();

            $table->string('semester');
            $table->string('tahun_ajaran', 20);

            $table->decimal('nilai', 5, 2);
            $table->text('keterangan')->nullable();

            $table->timestamps();

            $table->unique([
                'santri_id',
                'mata_pelajaran_id',
                'semester',
                'tahun_ajaran'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nilai');
    }
};