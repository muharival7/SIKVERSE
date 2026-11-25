<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLE = 'nilai';

    private const OLD_EXAM_UNIQUE = 'nilai_ujian_santri_kegiatan_unique';

    private const NEW_EXAM_UNIQUE = 'nilai_ujian_santri_mapel_semester_tahun_unique';

    private const DAILY_UNIQUE = 'nilai_harian_santri_mapel_semester_tahun_unique';

    /** @var array<int, string> */
    private const NEW_EXAM_COLUMNS = [
        'ujian_santri_id',
        'ujian_mata_pelajaran_id',
        'ujian_semester',
        'ujian_tahun_ajaran',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $this->assertSourceColumns();
        $this->assertNoDuplicateExamGradesForNewKey();
        $this->ensureExamGeneratedColumns();
        $this->assertExamGeneratedColumns();
        $this->ensureNewExamUniqueIndex();
        $this->assertIndex(self::NEW_EXAM_UNIQUE, self::NEW_EXAM_COLUMNS, true, 'Unique ujian baru');
        $this->dropOldExamUniqueIndex();
        $this->assertDailyUniqueStillPresent();
    }

    /**
     * Reverse the migrations.
     *
     * Forward-only: mengganti unique ujian menyentuh constraint integritas data.
     * Mengembalikan constraint lama berisiko bentrok dengan data/nilai baru
     * berbasis nama_kegiatan bebas (kegiatan_id NULL) dan dengan kondisi
     * partial DDL MariaDB. Perbaikan/rollback skema harus lewat migration
     * forward-fix terpisah setelah audit data.
     */
    public function down(): void
    {
        // Forward-only by project safety policy for this stage.
    }

    private function assertSourceColumns(): void
    {
        foreach (['santri_id', 'mata_pelajaran_id', 'semester', 'tahun_ajaran', 'jenis_nilai'] as $column) {
            if (! Schema::hasColumn(self::TABLE, $column)) {
                throw new RuntimeException('Migration dihentikan: kolom sumber nilai.'.$column.' tidak ditemukan.');
            }
        }
    }

    private function assertNoDuplicateExamGradesForNewKey(): void
    {
        $table = $this->quote(self::TABLE);
        $jenis = $this->quote('jenis_nilai');
        $santri = $this->quote('santri_id');
        $mapel = $this->quote('mata_pelajaran_id');
        $semester = $this->quote('semester');
        $tahun = $this->quote('tahun_ajaran');

        $sql = 'SELECT COUNT(*) AS c FROM ('
            .'SELECT 1 FROM '.$table
            .' WHERE '.$jenis." = 'Ujian'"
            .' GROUP BY '.$santri.', '.$mapel.', TRIM('.$semester.'), TRIM('.$tahun.')'
            .' HAVING COUNT(*) > 1) AS duplicates';

        $count = (int) DB::selectOne($sql)->c;

        if ($count > 0) {
            throw new RuntimeException(
                'Migration dihentikan: ditemukan '.$count
                .' grup duplikat nilai ujian untuk key baru (santri_id, mata_pelajaran_id, TRIM(semester), TRIM(tahun_ajaran)). '
                .'Tidak ada deduplikasi/penggabungan/penghapusan data otomatis.'
            );
        }
    }

    private function ensureExamGeneratedColumns(): void
    {
        $missing = [];

        foreach ($this->examColumnDefinitions() as $name => $definition) {
            if (Schema::hasColumn(self::TABLE, $name)) {
                $this->assertGeneratedColumnDefinition($name, $definition);

                continue;
            }

            $missing[$name] = $definition;
        }

        if ($missing === []) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) use ($missing): void {
            foreach ($missing as $name => $definition) {
                $column = $definition['kind'] === 'id'
                    ? $table->unsignedBigInteger($name)
                    : $table->string($name, $definition['length']);

                $column->nullable()->storedAs($definition['expression']);
            }
        });
    }

    /**
     * @return array<string, array{kind: 'id'|'string', length?: int, expression: string, types: array<int, string>}>
     */
    private function examColumnDefinitions(): array
    {
        return [
            'ujian_santri_id' => [
                'kind' => 'id',
                'expression' => "CASE WHEN jenis_nilai = 'Ujian' THEN santri_id ELSE NULL END",
                'types' => ['bigint(20) unsigned', 'bigint unsigned'],
            ],
            'ujian_mata_pelajaran_id' => [
                'kind' => 'id',
                'expression' => "CASE WHEN jenis_nilai = 'Ujian' THEN mata_pelajaran_id ELSE NULL END",
                'types' => ['bigint(20) unsigned', 'bigint unsigned'],
            ],
            'ujian_semester' => [
                'kind' => 'string',
                'length' => 255,
                'expression' => "CASE WHEN jenis_nilai = 'Ujian' THEN TRIM(semester) ELSE NULL END",
                'types' => ['varchar(255)', 'varchar'],
            ],
            'ujian_tahun_ajaran' => [
                'kind' => 'string',
                'length' => 20,
                'expression' => "CASE WHEN jenis_nilai = 'Ujian' THEN TRIM(tahun_ajaran) ELSE NULL END",
                'types' => ['varchar(20)', 'varchar'],
            ],
        ];
    }

    /**
     * @param  array{kind: 'id'|'string', length?: int, expression: string, types: array<int, string>}  $definition
     */
    private function assertGeneratedColumnDefinition(string $name, array $definition): void
    {
        $column = $this->columnDefinition($name);

        if ($column === null) {
            throw new RuntimeException('Migration dihentikan: kolom '.$name.' tidak ditemukan setelah pemeriksaan.');
        }

        if (! in_array($column['type'], $definition['types'], true) || ! $column['nullable'] || ! $column['generated']) {
            throw new RuntimeException(
                'Migration dihentikan: kolom '.$name.' sudah ada tetapi tidak sesuai (type='.$column['type']
                .', nullable='.($column['nullable'] ? 'yes' : 'no')
                .', generated='.($column['generated'] ? 'yes' : 'no').'). Kondisi parsial perlu audit manual.'
            );
        }

        $actualExpression = $column['expression'] === null ? '' : $this->normalizeExpression($column['expression']);
        $expectedExpression = $this->normalizeExpression($definition['expression']);

        if ($actualExpression !== $expectedExpression) {
            throw new RuntimeException(
                'Migration dihentikan: ekspresi generated column '.$name.' tidak sesuai. Kondisi parsial perlu audit manual.'
            );
        }
    }

    private function assertExamGeneratedColumns(): void
    {
        foreach ($this->examColumnDefinitions() as $name => $definition) {
            $this->assertGeneratedColumnDefinition($name, $definition);
        }
    }

    private function ensureNewExamUniqueIndex(): void
    {
        $existing = $this->indexDefinition(self::NEW_EXAM_UNIQUE);

        if ($existing !== null) {
            $this->assertIndex(self::NEW_EXAM_UNIQUE, self::NEW_EXAM_COLUMNS, true, 'Unique ujian baru');

            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->unique(self::NEW_EXAM_COLUMNS, self::NEW_EXAM_UNIQUE);
        });

        $this->assertIndex(self::NEW_EXAM_UNIQUE, self::NEW_EXAM_COLUMNS, true, 'Unique ujian baru');
    }

    private function dropOldExamUniqueIndex(): void
    {
        $existing = $this->indexDefinition(self::OLD_EXAM_UNIQUE);

        if ($existing === null) {
            return;
        }

        $this->assertIndex(self::OLD_EXAM_UNIQUE, ['santri_id', 'ujian_kegiatan_id'], true, 'Unique ujian lama');
        $this->assertIndex(self::NEW_EXAM_UNIQUE, self::NEW_EXAM_COLUMNS, true, 'Unique ujian baru');
        $this->assertDailyUniqueStillPresent();

        Schema::table(self::TABLE, function (Blueprint $table): void {
            $table->dropUnique(self::OLD_EXAM_UNIQUE);
        });
    }

    private function assertDailyUniqueStillPresent(): void
    {
        $expectedColumns = ['harian_santri_id', 'harian_mata_pelajaran_id', 'harian_semester', 'harian_tahun_ajaran'];
        $expectedDefinitions = [
            'harian_santri_id' => [
                'types' => ['bigint(20) unsigned', 'bigint unsigned'],
                'expression' => "CASE WHEN jenis_nilai = 'Ujian' THEN NULL ELSE santri_id END",
            ],
            'harian_mata_pelajaran_id' => [
                'types' => ['bigint(20) unsigned', 'bigint unsigned'],
                'expression' => "CASE WHEN jenis_nilai = 'Ujian' THEN NULL ELSE mata_pelajaran_id END",
            ],
            'harian_semester' => [
                'types' => ['varchar(255)', 'varchar'],
                'expression' => "CASE WHEN jenis_nilai = 'Ujian' THEN NULL ELSE semester END",
            ],
            'harian_tahun_ajaran' => [
                'types' => ['varchar(20)', 'varchar'],
                'expression' => "CASE WHEN jenis_nilai = 'Ujian' THEN NULL ELSE tahun_ajaran END",
            ],
        ];

        foreach ($expectedColumns as $name) {
            if (! Schema::hasColumn(self::TABLE, $name)) {
                throw new RuntimeException(
                    'Migration dihentikan: kondisi unique harian tidak dapat dibuktikan aman karena kolom '.$name
                    .' tidak ditemukan. Unique harian harus dipertahankan; perlu audit manual.'
                );
            }

            $definition = $this->columnDefinition($name);

            if ($definition === null || ! $definition['nullable'] || ! $definition['generated'] || ! in_array($definition['type'], $expectedDefinitions[$name]['types'], true)) {
                throw new RuntimeException(
                    'Migration dihentikan: kondisi unique harian tidak dapat dibuktikan aman karena kolom '.$name
                    .' tidak sesuai definisi generated yang diharapkan. Unique harian harus dipertahankan; perlu audit manual.'
                );
            }

            $actualExpression = $definition['expression'] === null ? '' : $this->normalizeExpression($definition['expression']);
            $expectedExpression = $this->normalizeExpression($expectedDefinitions[$name]['expression']);

            if ($actualExpression !== $expectedExpression) {
                throw new RuntimeException(
                    'Migration dihentikan: kondisi unique harian tidak dapat dibuktikan aman karena ekspresi kolom '.$name
                    .' tidak sesuai. Unique harian harus dipertahankan; perlu audit manual.'
                );
            }
        }

        $indexDefinition = $this->indexDefinition(self::DAILY_UNIQUE);

        if ($indexDefinition === null) {
            throw new RuntimeException(
                'Migration dihentikan: kondisi unique harian tidak dapat dibuktikan aman karena index '.self::DAILY_UNIQUE
                .' tidak ditemukan. Unique harian harus dipertahankan; perlu audit manual.'
            );
        }

        $this->assertIndex(
            self::DAILY_UNIQUE,
            $expectedColumns,
            true,
            'Migration dihentikan: kondisi unique harian tidak dapat dibuktikan aman'
        );
    }

    /**
     * @param  array<int, string>  $expectedColumns
     */
    private function assertIndex(string $name, array $expectedColumns, bool $expectedUnique, string $context): void
    {
        $definition = $this->indexDefinition($name);

        if ($definition === null) {
            throw new RuntimeException($context.': index '.$name.' tidak ditemukan.');
        }

        if ($definition['columns'] !== $expectedColumns || $definition['unique'] !== $expectedUnique) {
            throw new RuntimeException(
                $context.': definisi index '.$name.' tidak sesuai. Kolom: '
                .implode(', ', $definition['columns']).'; unique: '.($definition['unique'] ? 'ya' : 'tidak').'.'
            );
        }
    }

    private function driver(): string
    {
        return Schema::getConnection()->getDriverName();
    }

    private function quote(string $identifier): string
    {
        $character = $this->driver() === 'mysql' ? '`' : '"';

        return $character.str_replace($character, $character.$character, $identifier).$character;
    }

    private function normalizeExpression(string $expression): string
    {
        return str_replace(['`', '"', ' ', "\t", "\n", "\r"], '', strtolower($expression));
    }

    /**
     * @return array{type: string, nullable: bool, generated: bool, expression: ?string}|null
     */
    private function columnDefinition(string $name): ?array
    {
        if ($this->driver() === 'mysql') {
            $row = DB::selectOne(
                'SELECT COLUMN_TYPE, IS_NULLABLE, EXTRA, GENERATION_EXPRESSION '
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
                'expression' => $row->GENERATION_EXPRESSION === null ? null : (string) $row->GENERATION_EXPRESSION,
            ];
        }

        foreach (DB::select("PRAGMA table_info('".self::TABLE."')") as $row) {
            if ($row->name === $name) {
                return [
                    'type' => strtolower((string) $row->type),
                    'nullable' => (int) $row->notnull === 0,
                    'generated' => false,
                    'expression' => null,
                ];
            }
        }

        return null;
    }

    /**
     * @return array{columns: array<int, string>, unique: bool}|null
     */
    private function indexDefinition(string $name): ?array
    {
        if ($this->driver() === 'mysql') {
            $rows = DB::select(
                'SELECT COLUMN_NAME, NON_UNIQUE FROM information_schema.STATISTICS '
                .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ? ORDER BY SEQ_IN_INDEX',
                [self::TABLE, $name]
            );

            if ($rows === []) {
                return null;
            }

            return [
                'columns' => array_map(static fn ($row): string => (string) $row->COLUMN_NAME, $rows),
                'unique' => (int) $rows[0]->NON_UNIQUE === 0,
            ];
        }

        $found = null;

        foreach (DB::select("PRAGMA index_list('".self::TABLE."')") as $index) {
            if ($index->name === $name) {
                $found = $index;
                break;
            }
        }

        if ($found === null) {
            return null;
        }

        $info = DB::select('PRAGMA index_info('.$this->quote($name).')');
        usort($info, static fn ($a, $b): int => $a->seqno <=> $b->seqno);

        return [
            'columns' => array_map(static fn ($row): string => (string) $row->name, $info),
            'unique' => (int) $found->unique === 1,
        ];
    }
};
