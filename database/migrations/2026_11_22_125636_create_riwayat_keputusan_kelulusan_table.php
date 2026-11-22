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
        Schema::create('riwayat_keputusan_kelulusan', function (Blueprint $table): void {
            $table->id();

            // Identifier sengaja disimpan tanpa foreign key agar keputusan
            // tetap menjadi arsip walaupun catatan kelulusan dihapus.
            $table->unsignedBigInteger('kelulusan_sik_id');
            $table->foreignId('actor_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('status_sebelumnya', 30)->nullable();
            $table->string('status_sesudahnya', 30);
            $table->text('alasan')->nullable();

            $table->timestamps();
            $table->index('kelulusan_sik_id');
            $table->index(['kelulusan_sik_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('riwayat_keputusan_kelulusan');
    }
};
