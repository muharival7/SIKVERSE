
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sertifikat', function (Blueprint $table) {
            $table->id();

            $table->foreignId('santri_id')
                ->constrained('santris')
                ->cascadeOnDelete();

            $table->string('nomor_sertifikat')->unique();
            $table->string('file_sertifikat');
            $table->date('tanggal_terbit')->nullable();
            $table->text('keterangan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sertifikat');
    }
};
