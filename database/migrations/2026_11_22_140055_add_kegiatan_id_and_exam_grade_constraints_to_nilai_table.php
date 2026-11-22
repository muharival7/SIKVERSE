<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_UNIQUE_INDEX = 'nilai_santri_id_mata_pelajaran_id_semester_tahun_ajaran_unique';

    private const DAILY_UNIQUE_INDEX = 'nilai_harian_duplicate_unique';

    private const EXAM_UNIQUE_INDEX = 'nilai_ujian_santri_kegiatan_unique';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('nilai', function (Blueprint $table): void {
            $table->foreignId('kegiatan_id')
                ->nullable()
                ->after('mata_pelajaran_id')
                ->constrained('kegiatan')
                ->restrictOnDelete();
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement(
                'ALTER TABLE `nilai` '
                .'ADD COLUMN `ujian_kegiatan_id` BIGINT UNSIGNED GENERATED ALWAYS AS '
                .'(CASE WHEN `jenis_nilai` = \'Ujian\' THEN `kegiatan_id` ELSE NULL END) STORED, '
                .'ADD COLUMN `harian_duplicate_key` VARCHAR(191) GENERATED ALWAYS AS '
                .'(CASE WHEN `jenis_nilai` = \'Ujian\' THEN NULL '
                .'ELSE CONCAT_WS(\'|\', `santri_id`, `mata_pelajaran_id`, `semester`, `tahun_ajaran`) END) STORED'
            );
        } elseif ($driver === 'sqlite') {
            DB::statement(
                'ALTER TABLE "nilai" ADD COLUMN "ujian_kegiatan_id" BIGINT GENERATED ALWAYS AS '
                .'(CASE WHEN "jenis_nilai" = \'Ujian\' THEN "kegiatan_id" ELSE NULL END) STORED'
            );
            DB::statement(
                'ALTER TABLE "nilai" ADD COLUMN "harian_duplicate_key" VARCHAR(191) GENERATED ALWAYS AS '
                .'(CASE WHEN "jenis_nilai" = \'Ujian\' THEN NULL '
                .'ELSE CAST("santri_id" AS TEXT) || \'|\' || CAST("mata_pelajaran_id" AS TEXT) '
                .'|| \'|\' || COALESCE("semester", \'\') || \'|\' || COALESCE("tahun_ajaran", \'\') END) STORED'
            );
        } else {
            throw new RuntimeException('Migration nilai ujian tidak mendukung driver '.$driver.'.');
        }

        Schema::table('nilai', function (Blueprint $table): void {
            $table->dropUnique(self::LEGACY_UNIQUE_INDEX);
            $table->unique('harian_duplicate_key', self::DAILY_UNIQUE_INDEX);
            $table->unique(['santri_id', 'ujian_kegiatan_id'], self::EXAM_UNIQUE_INDEX);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('nilai', function (Blueprint $table): void {
            $table->dropUnique(self::EXAM_UNIQUE_INDEX);
            $table->dropUnique(self::DAILY_UNIQUE_INDEX);
            $table->unique([
                'santri_id',
                'mata_pelajaran_id',
                'semester',
                'tahun_ajaran',
            ], self::LEGACY_UNIQUE_INDEX);
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `nilai` DROP COLUMN `ujian_kegiatan_id`, DROP COLUMN `harian_duplicate_key`');
        } elseif ($driver === 'sqlite') {
            DB::statement('ALTER TABLE "nilai" DROP COLUMN "ujian_kegiatan_id"');
            DB::statement('ALTER TABLE "nilai" DROP COLUMN "harian_duplicate_key"');
        }

        Schema::table('nilai', function (Blueprint $table): void {
            $table->dropColumn('kegiatan_id');
        });
    }
};
