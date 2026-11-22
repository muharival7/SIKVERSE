<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('riwayat_perubahan_absensi', function (Blueprint $table): void {
            $table->id();

            $table->string('jenis_perubahan', 40);
            $table->foreignId('absensi_sik_id')
                ->nullable()
                ->constrained('absensi_sik')
                ->nullOnDelete();
            $table->foreignId('pertemuan_id')
                ->nullable()
                ->constrained('pertemuan')
                ->nullOnDelete();
            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignId('instruktur_id')
                ->nullable()
                ->constrained('infrastruktur_siks')
                ->nullOnDelete();

            $table->string('status_sebelumnya', 30)->nullable();
            $table->string('status_sesudahnya', 30)->nullable();
            $table->date('tanggal_sebelumnya')->nullable();
            $table->date('tanggal_sesudahnya')->nullable();
            $table->text('alasan_perubahan')->nullable();

            $table->timestamps();
            $table->index('jenis_perubahan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riwayat_perubahan_absensi');
    }
};
