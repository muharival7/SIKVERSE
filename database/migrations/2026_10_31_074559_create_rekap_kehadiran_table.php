
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rekap_kehadiran', function (Blueprint $table) {
            $table->id();

            $table->foreignId('santri_id')
                ->constrained('santris')
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('total_pertemuan')->default(0);
            $table->unsignedSmallInteger('jumlah_hadir')->default(0);
            $table->unsignedSmallInteger('jumlah_izin')->default(0);
            $table->unsignedSmallInteger('jumlah_sakit')->default(0);
            $table->unsignedSmallInteger('jumlah_alpha')->default(0);

            $table->decimal('persentase_kehadiran', 5, 2)->default(0);
            $table->string('status_ujian')->default('Belum Memenuhi');

            $table->string('tahun_ajaran', 20);
            $table->string('semester');

            $table->timestamps();

            $table->unique([
                'santri_id',
                'tahun_ajaran',
                'semester'
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rekap_kehadiran');
    }
};
