<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'nilai';

    private const COLUMN = 'nama_kegiatan';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (Schema::hasColumn(self::TABLE, self::COLUMN)) {
            $this->assertNamaKegiatanColumn();

            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->string(self::COLUMN, 150)->nullable()->after('kegiatan_id');
        });
    }

    private function assertNamaKegiatanColumn(): void
    {
        $column = $this->columnDefinition(self::COLUMN);

        if ($column === null) {
            throw new RuntimeException('Migration dihentikan: kolom '.self::COLUMN.' tidak ditemukan setelah pemeriksaan.');
        }

        $type = $column['type'];
        $isVarchar150 = $type === 'varchar(150)' || preg_match('/^varchar\(150\)$/', $type) === 1;

        if (! $isVarchar150 || ! $column['nullable'] || $column['generated']) {
            throw new RuntimeException(
                'Migration dihentikan: kolom '.self::COLUMN.' sudah ada tetapi tidak sesuai (type='.$type
                .', nullable='.($column['nullable'] ? 'yes' : 'no')
                .', generated='.($column['generated'] ? 'yes' : 'no')
                .', expected=varchar(150) nullable non-generated). Kondisi parsial perlu audit manual.'
            );
        }
    }

    /**
     * @return array{type: string, nullable: bool, generated: bool}|null
     */
    private function columnDefinition(string $name): ?array
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            $row = DB::selectOne(
                'SELECT COLUMN_TYPE, IS_NULLABLE, EXTRA '
                .'FROM information_schema.COLUMNS '
                .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
                [self::TABLE, $name]
            );

            if ($row === null) {
                return null;
            }

            return [
                'type' => strtolower((string) $row->COLUMN_TYPE),
                'nullable' => $row->IS_NULLABLE === 'YES',
                'generated' => str_contains(strtoupper((string) $row->EXTRA), 'GENERATED'),
            ];
        }

        foreach (DB::select("PRAGMA table_info('".self::TABLE."')") as $row) {
            if ($row->name === $name) {
                return [
                    'type' => strtolower((string) $row->type),
                    'nullable' => (int) $row->notnull === 0,
                    'generated' => false,
                ];
            }
        }

        return null;
    }

    /**
     * Reverse the migrations.
     *
     * Forward-only: kolom additive ini dibiarkan apa adanya untuk menghindari
     * penghapusan data/struktur pada lingkungan yang mungkin sudah bergantung
     * padanya. Rollback skema dilakukan lewat migration forward-fix terpisah
     * bila benar-benar diperlukan.
     */
    public function down(): void
    {
        // Forward-only by project safety policy for this stage.
    }
};
