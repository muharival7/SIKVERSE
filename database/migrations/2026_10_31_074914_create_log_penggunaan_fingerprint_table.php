
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_penggunaan_fingerprint', function (Blueprint $table) {
            $table->id();

            $table->foreignId('fingerprint_id')
                ->constrained('fingerprints')
                ->cascadeOnDelete();

            $table->dateTime('waktu_scan');
            $table->string('status');
            $table->text('keterangan')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_penggunaan_fingerprint');
    }
};
