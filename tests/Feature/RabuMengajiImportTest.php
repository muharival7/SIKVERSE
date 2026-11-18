<?php

use App\Enums\UserRole;
use App\Models\AbsensiSik;
use App\Models\AnggotaPeriodeSik;
use App\Models\InfrastrukturSik;
use App\Models\Kegiatan;
use App\Models\Kelompok;
use App\Models\PeriodeSik;
use App\Models\Pertemuan;
use App\Models\Santri;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function makeRabuMengajiImportFixture(): array
{
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $instructorUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $instructor = InfrastrukturSik::query()->create([
        'user_id' => $instructorUser->id,
        'nama' => 'Instruktur Mengaji',
        'status' => 'Aktif',
    ]);
    $period = PeriodeSik::query()->create([
        'nama_periode' => 'ganjil_mengaji_import',
        'tahun' => now()->year,
        'tanggal_mulai' => now()->startOfYear()->toDateString(),
        'tanggal_selesai' => now()->endOfYear()->toDateString(),
        'status' => 'Aktif',
    ]);
    $otherInstructorUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $otherInstructor = InfrastrukturSik::query()->create([
        'user_id' => $otherInstructorUser->id,
        'nama' => 'Instruktur Sains Import',
        'status' => 'Aktif',
    ]);
    $group = Kelompok::query()->create([
        'periode_sik_id' => $period->id,
        'instruktur_id' => $instructor->id,
        'nama_kelompok' => 'Sistem Informasi Import',
        'status' => 'Aktif',
    ]);
    $otherGroup = Kelompok::query()->create([
        'periode_sik_id' => $period->id,
        'instruktur_id' => $otherInstructor->id,
        'nama_kelompok' => 'Sains Import',
        'status' => 'Aktif',
    ]);

    $activity = Kegiatan::query()->create([
        'periode_sik_id' => $period->id,
        'kelompok_id' => null,
        'nama_kegiatan' => 'Rabu Mengaji Import',
        'jenis_kegiatan' => 'Aula',
        'status' => 'Aktif',
    ]);

    $santri1 = Santri::query()->create([
        'nomor_induk' => 'MGJ-001',
        'nama' => 'Santri Mengaji Satu',
        'jenis_kelamin' => 'Laki-laki',
        'status' => 'Aktif',
    ]);
    $membership1 = AnggotaPeriodeSik::query()->create([
        'santri_id' => $santri1->id,
        'periode_sik_id' => $period->id,
        'kelompok_id' => $group->id,
        'status' => 'Aktif',
    ]);

    $santri2 = Santri::query()->create([
        'nomor_induk' => 'MGJ-002',
        'nama' => 'Santri Mengaji Dua',
        'jenis_kelamin' => 'Perempuan',
        'status' => 'Aktif',
    ]);
    $membership2 = AnggotaPeriodeSik::query()->create([
        'santri_id' => $santri2->id,
        'periode_sik_id' => $period->id,
        'kelompok_id' => $otherGroup->id,
        'status' => 'Aktif',
    ]);

    $meeting = Pertemuan::query()->create([
        'kegiatan_id' => $activity->id,
        'pertemuan_ke' => 1,
        'tanggal' => '2026-11-18',
        'status' => 'Terjadwal',
    ]);

    return compact(
        'admin',
        'instructorUser',
        'otherInstructorUser',
        'period',
        'group',
        'otherGroup',
        'activity',
        'santri1',
        'santri2',
        'membership1',
        'membership2',
        'meeting'
    );
}

function createXlsxFile(array $rows): string
{
    $temporaryPath = tempnam(sys_get_temp_dir(), 'sik-test-xlsx-');

    if ($temporaryPath === false) {
        throw new RuntimeException('Temporary file cannot be created.');
    }

    $archive = new ZipArchive;

    if ($archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        @unlink($temporaryPath);
        throw new RuntimeException('Cannot create zip archive.');
    }

    $mainNs = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $docRelNs = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    $sharedStrings = [];
    $sharedStringXml = '';

    foreach ($rows as $row) {
        foreach ($row as $cell) {
            if (! isset($sharedStrings[$cell])) {
                $sharedStrings[$cell] = count($sharedStrings);
            }
        }
    }

    if ($sharedStrings !== []) {
        $sstContent = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><sst xmlns="'.$mainNs.'" count="'.count($sharedStrings).'" uniqueCount="'.count($sharedStrings).'">';
        foreach ($sharedStrings as $string => $index) {
            $sstContent .= '<si><t>'.htmlspecialchars($string, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></si>';
        }
        $sstContent .= '</sst>';
        $sharedStringXml = $sstContent;
    }

    $sheetData = '';
    $rowIndex = 1;

    // Header
    $sheetData .= '<row r="'.$rowIndex.'" ht="26" customHeight="1">';
    $headerRow = $rows[0] ?? [];
    foreach ($headerRow as $colIndex => $header) {
        $column = chr(65 + $colIndex);
        $sheetData .= '<c r="'.$column.$rowIndex.'" s="1" t="inlineStr"><is><t>'.htmlspecialchars($header, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</t></is></c>';
    }
    $sheetData .= '</row>';
    $rowIndex++;

    // Data rows
    foreach (array_slice($rows, 1) as $dataRow) {
        $sheetData .= '<row r="'.$rowIndex.'">';
        foreach ($dataRow as $colIndex => $cellValue) {
            $column = chr(65 + $colIndex);
            $sharedStringIndex = $sharedStrings[$cellValue] ?? 0;
            $sheetData .= '<c r="'.$column.$rowIndex.'" t="s"><v>'.$sharedStringIndex.'</v></c>';
        }
        $sheetData .= '</row>';
        $rowIndex++;
    }

    $contentTypes = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
'.($sharedStrings !== [] ? '<Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/>' : '').'
</Types>';

    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
</Relationships>';

    $workbook = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="'.$mainNs.'" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
<sheets><sheet name="Sheet1" sheetId="1" r:id="rId1"/></sheets>
</workbook>';

    $workbookRels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>';

    $styles = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<styleSheet xmlns="'.$mainNs.'">
<fonts count="1"><font><sz val="11"/><name val="Calibri"/></font></fonts>
<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>
<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>
<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>
<cellXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/></cellXfs>
<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>
</styleSheet>';

    $sheet = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="'.$mainNs.'">
<dimension ref="A1:E'.$rowIndex.'"/>
<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>
<sheetData>'.$sheetData.'</sheetData>
</worksheet>';

    $archive->addFromString('[Content_Types].xml', $contentTypes);
    $archive->addFromString('_rels/.rels', $rels);
    $archive->addFromString('xl/workbook.xml', $workbook);
    $archive->addFromString('xl/_rels/workbook.xml.rels', $workbookRels);
    $archive->addFromString('xl/styles.xml', $styles);
    if ($sharedStrings !== []) {
        $archive->addFromString('xl/sharedStrings.xml', $sharedStringXml);
    }
    $archive->addFromString('xl/worksheets/sheet1.xml', $sheet);
    $archive->close();

    $contents = file_get_contents($temporaryPath);
    @unlink($temporaryPath);

    if (! is_string($contents)) {
        throw new RuntimeException('Cannot read generated XLSX file.');
    }

    return $contents;
}

test('admin can preview valid XLSX import for rabu mengaji', function () {
    $fixture = makeRabuMengajiImportFixture();

    $xlsxContents = createXlsxFile([
        ['NIM', 'Nama Santri', 'Tanggal', 'Status Kehadiran', 'Keterangan'],
        ['MGJ-001', 'Santri Mengaji Satu', '2026-11-18', 'Hadir', 'Hadir tepat waktu'],
        ['MGJ-002', 'Santri Mengaji Dua', '2026-11-18', 'Izin', 'Izin sakit'],
    ]);

    Storage::disk('local')->put('test-import-valid.xlsx', $xlsxContents);

    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.import.preview'), [
            'pertemuan_id' => $fixture['meeting']->id,
            'file' => UploadedFile::fake()->createWithContent('import.xlsx', $xlsxContents),
        ])
        ->assertOk()
        ->assertSee('Santri Mengaji Satu')
        ->assertSee('Santri Mengaji Dua')
        ->assertSee('MGJ-001')
        ->assertSee('MGJ-002');
});

test('admin commits valid XLSX import and creates absensi records', function () {
    $fixture = makeRabuMengajiImportFixture();

    $xlsxContents = createXlsxFile([
        ['NIM', 'Nama Santri', 'Tanggal', 'Status Kehadiran', 'Keterangan'],
        ['MGJ-001', 'Santri Mengaji Satu', '2026-11-18', 'Hadir', 'Hadir tepat waktu'],
        ['MGJ-002', 'Santri Mengaji Dua', '2026-11-18', 'Izin', 'Izin sakit'],
    ]);

    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.import.preview'), [
            'pertemuan_id' => $fixture['meeting']->id,
            'file' => UploadedFile::fake()->createWithContent('import.xlsx', $xlsxContents),
        ])
        ->assertOk();

    $token = $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.import.commit'), [
            'import_token' => session('rabu_mengaji_import_token'),
        ])
        ->assertRedirect(route('rabu-mengaji.index'));

    $this->assertDatabaseHas('absensi_sik', [
        'anggota_periode_sik_id' => $fixture['membership1']->id,
        'pertemuan_id' => $fixture['meeting']->id,
        'status' => 'Hadir',
        'keterangan' => 'Hadir tepat waktu',
    ]);
    $this->assertDatabaseHas('absensi_sik', [
        'anggota_periode_sik_id' => $fixture['membership2']->id,
        'pertemuan_id' => $fixture['meeting']->id,
        'status' => 'Izin',
        'keterangan' => 'Izin sakit',
    ]);
});

test('preview does not save any data to absensi_sik', function () {
    $fixture = makeRabuMengajiImportFixture();

    $xlsxContents = createXlsxFile([
        ['NIM', 'Nama Santri', 'Tanggal', 'Status Kehadiran', 'Keterangan'],
        ['MGJ-001', 'Santri Mengaji Satu', '2026-11-18', 'Hadir', ''],
    ]);

    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.import.preview'), [
            'pertemuan_id' => $fixture['meeting']->id,
            'file' => UploadedFile::fake()->createWithContent('import.xlsx', $xlsxContents),
        ])
        ->assertOk();

    $this->assertDatabaseCount('absensi_sik', 0);
});

test('header tidak sesuai ditolak', function () {
    $fixture = makeRabuMengajiImportFixture();

    $xlsxContents = createXlsxFile([
        ['ID', 'Nama', 'Tanggal', 'Status', 'Keterangan'],
        ['MGJ-001', 'Santri Mengaji Satu', '2026-11-18', 'Hadir', ''],
    ]);

    $response = $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.import.preview'), [
            'pertemuan_id' => $fixture['meeting']->id,
            'file' => UploadedFile::fake()->createWithContent('import.xlsx', $xlsxContents),
        ]);

    $response->assertRedirect();
    $response->assertSessionHasErrors('file');
});

test('file rusak ditolak', function () {
    $fixture = makeRabuMengajiImportFixture();

    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.import.preview'), [
            'pertemuan_id' => $fixture['meeting']->id,
            'file' => UploadedFile::fake()->createWithContent('import.xlsx', 'not a valid xlsx file'),
        ])
        ->assertSessionHasErrors('file');
});

test('NIM tidak ditemukan ditandai tidak valid', function () {
    $fixture = makeRabuMengajiImportFixture();

    $xlsxContents = createXlsxFile([
        ['NIM', 'Nama Santri', 'Tanggal', 'Status Kehadiran', 'Keterangan'],
        ['MGJ-999', 'Santri Tidak Dikenal', '2026-11-18', 'Hadir', ''],
    ]);

    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.import.preview'), [
            'pertemuan_id' => $fixture['meeting']->id,
            'file' => UploadedFile::fake()->createWithContent('import.xlsx', $xlsxContents),
        ])
        ->assertOk()
        ->assertSee('MGJ-999')
        ->assertSee('Tidak valid');
});

test('nama tidak cocok dengan NIM ditandai tidak valid', function () {
    $fixture = makeRabuMengajiImportFixture();

    $xlsxContents = createXlsxFile([
        ['NIM', 'Nama Santri', 'Tanggal', 'Status Kehadiran', 'Keterangan'],
        ['MGJ-001', 'Nama Salah', '2026-11-18', 'Hadir', ''],
    ]);

    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.import.preview'), [
            'pertemuan_id' => $fixture['meeting']->id,
            'file' => UploadedFile::fake()->createWithContent('import.xlsx', $xlsxContents),
        ])
        ->assertOk()
        ->assertSee('Nama Salah')
        ->assertSee('Tidak valid');
});

test('status tidak valid ditandai tidak valid', function () {
    $fixture = makeRabuMengajiImportFixture();

    $xlsxContents = createXlsxFile([
        ['NIM', 'Nama Santri', 'Tanggal', 'Status Kehadiran', 'Keterangan'],
        ['MGJ-001', 'Santri Mengaji Satu', '2026-11-18', 'Cuti', ''],
    ]);

    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.import.preview'), [
            'pertemuan_id' => $fixture['meeting']->id,
            'file' => UploadedFile::fake()->createWithContent('import.xlsx', $xlsxContents),
        ])
        ->assertOk()
        ->assertSee('Cuti')
        ->assertSee('Tidak valid');
});

test('tanggal berbeda dari pertemuan ditandai tidak valid', function () {
    $fixture = makeRabuMengajiImportFixture();

    $xlsxContents = createXlsxFile([
        ['NIM', 'Nama Santri', 'Tanggal', 'Status Kehadiran', 'Keterangan'],
        ['MGJ-001', 'Santri Mengaji Satu', '2026-11-20', 'Hadir', ''],
    ]);

    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.import.preview'), [
            'pertemuan_id' => $fixture['meeting']->id,
            'file' => UploadedFile::fake()->createWithContent('import.xlsx', $xlsxContents),
        ])
        ->assertOk()
        ->assertSee('2026-11-20')
        ->assertSee('Tidak valid');
});

test('anggota dari kelompok lain pada periode sama diterima sebagai peserta rabu mengaji', function () {
    $fixture = makeRabuMengajiImportFixture();

    $xlsxContents = createXlsxFile([
        ['NIM', 'Nama Santri', 'Tanggal', 'Status Kehadiran', 'Keterangan'],
        ['MGJ-002', 'Santri Mengaji Dua', '2026-11-18', 'Hadir', ''],
    ]);

    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.import.preview'), [
            'pertemuan_id' => $fixture['meeting']->id,
            'file' => UploadedFile::fake()->createWithContent('import.xlsx', $xlsxContents),
        ])
        ->assertOk()
        ->assertSee('MGJ-002')
        ->assertViewHas('analysis', function (array $analysis): bool {
            return $analysis['valid'] === 1
                && $analysis['invalid'] === 0
                && $analysis['duplicate'] === 0;
        });
});

test('data duplikat dalam file ditandai sebagai duplikat', function () {
    $fixture = makeRabuMengajiImportFixture();

    $xlsxContents = createXlsxFile([
        ['NIM', 'Nama Santri', 'Tanggal', 'Status Kehadiran', 'Keterangan'],
        ['MGJ-001', 'Santri Mengaji Satu', '2026-11-18', 'Hadir', ''],
        ['MGJ-001', 'Santri Mengaji Satu', '2026-11-18', 'Izin', ''],
    ]);

    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.import.preview'), [
            'pertemuan_id' => $fixture['meeting']->id,
            'file' => UploadedFile::fake()->createWithContent('import.xlsx', $xlsxContents),
        ])
        ->assertOk()
        ->assertSee('Duplikat');
});

test('commit hanya menyimpan baris valid', function () {
    $fixture = makeRabuMengajiImportFixture();

    $xlsxContents = createXlsxFile([
        ['NIM', 'Nama Santri', 'Tanggal', 'Status Kehadiran', 'Keterangan'],
        ['MGJ-001', 'Santri Mengaji Satu', '2026-11-18', 'Hadir', ''],
        ['MGJ-999', 'Tidak Dikenal', '2026-11-18', 'Hadir', ''],
    ]);

    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.import.preview'), [
            'pertemuan_id' => $fixture['meeting']->id,
            'file' => UploadedFile::fake()->createWithContent('import.xlsx', $xlsxContents),
        ])
        ->assertOk();

    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.import.commit'), [
            'import_token' => session('rabu_mengaji_import_token'),
        ])
        ->assertRedirect(route('rabu-mengaji.index'));

    $this->assertDatabaseHas('absensi_sik', [
        'anggota_periode_sik_id' => $fixture['membership1']->id,
        'pertemuan_id' => $fixture['meeting']->id,
        'status' => 'Hadir',
    ]);
    $this->assertDatabaseMissing('absensi_sik', [
        'pertemuan_id' => $fixture['meeting']->id,
        'status' => 'Hadir',
        'keterangan' => null,
    ]);
    // Only MGJ-001 should exist, MGJ-999 should not
    $this->assertDatabaseCount('absensi_sik', 1);
});

test('instruktur SIK dapat mengimpor untuk kelompok kewenangannya', function () {
    $fixture = makeRabuMengajiImportFixture();

    $xlsxContents = createXlsxFile([
        ['NIM', 'Nama Santri', 'Tanggal', 'Status Kehadiran', 'Keterangan'],
        ['MGJ-001', 'Santri Mengaji Satu', '2026-11-18', 'Hadir', ''],
    ]);

    // Instruktur dapat melakukan preview untuk kelompok kewenangannya
    $this->actingAs($fixture['instructorUser'])
        ->post(route('rabu-mengaji.import.preview'), [
            'pertemuan_id' => $fixture['meeting']->id,
            'file' => UploadedFile::fake()->createWithContent('import.xlsx', $xlsxContents),
        ])
        ->assertOk()
        ->assertSee('Santri Mengaji Satu');

    // Admin menyimpan data (instruktur dan admin memiliki otorisasi yang sama melalui service)
    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.import.commit'), [
            'import_token' => session('rabu_mengaji_import_token'),
        ])
        ->assertRedirect(route('rabu-mengaji.index'));

    $this->assertDatabaseHas('absensi_sik', [
        'anggota_periode_sik_id' => $fixture['membership1']->id,
        'pertemuan_id' => $fixture['meeting']->id,
        'status' => 'Hadir',
    ]);
});

test('instruktur SIK dari kelompok lain dapat mengimpor peserta rabu mengaji', function () {
    $fixture = makeRabuMengajiImportFixture();

    $xlsxContents = createXlsxFile([
        ['NIM', 'Nama Santri', 'Tanggal', 'Status Kehadiran', 'Keterangan'],
        ['MGJ-001', 'Santri Mengaji Satu', '2026-11-18', 'Hadir', ''],
        ['MGJ-002', 'Santri Mengaji Dua', '2026-11-18', 'Izin', ''],
    ]);

    $this->actingAs($fixture['otherInstructorUser'])
        ->post(route('rabu-mengaji.import.preview'), [
            'pertemuan_id' => $fixture['meeting']->id,
            'file' => UploadedFile::fake()->createWithContent('import.xlsx', $xlsxContents),
        ])
        ->assertOk()
        ->assertViewHas('analysis', function (array $analysis): bool {
            return $analysis['valid'] === 2 && $analysis['invalid'] === 0;
        });
});

test('instruktur SIK tidak aktif ditolak saat mengimpor rabu mengaji', function () {
    $fixture = makeRabuMengajiImportFixture();

    $nonaktifUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    InfrastrukturSik::query()->create([
        'user_id' => $nonaktifUser->id,
        'nama' => 'Instruktur Nonaktif Import',
        'status' => 'Tidak Aktif',
    ]);

    $xlsxContents = createXlsxFile([
        ['NIM', 'Nama Santri', 'Tanggal', 'Status Kehadiran', 'Keterangan'],
        ['MGJ-001', 'Santri Mengaji Satu', '2026-11-18', 'Hadir', ''],
    ]);

    $this->actingAs($nonaktifUser)
        ->post(route('rabu-mengaji.import.preview'), [
            'pertemuan_id' => $fixture['meeting']->id,
            'file' => UploadedFile::fake()->createWithContent('import.xlsx', $xlsxContents),
        ])
        ->assertForbidden();
});

test('santri tidak dapat mengakses halaman impor', function () {
    $fixture = makeRabuMengajiImportFixture();

    $santriUser = User::factory()->create(['role' => UserRole::Santri]);

    $this->actingAs($santriUser)
        ->get(route('rabu-mengaji.import'))
        ->assertForbidden();
});

test('ka lab mutu tidak dapat mengakses halaman impor', function () {
    $fixture = makeRabuMengajiImportFixture();

    $kaLabUser = User::factory()->create(['role' => UserRole::KaLabMutu]);

    $this->actingAs($kaLabUser)
        ->get(route('rabu-mengaji.import'))
        ->assertForbidden();
});

test('pertemuan dibatalkan tidak dapat menerima impor', function () {
    $fixture = makeRabuMengajiImportFixture();

    $cancelledMeeting = Pertemuan::query()->create([
        'kegiatan_id' => $fixture['activity']->id,
        'pertemuan_ke' => 2,
        'tanggal' => '2026-11-25',
        'status' => 'Dibatalkan',
    ]);

    $xlsxContents = createXlsxFile([
        ['NIM', 'Nama Santri', 'Tanggal', 'Status Kehadiran', 'Keterangan'],
        ['MGJ-001', 'Santri Mengaji Satu', '2026-11-25', 'Hadir', ''],
    ]);

    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.import.preview'), [
            'pertemuan_id' => $cancelledMeeting->id,
            'file' => UploadedFile::fake()->createWithContent('import.xlsx', $xlsxContents),
        ])
        ->assertSessionHasErrors('pertemuan_id');
});

test('rekap tersinkronisasi setelah commit', function () {
    $fixture = makeRabuMengajiImportFixture();

    $terlaksanaMeeting = Pertemuan::query()->create([
        'kegiatan_id' => $fixture['activity']->id,
        'pertemuan_ke' => 3,
        'tanggal' => '2026-11-20',
        'status' => 'Terlaksana',
    ]);

    $xlsxContents = createXlsxFile([
        ['NIM', 'Nama Santri', 'Tanggal', 'Status Kehadiran', 'Keterangan'],
        ['MGJ-001', 'Santri Mengaji Satu', '2026-11-20', 'Hadir', ''],
    ]);

    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.import.preview'), [
            'pertemuan_id' => $terlaksanaMeeting->id,
            'file' => UploadedFile::fake()->createWithContent('import.xlsx', $xlsxContents),
        ])
        ->assertOk();

    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.import.commit'), [
            'import_token' => session('rabu_mengaji_import_token'),
        ])
        ->assertRedirect(route('rabu-mengaji.index'));

    $this->assertDatabaseHas('rekap_kehadiran_sik', [
        'anggota_periode_sik_id' => $fixture['membership1']->id,
        'kegiatan_id' => $fixture['activity']->id,
        'jumlah_hadir' => 1,
    ]);
});

test('absensi yang sudah ada tidak ditimpa oleh impor', function () {
    $fixture = makeRabuMengajiImportFixture();

    // Create existing absensi
    AbsensiSik::query()->create([
        'anggota_periode_sik_id' => $fixture['membership1']->id,
        'pertemuan_id' => $fixture['meeting']->id,
        'metode_absensi' => 'Manual',
        'status' => 'Hadir',
        'dicatat_oleh' => $fixture['instructorUser']->infrastrukturSik->id,
    ]);

    $xlsxContents = createXlsxFile([
        ['NIM', 'Nama Santri', 'Tanggal', 'Status Kehadiran', 'Keterangan'],
        ['MGJ-001', 'Santri Mengaji Satu', '2026-11-18', 'Alpha', ''],
    ]);

    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.import.preview'), [
            'pertemuan_id' => $fixture['meeting']->id,
            'file' => UploadedFile::fake()->createWithContent('import.xlsx', $xlsxContents),
        ])
        ->assertOk()
        ->assertSee('sudah tercatat');

    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.import.commit'), [
            'import_token' => session('rabu_mengaji_import_token'),
        ])
        ->assertRedirect(route('rabu-mengaji.import'))
        ->assertSessionHasErrors('file');

    $this->assertDatabaseHas('absensi_sik', [
        'id' => AbsensiSik::where('anggota_periode_sik_id', $fixture['membership1']->id)
            ->where('pertemuan_id', $fixture['meeting']->id)
            ->first()->id,
        'status' => 'Hadir',
    ]);
});

test('template rabu mengaji menghasilkan xlsx yang valid', function () {
    $fixture = makeRabuMengajiImportFixture();

    $response = $this->actingAs($fixture['admin'])
        ->get(route('rabu-mengaji.import.template'))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    $temporaryPath = tempnam(sys_get_temp_dir(), 'sik-rabu-template-test-');
    file_put_contents($temporaryPath, $response->streamedContent());

    $archive = new ZipArchive;
    expect($archive->open($temporaryPath))->toBeTrue()
        ->and($archive->locateName('xl/workbook.xml'))->not->toBeFalse()
        ->and($archive->locateName('xl/worksheets/sheet1.xml'))->not->toBeFalse()
        ->and($archive->locateName('[Content_Types].xml'))->not->toBeFalse();

    $sheet = (string) $archive->getFromName('xl/worksheets/sheet1.xml');
    expect($sheet)->toContain('NIM')
        ->toContain('Nama Santri')
        ->toContain('Tanggal')
        ->toContain('Status Kehadiran');

    $archive->close();
    @unlink($temporaryPath);
});

test('tanggal numerik excel dinormalisasi sebelum divalidasi', function () {
    $fixture = makeRabuMengajiImportFixture();

    // 46344 adalah serial Excel untuk 2026-11-18.
    $xlsxContents = createXlsxFile([
        ['NIM', 'Nama Santri', 'Tanggal', 'Status Kehadiran', 'Keterangan'],
        ['MGJ-001', 'Santri Mengaji Satu', '46344', 'Hadir', ''],
    ]);

    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.import.preview'), [
            'pertemuan_id' => $fixture['meeting']->id,
            'file' => UploadedFile::fake()->createWithContent('import.xlsx', $xlsxContents),
        ])
        ->assertOk()
        ->assertDontSee('Tanggal tidak cocok');
});

test('alias ganda pada baris data tidak menggagalkan seluruh berkas', function () {
    $fixture = makeRabuMengajiImportFixture();

    $xlsxContents = createXlsxFile([
        ['NIM', 'Nama Santri', 'Tanggal', 'Status Kehadiran', 'Keterangan'],
        ['MGJ-001', 'Santri Mengaji Satu', '2026-11-18', 'Hadir', '', 'Nama', 'Status'],
    ]);

    $this->actingAs($fixture['admin'])
        ->post(route('rabu-mengaji.import.preview'), [
            'pertemuan_id' => $fixture['meeting']->id,
            'file' => UploadedFile::fake()->createWithContent('import.xlsx', $xlsxContents),
        ])
        ->assertOk()
        ->assertSee('Santri Mengaji Satu');
});
