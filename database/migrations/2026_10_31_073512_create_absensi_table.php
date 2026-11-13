
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('absensi', function (Blueprint $table) {
            $table->id();

            $table->foreignId('santri_id')
                ->constrained('santris')
                ->cascadeOnDelete();

            $table->foreignId('jadwal_id')
                ->constrained('jadwal')
                ->cascadeOnDelete();

            $table->date('tanggal');
            $table->time('waktu_masuk')->nullable();
            $table->string('metode_absensi')->default('Fingerprint');
            $table->enum('status', ['Hadir', 'Izin', 'Sakit', 'Alpha']);
            $table->text('keterangan')->nullable();

            $table->timestamps();

            $table->unique(['santri_id', 'jadwal_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('absensi');
    }
};
