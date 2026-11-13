
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal', function (Blueprint $table) {
            $table->id();

            $table->foreignId('mata_pelajaran_id')
                ->constrained('mata_pelajaran')
                ->cascadeOnDelete();

            $table->foreignId('infrastruktur_sik_id')
                ->constrained('infrastruktur_siks')
                ->cascadeOnDelete();

            $table->string('hari');
            $table->time('waktu_mulai');
            $table->time('waktu_selesai');
            $table->string('ruang');
            $table->boolean('status')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal');
    }
};
