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
        Schema::table('infrastruktur_siks', function (Blueprint $table): void {
            $table->string('nip')->nullable()->unique()->after('nama');
            $table->string('program_studi')->nullable()->after('nip');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('infrastruktur_siks', function (Blueprint $table): void {
            $table->dropUnique(['nip']);
            $table->dropColumn(['nip', 'program_studi']);
        });
    }
};
