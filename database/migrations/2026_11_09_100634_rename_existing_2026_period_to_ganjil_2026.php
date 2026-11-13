<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $oldPeriodExists = DB::table('periode_sik')
            ->where('nama_periode', 'periode sik 2026')
            ->exists();
        $newPeriodExists = DB::table('periode_sik')
            ->where('nama_periode', 'ganjil_2026')
            ->exists();

        if ($oldPeriodExists && ! $newPeriodExists) {
            DB::table('periode_sik')
                ->where('nama_periode', 'periode sik 2026')
                ->update(['nama_periode' => 'ganjil_2026']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $currentNameExists = DB::table('periode_sik')
            ->where('nama_periode', 'ganjil_2026')
            ->exists();
        $previousNameExists = DB::table('periode_sik')
            ->where('nama_periode', 'periode sik 2026')
            ->exists();

        if ($currentNameExists && ! $previousNameExists) {
            DB::table('periode_sik')
                ->where('nama_periode', 'ganjil_2026')
                ->update(['nama_periode' => 'periode sik 2026']);
        }
    }
};
