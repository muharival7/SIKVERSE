<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_UNIQUE_INDEX = 'nilai_santri_id_mata_pelajaran_id_semester_tahun_ajaran_unique';

    private const SUPPORTING_INDEX = 'nilai_santri_id_supporting_index';

    private const DAILY_UNIQUE_INDEX = 'nilai_harian_santri_mapel_semester_tahun_unique';

    private const EXAM_UNIQUE_INDEX = 'nilai_ujian_santri_kegiatan_unique';

    private const SANTRI_FK = 'nilai_santri_id_foreign';

    /** @var array<int, string> */
    private const LEGACY_COLUMNS = ['santri_id', 'mata_pelajaran_id', 'semester', 'tahun_ajaran'];

    /** @var array<int, string> */
    private const DAILY_COLUMNS = [
        'harian_santri_id',
        'harian_mata_pelajaran_id',
        'harian_semester',
        'harian_tahun_ajaran',
    ];

    /** @var array<int, string> */
    private const EXAM_COLUMNS = ['santri_id', 'ujian_kegiatan_id'];

    /** @var array<int, string> */
    private const ID_TYPES = ['bigint(20) unsigned', 'bigint unsigned', 'bigint', 'integer'];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->assertPrerequisites();
        $this->addDailyGeneratedColumns();
        $this->assertDailyGeneratedColumns();

        $this->ensureIndex(self::SUPPORTING_INDEX, ['santri_id'], false, 'Index pendukung santri');
        $this->assertSantriForeignKey();

        $this->assertNoDuplicateDailyGrades();
        $this->assertNoDuplicateExamGrades();
        $this->assertExamRowsHaveKegiatan();

        $this->ensureIndex(self::DAILY_UNIQUE_INDEX, self::DAILY_COLUMNS, true, 'Unique nilai harian');
        $this->ensureIndex(self::EXAM_UNIQUE_INDEX, self::EXAM_COLUMNS, true, 'Unique nilai ujian');

        $this->dropLegacyUniqueIndex();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Sengaja tidak melakukan apa pun. Sesuai kebijakan proyek, pembalikan
        // otomatis dapat mengembalikan index lama ke atas partial state yang
        // sudah diperbaiki dan merusak integritas foreign key santri.
    }

    private function assertPrerequisites(): void
    {
        if (! Schema::hasTable('nilai')) {
            throw new RuntimeException('Migration dihentikan: tabel nilai tidak ditemukan.');
        }

        $this->assertColumn('santri_id', self::ID_TYPES, false, 'Prasyarat');
        $this->assertColumn('mata_pelajaran_id', self::ID_TYPES, false, 'Prasyarat');
        $this->assertColumn('semester', ['varchar(255)', 'varchar'], false, 'Prasyarat');
        $this->assertColumn('tahun_ajaran', ['varchar(20)', 'varchar'], false, 'Prasyarat');
        $this->assertColumn('jenis_nilai', ['varchar(20)', 'varchar'], true, 'Prasyarat');
        $this->assertColumn('kegiatan_id', self::ID_TYPES, true, 'Prasyarat');
        $this->assertColumn('harian_duplicate_key', ['varchar(191)', 'varchar'], true, 'Prasyarat');

        $this->assertGeneratedExpression(
            'ujian_kegiatan_id',
            "CASE WHEN jenis_nilai = 'Ujian' THEN kegiatan_id ELSE NULL END"
        );
        $this->assertGeneratedExpression(
            'harian_duplicate_key',
            "CASE WHEN jenis_nilai = 'Ujian' THEN NULL ELSE CONCAT_WS('|', santri_id, mata_pelajaran_id, semester, tahun_ajaran) END"
        );
        $this->assertSantriForeignKey();

        $legacyExists = $this->indexDefinition(self::LEGACY_UNIQUE_INDEX) !== null;
        $dailyExists = $this->indexDefinition(self::DAILY_UNIQUE_INDEX) !== null;
        $examExists = $this->indexDefinition(self::EXAM_UNIQUE_INDEX) !== null;

        if (! $legacyExists && (! $dailyExists || ! $examExists)) {
            throw new RuntimeException(
                'Migration dihentikan: index lama tidak ditemukan dan kedua index baru belum lengkap. Kondisi partial tidak dikenali.'
            );
        }
    }

    /**
     * @param  array<int, string>  $acceptedTypes
     */
    private function assertColumn(string $name, array $acceptedTypes, bool $nullable, string $context): void
    {
        $definition = $this->columnDefinition($name);

        if ($definition === null) {
            throw new RuntimeException($context.': kolom '.$name.' tidak ditemukan pada tabel nilai.');
        }

        if (! in_array($definition['type'], $acceptedTypes, true)) {
            throw new RuntimeException(
                $context.': tipe kolom '.$name.' = '.$definition['type'].' tidak sesuai. Diterima: '.implode(', ', $acceptedTypes).'.'
            );
        }

        if ($definition['nullable'] !== $nullable) {
            throw new RuntimeException($context.': status nullable kolom '.$name.' tidak sesuai rancangan.');
        }
    }

    private function assertGeneratedExpression(string $name, string $expectedExpression): void
    {
        $definition = $this->columnDefinition($name);

        if ($definition === null) {
            throw new RuntimeException('Migration dihentikan: kolom generated '.$name.' tidak ditemukan.');
        }

        if ($this->driver() === 'mysql') {
            if (! $definition['generated']) {
                throw new RuntimeException('Migration dihentikan: kolom '.$name.' bukan generated column.');
            }

            $actual = $this->normalizeExpression((string) $definition['expression']);
            $expected = $this->normalizeExpression($expectedExpression);

            if ($actual !== $expected) {
                throw new RuntimeException(
                    'Migration dihentikan: ekspresi generated column '.$name.' tidak sesuai rancangan.'
                );
            }

            return;
        }

        $row = DB::selectOne("SELECT sql FROM sqlite_master WHERE type = 'table' AND name = 'nilai'");
        $sql = strtolower((string) ($row?->sql ?? ''));

        if (! str_contains($sql, strtolower($this->quote($name))) || ! str_contains($sql, 'generated always')) {
            throw new RuntimeException(
                'Migration dihentikan: kolom '.$name.' tidak terverifikasi sebagai generated column.'
            );
        }
    }

    private function addDailyGeneratedColumns(): void
    {
        /** @var array<int, array{0: string, 1: string, 2: ?int, 3: string}> $specifications */
        $specifications = [
            [
                'harian_santri_id',
                'id',
                null,
                "CASE WHEN jenis_nilai = 'Ujian' THEN NULL ELSE santri_id END",
            ],
            [
                'harian_mata_pelajaran_id',
                'id',
                null,
                "CASE WHEN jenis_nilai = 'Ujian' THEN NULL ELSE mata_pelajaran_id END",
            ],
            [
                'harian_semester',
                'string',
                255,
                "CASE WHEN jenis_nilai = 'Ujian' THEN NULL ELSE semester END",
            ],
            [
                'harian_tahun_ajaran',
                'string',
                20,
                "CASE WHEN jenis_nilai = 'Ujian' THEN NULL ELSE tahun_ajaran END",
            ],
        ];

        $missing = array_values(array_filter(
            $specifications,
            fn (array $specification): bool => $this->columnDefinition($specification[0]) === null
        ));

        if ($missing === []) {
            return;
        }

        // Charset dan collation tidak didefinisikan eksplisit agar mewarisi
        // default tabel nilai, yaitu utf8mb4 / utf8mb4_unicode_ci.
        Schema::table('nilai', function (Blueprint $table) use ($missing): void {
            foreach ($missing as [$name, $kind, $length, $expression]) {
                $column = $kind === 'id'
                    ? $table->unsignedBigInteger($name)
                    : $table->string($name, $length);

                $column->nullable()->storedAs($expression);
            }
        });
    }

    private function assertDailyGeneratedColumns(): void
    {
        /** @var array<string, array{0: array<int, string>, 1: string}> $expected */
        $expected = [
            'harian_santri_id' => [
                self::ID_TYPES,
                "CASE WHEN jenis_nilai = 'Ujian' THEN NULL ELSE santri_id END",
            ],
            'harian_mata_pelajaran_id' => [
                self::ID_TYPES,
                "CASE WHEN jenis_nilai = 'Ujian' THEN NULL ELSE mata_pelajaran_id END",
            ],
            'harian_semester' => [
                ['varchar(255)', 'varchar'],
                "CASE WHEN jenis_nilai = 'Ujian' THEN NULL ELSE semester END",
            ],
            'harian_tahun_ajaran' => [
                ['varchar(20)', 'varchar'],
                "CASE WHEN jenis_nilai = 'Ujian' THEN NULL ELSE tahun_ajaran END",
            ],
        ];

        foreach ($expected as $name => [$types, $expression]) {
            $this->assertColumn($name, $types, true, 'Generated column');
            $this->assertGeneratedExpression($name, $expression);
        }
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function ensureIndex(string $name, array $columns, bool $unique, string $context): void
    {
        if ($this->indexDefinition($name) !== null) {
            $this->assertIndex($name, $columns, $unique, $context);

            return;
        }

        Schema::table('nilai', function (Blueprint $table) use ($name, $columns, $unique): void {
            if ($unique) {
                $table->unique($columns, $name);
            } else {
                $table->index($columns, $name);
            }
        });

        $this->assertIndex($name, $columns, $unique, $context);
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

    private function assertSantriForeignKey(): void
    {
        if ($this->driver() === 'mysql') {
            $row = DB::selectOne(
                'SELECT REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME '
                .'FROM information_schema.KEY_COLUMN_USAGE '
                .'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? '
                .'AND CONSTRAINT_NAME = ? AND COLUMN_NAME = ?',
                ['nilai', self::SANTRI_FK, 'santri_id']
            );

            if ($row === null || $row->REFERENCED_TABLE_NAME !== 'santris' || $row->REFERENCED_COLUMN_NAME !== 'id') {
                throw new RuntimeException(
                    'Migration dihentikan: FK nilai_santri_id_foreign tidak ditemukan atau tidak merujuk ke santris.id.'
                );
            }

            return;
        }

        foreach (DB::select("PRAGMA foreign_key_list('nilai')") as $row) {
            if ($row->table === 'santris' && $row->from === 'santri_id' && $row->to === 'id') {
                return;
            }
        }

        throw new RuntimeException(
            'Migration dihentikan: FK nilai_santri_id_foreign tidak ditemukan atau tidak merujuk ke santris.id.'
        );
    }

    private function assertNoDuplicateDailyGrades(): void
    {
        $table = $this->quote('nilai');
        $santri = $this->quote('harian_santri_id');
        $mapel = $this->quote('harian_mata_pelajaran_id');
        $semester = $this->quote('harian_semester');
        $tahunAjaran = $this->quote('harian_tahun_ajaran');

        $sql = 'SELECT COUNT(*) AS c FROM ('
            .'SELECT 1 FROM '.$table
            .' WHERE '.$santri.' IS NOT NULL'
            .' GROUP BY '.$santri.', '.$mapel.', '.$semester.', '.$tahunAjaran
            .' HAVING COUNT(*) > 1) AS duplicates';

        $count = (int) DB::selectOne($sql)->c;

        if ($count > 0) {
            throw new RuntimeException(
                'Migration dihentikan: ditemukan '.$count.' grup duplikat nilai harian. Tidak ada deduplikasi otomatis.'
            );
        }
    }

    private function assertNoDuplicateExamGrades(): void
    {
        $table = $this->quote('nilai');
        $kegiatan = $this->quote('ujian_kegiatan_id');
        $santri = $this->quote('santri_id');

        $sql = 'SELECT COUNT(*) AS c FROM ('
            .'SELECT 1 FROM '.$table
            .' WHERE '.$kegiatan.' IS NOT NULL'
            .' GROUP BY '.$santri.', '.$kegiatan
            .' HAVING COUNT(*) > 1) AS duplicates';

        $count = (int) DB::selectOne($sql)->c;

        if ($count > 0) {
            throw new RuntimeException(
                'Migration dihentikan: ditemukan '.$count.' grup duplikat nilai ujian. Tidak ada deduplikasi otomatis.'
            );
        }
    }

    private function assertExamRowsHaveKegiatan(): void
    {
        $table = $this->quote('nilai');
        $jenis = $this->quote('jenis_nilai');
        $kegiatan = $this->quote('kegiatan_id');

        $count = (int) DB::selectOne(
            'SELECT COUNT(*) AS c FROM '.$table
            .' WHERE '.$jenis." = 'Ujian' AND ".$kegiatan.' IS NULL'
        )->c;

        if ($count > 0) {
            throw new RuntimeException(
                'Migration dihentikan: ditemukan '.$count.' baris ujian dengan kegiatan_id bernilai NULL.'
            );
        }
    }

    private function dropLegacyUniqueIndex(): void
    {
        if ($this->indexDefinition(self::LEGACY_UNIQUE_INDEX) === null) {
            return;
        }

        $this->assertIndex(self::LEGACY_UNIQUE_INDEX, self::LEGACY_COLUMNS, true, 'Index lama');
        $this->assertSantriForeignKey();
        $this->assertIndex(self::SUPPORTING_INDEX, ['santri_id'], false, 'Index pendukung santri');
        $this->assertIndex(self::DAILY_UNIQUE_INDEX, self::DAILY_COLUMNS, true, 'Unique nilai harian');
        $this->assertIndex(self::EXAM_UNIQUE_INDEX, self::EXAM_COLUMNS, true, 'Unique nilai ujian');

        Schema::table('nilai', function (Blueprint $table): void {
            $table->dropUnique(self::LEGACY_UNIQUE_INDEX);
        });
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
        return str_replace(
            ['`', '"', ' ', "\t", "\n", "\r"],
            '',
            strtolower($expression)
        );
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
                ['nilai', $name]
            );

            if ($row === null) {
                return null;
            }

            return [
                'type' => strtolower($row->COLUMN_TYPE),
                'nullable' => $row->IS_NULLABLE === 'YES',
                'generated' => str_contains(strtoupper((string) $row->EXTRA), 'GENERATED'),
                'expression' => $row->GENERATION_EXPRESSION,
            ];
        }

        foreach (DB::select("PRAGMA table_info('nilai')") as $row) {
            if ($row->name === $name) {
                return [
                    'type' => strtolower($row->type),
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
                ['nilai', $name]
            );

            if ($rows === []) {
                return null;
            }

            return [
                'columns' => array_map(static fn ($row): string => $row->COLUMN_NAME, $rows),
                'unique' => (int) $rows[0]->NON_UNIQUE === 0,
            ];
        }

        $found = null;

        foreach (DB::select("PRAGMA index_list('nilai')") as $index) {
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
            'columns' => array_map(static fn ($row): string => $row->name, $info),
            'unique' => (int) $found->unique === 1,
        ];
    }
};
