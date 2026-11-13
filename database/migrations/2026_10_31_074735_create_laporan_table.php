
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laporan', function (Blueprint $table) {
            $table->id();

            $table->unsignedSmallInteger('total_santri')->default(0);
            $table->unsignedSmallInteger('jumlah_hadir')->default(0);
            $table->unsignedSmallInteger('jumlah_tidak_hadir')->default(0);
            $table->unsignedSmallInteger('jumlah_izin')->default(0);
            $table->unsignedSmallInteger('jumlah_sakit')->default(0);

            $table->decimal('persentase_kehadiran', 5, 2)->default(0);

            $table->date('tanggal_awal');
            $table->date('tanggal_akhir');

            $table->string('tahun_ajaran', 20);
            $table->string('semester');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laporan');
    }
};
