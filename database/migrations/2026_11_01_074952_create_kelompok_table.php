
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelompok', function (Blueprint $table) {
            $table->id();

            $table->foreignId('periode_sik_id')
                ->constrained('periode_sik')
                ->restrictOnDelete();

            $table->foreignId('instruktur_id')
                ->constrained('infrastruktur_siks')
                ->restrictOnDelete();

            $table->string('nama_kelompok', 100);
            $table->text('keterangan')->nullable();
            $table->string('status', 20)->default('Aktif');

            $table->timestamps();

            $table->unique(['periode_sik_id', 'nama_kelompok']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelompok');
    }
};
