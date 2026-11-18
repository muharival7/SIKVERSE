<?php

use App\Enums\UserRole;
use App\Models\AnggotaPeriodeSik;
use App\Models\InfrastrukturSik;
use App\Models\Kelompok;
use App\Models\PeriodeSik;
use App\Models\Santri;
use App\Models\User;
use App\Services\SantriSpreadsheet;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * @param  list<array<int, string>>  $dataRows
 * @param  array{headers?: list<string>, leading_rows?: list<list<string>>, extra_sheet?: bool}  $options
 */
function makeSantriImportUpload(array $dataRows, array $options = []): UploadedFile
{
    $headers = $options['headers'] ?? ['Nomor Induk/NIM', 'Nama Santri', 'Jenis Kelamin', 'Program Studi', 'Kelompok', 'Periode', 'Status Santri', 'Email (opsional)'];
    $rows = [...($options['leading_rows'] ?? []), $headers, ...$dataRows];
    $sheets = [];

    if ($options['extra_sheet'] ?? false) {
        $sheets[] = [
            'name' => 'Petunjuk',
            'rows' => [['DAFTAR DATA SANTRI'], ['Catatan untuk pengelola']],
        ];
    }

    $sheets[] = ['name' => 'Data Santri', 'rows' => $rows];

    return makeXlsxUpload($sheets);
}

/** @param list<array{name: string, rows: list<list<string>>}> $sheets */
function makeXlsxUpload(array $sheets): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'sik-import-test-');

    if ($path === false) {
        throw new RuntimeException('Tidak dapat membuat file Excel uji.');
    }

    $zip = new ZipArchive;

    if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Tidak dapat membuat workbook Excel uji.');
    }

    $sheetOverrides = '';
    $workbookSheets = '';
    $relationships = '';

    foreach ($sheets as $index => $sheet) {
        $sheetId = $index + 1;
        $relationId = 'rId'.$sheetId;
        $safeName = htmlspecialchars($sheet['name'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $workbookSheets .= '<sheet name="'.$safeName.'" sheetId="'.$sheetId.'" r:id="'.$relationId.'"/>';
        $relationships .= '<Relationship Id="'.$relationId.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$sheetId.'.xml"/>';
        $sheetOverrides .= '<Override PartName="/xl/worksheets/sheet'.$sheetId.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $sheetRows = '';

        foreach ($sheet['rows'] as $rowIndex => $values) {
            $excelRow = $rowIndex + 1;
            $sheetRows .= '<row r="'.$excelRow.'">';

            foreach (array_values($values) as $columnIndex => $value) {
                $column = '';
                $number = $columnIndex + 1;

                while ($number > 0) {
                    $remainder = ($number - 1) % 26;
                    $column = chr(65 + $remainder).$column;
                    $number = intdiv($number - 1, 26);
                }

                $text = htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $sheetRows .= '<c r="'.$column.$excelRow.'" t="inlineStr"><is><t>'.$text.'</t></is></c>';
            }

            $sheetRows .= '</row>';
        }

        $zip->addFromString('xl/worksheets/sheet'.$sheetId.'.xml', '<?xml version="1.0" encoding="UTF-8"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$sheetRows.'</sheetData></worksheet>');
    }

    $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?>'
        .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
        .'<Default Extension="xml" ContentType="application/xml"/>'
        .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        .$sheetOverrides.'</Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
        .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
        .'</Relationships>');
    $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?>'
        .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
        .'<sheets>'.$workbookSheets.'</sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?>'
        .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relationships.'</Relationships>');
    $zip->close();

    return new UploadedFile(
        $path,
        'santri-import.xlsx',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        null,
        true
    );
}

/** @return array{period: PeriodeSik, instructor: InfrastrukturSik, group: Kelompok} */
function createSantriImportGroup(string $groupName = 'Teknologi'): array
{
    $instructorUser = User::factory()->create(['role' => UserRole::InfrastrukturSik]);
    $instructor = InfrastrukturSik::query()->create([
        'user_id' => $instructorUser->id,
        'nama' => 'Instruktur Uji',
        'nip' => 'NIP-'.strtoupper(bin2hex(random_bytes(4))),
        'program_studi' => 'Sistem Informasi',
        'email' => $instructorUser->email,
        'status' => 'Aktif',
    ]);
    $period = PeriodeSik::query()->create([
        'nama_periode' => 'ganjil_2026',
        'tahun' => 2026,
        'tanggal_mulai' => '2026-08-01',
        'tanggal_selesai' => '2026-12-31',
        'status' => 'Aktif',
    ]);
    $group = Kelompok::query()->create([
        'periode_sik_id' => $period->id,
        'instruktur_id' => $instructor->id,
        'nama_kelompok' => $groupName,
        'status' => 'Aktif',
    ]);

    return compact('period', 'instructor', 'group');
}

test('admin can download a structurally valid XLSX template with supported headers', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)->get(route('santris.import.template'));

    $response->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertHeader('content-disposition', 'attachment; filename=template-data-santri.xlsx');

    $contents = $response->streamedContent();
    expect($contents)->toStartWith('PK');

    $path = tempnam(sys_get_temp_dir(), 'sik-template-test-');
    expect($path)->not->toBeFalse();
    file_put_contents($path, $contents);
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();
    expect($zip->getFromName('[Content_Types].xml'))->toContain('spreadsheetml.sheet.main+xml')
        ->and($zip->getFromName('xl/workbook.xml'))->toContain('Data Santri')
        ->and($zip->getFromName('xl/worksheets/sheet1.xml'))->toContain('Nomor Induk/NIM');
    $zip->close();
    @unlink($path);
});

test('admin can preview and commit valid rows while duplicate and invalid rows are reported', function () {
    config()->set('sik.auth.require_email_verification', false);
    Storage::fake('local');
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $setup = createSantriImportGroup();

    $upload = makeSantriImportUpload([
        ['001234', 'Alya Rahma', 'Perempuan', 'Sistem Informasi', 'Teknologi', 'ganjil_2026', 'Aktif', 'alya@example.test'],
        ['001235', 'Bima Fadli', 'Laki-laki', 'Sistem Informasi', 'Teknologi', 'ganjil_2026', 'Aktif', 'bima@example.test'],
        ['001234', 'Alya Duplikat', 'Perempuan', 'Sistem Informasi', 'Teknologi', 'ganjil_2026', 'Aktif', 'alya-duplicate@example.test'],
        ['001236', '', 'Perempuan', 'Sistem Informasi', 'Teknologi', 'ganjil_2026', 'Aktif', 'invalid@example.test'],
    ]);

    $preview = $this->actingAs($admin)->post(route('santris.import.preview'), ['file' => $upload]);

    $preview->assertOk()
        ->assertViewHas('preview', fn (array $data): bool => $data['valid'] === 2
            && $data['duplicate'] === 1
            && $data['invalid'] === 1);
    expect(Santri::query()->count())->toBe(0)
        ->and(User::query()->where('role', UserRole::Santri->value)->count())->toBe(0);

    $token = session('santri_import_token');
    expect($token)->toBeString();

    $this->post(route('santris.import.commit'), ['import_token' => $token])
        ->assertRedirect(route('santris.index'))
        ->assertSessionHas('import_result', fn (array $result): bool => $result['created'] === 2
            && $result['failed'] === 2);

    expect(Santri::query()->count())->toBe(2)
        ->and(User::query()->where('role', UserRole::Santri->value)->count())->toBe(2)
        ->and(AnggotaPeriodeSik::query()->count())->toBe(2)
        ->and(Kelompok::query()->count())->toBe(1);

    $membership = AnggotaPeriodeSik::query()->with('kelompok')->firstOrFail();
    expect($membership->periode_sik_id)->toBe($setup['period']->id)
        ->and($membership->kelompok->instruktur_id)->toBe($setup['instructor']->id);

    $credentialsToken = session('santri_credentials_token');
    expect($credentialsToken)->toBeString();
    $credentials = $this->get(route('santris.import.credentials', $credentialsToken));
    $credentials->assertOk()->assertHeader('content-disposition', 'attachment; filename=kredensial-santri.csv');
    expect(session('santri_credentials_token'))->toBeNull();
});

test('admin can import hundreds of santri without creating groups or touching existing accounts', function () {
    config()->set('sik.auth.require_email_verification', false);
    Storage::fake('local');
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    createSantriImportGroup();

    $rows = [];
    for ($index = 1; $index <= 200; $index++) {
        $nim = str_pad((string) (100000 + $index), 8, '0', STR_PAD_LEFT);
        $rows[] = [$nim, "Santri Uji {$index}", $index % 2 === 0 ? 'Perempuan' : 'Laki-laki', 'Sistem Informasi', 'Teknologi', 'ganjil_2026', 'Aktif', "santri{$index}@example.test"];
    }

    $preview = $this->actingAs($admin)->post(route('santris.import.preview'), ['file' => makeSantriImportUpload($rows)]);
    $preview->assertOk()->assertViewHas('preview', fn (array $data): bool => $data['valid'] === 200 && $data['invalid'] === 0);
    $this->post(route('santris.import.commit'), ['import_token' => session('santri_import_token')])->assertRedirect(route('santris.index'));

    expect(Santri::query()->count())->toBe(200)
        ->and(User::query()->where('role', UserRole::Santri->value)->count())->toBe(200)
        ->and(AnggotaPeriodeSik::query()->count())->toBe(200)
        ->and(Kelompok::query()->count())->toBe(1);
});

test('workbook titles, sheet selection, header aliases and empty rows are parsed and gender can be completed before commit', function () {
    config()->set('sik.auth.require_email_verification', false);
    Storage::fake('local');
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $setup = createSantriImportGroup();
    $upload = makeSantriImportUpload([
        ['1', '001240', 'Nabila Uji', 'Sistem Informasi', 'Teknologi'],
        ['', '', '', '', ''],
    ], [
        'headers' => ['NO.', 'NIM', 'NAMA', 'PROGRAM STUDI', 'KELAS'],
        'leading_rows' => [['DAFTAR PESERTA SIK'], ['SEMESTER GANJIL TAHUN AKADEMIK 2026/2027'], []],
        'extra_sheet' => true,
    ]);

    $preview = $this->actingAs($admin)->post(route('santris.import.preview'), ['file' => $upload]);
    $preview->assertOk()->assertViewHas('preview', function (array $data): bool {
        return count($data['rows']) === 1
            && $data['rows'][0]['sheet'] === 'Data Santri'
            && $data['rows'][0]['row'] === 5
            && $data['rows'][0]['data']['periode_sik_id'] !== null
            && $data['rows'][0]['data']['periode_label'] === 'ganjil_2026'
            && $data['rows'][0]['category'] === 'invalid'
            && str_contains(implode(' ', $data['rows'][0]['reasons']), 'Jenis kelamin wajib');
    });
    expect(Santri::query()->count())->toBe(0);

    $this->post(route('santris.import.commit'), [
        'import_token' => session('santri_import_token'),
        'corrections' => [0 => ['jenis_kelamin' => 'Perempuan']],
    ])->assertRedirect(route('santris.index'));

    $santri = Santri::query()->where('nomor_induk', '001240')->firstOrFail();
    expect($santri->jenis_kelamin)->toBe('Perempuan')
        ->and($santri->program_studi)->toBe('Sistem Informasi')
        ->and(AnggotaPeriodeSik::query()->where('santri_id', $santri->id)->value('periode_sik_id'))->toBe($setup['period']->id);
});

test('database duplicates, missing required fields and unknown groups never get stored', function () {
    config()->set('sik.auth.require_email_verification', false);
    Storage::fake('local');
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    createSantriImportGroup('Teknologi');
    $oldUser = User::factory()->create(['role' => UserRole::Santri, 'email' => 'old-santri@example.test', 'name' => 'Nama Lama']);
    Santri::query()->create([
        'user_id' => $oldUser->id,
        'nomor_induk' => '009999',
        'nama' => 'Nama Lama',
        'jenis_kelamin' => 'Laki-laki',
        'program_studi' => 'Sistem Informasi',
        'status' => 'Aktif',
    ]);
    $oldPasswordHash = $oldUser->password;

    $upload = makeSantriImportUpload([
        ['008887', 'Santri Valid', 'Laki-laki', '', 'Teknologi', 'ganjil_2026', 'Aktif', 'valid@example.test'],
        ['009999', 'Nama Diubah', 'Perempuan', 'Prodi', 'Teknologi', 'ganjil_2026', 'Aktif', 'different@example.test'],
        ['', '', 'Laki-laki', '', 'Teknologi', 'ganjil_2026', 'Aktif', 'missing@example.test'],
        ['008888', 'Kelompok Tidak Ada', 'Perempuan', '', 'Kelompok Baru', 'ganjil_2026', 'Aktif', 'new@example.test'],
    ]);

    $this->actingAs($admin)->post(route('santris.import.preview'), ['file' => $upload])
        ->assertOk()
        ->assertViewHas('preview', fn (array $data): bool => $data['valid'] === 1
            && $data['duplicate'] === 1
            && $data['invalid'] === 2
        );

    expect(Santri::query()->count())->toBe(1)
        ->and(User::findOrFail($oldUser->id)->name)->toBe('Nama Lama')
        ->and(Kelompok::query()->count())->toBe(1);

    $this->post(route('santris.import.commit'), ['import_token' => session('santri_import_token')])
        ->assertRedirect(route('santris.index'))
        ->assertSessionHas('import_result', fn (array $result): bool => $result['created'] === 1
            && $result['failed'] === 3);

    $oldUser->refresh();
    expect(Santri::query()->count())->toBe(2)
        ->and(Santri::query()->whereIn('nomor_induk', ['009999', ''])->count())->toBe(1)
        ->and($oldUser->name)->toBe('Nama Lama')
        ->and($oldUser->email)->toBe('old-santri@example.test')
        ->and($oldUser->password)->toBe($oldPasswordHash)
        ->and(Kelompok::query()->count())->toBe(1);
});

test('import page removes default period and instructor choices and preview is explicit before saving', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)->get(route('santris.import'))
        ->assertOk()
        ->assertDontSee('Periode bawaan jika kolom periode kosong')
        ->assertDontSee('Instruktur untuk kelompok baru')
        ->assertSee('Belum ada data yang disimpan');
});

test('invalid workbook layout is rejected before import', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $upload = makeSantriImportUpload([['001234', 'Alya Rahma']], ['headers' => ['Nomor Induk/NIM', 'Nama Santri']]);

    $this->actingAs($admin)->post(route('santris.import.preview'), ['file' => $upload])
        ->assertSessionHasErrors('file');
    expect(Santri::query()->count())->toBe(0);
});

test('import rejects non-XLSX files, files over 10 MB, and workbooks over the row limit', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $validFile = makeSantriImportUpload([['001234', 'Alya Rahma', 'Perempuan', 'Sistem Informasi', 'Teknologi', 'ganjil_2026', 'Aktif', 'alya@example.test']]);
    $csvNamedUpload = new UploadedFile(
        $validFile->getPathname(),
        'data-santri.csv',
        'text/csv',
        null,
        true
    );

    $this->actingAs($admin)->post(route('santris.import.preview'), ['file' => $csvNamedUpload])
        ->assertSessionHasErrors('file');

    $oversizedFile = UploadedFile::fake()->create(
        'data-santri.xlsx',
        10241,
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
    );
    $this->post(route('santris.import.preview'), ['file' => $oversizedFile])
        ->assertSessionHasErrors('file');

    $tooManyRows = [];
    for ($index = 1; $index <= 1501; $index++) {
        $tooManyRows[] = [str_pad((string) $index, 8, '0', STR_PAD_LEFT), 'Nama Uji '.$index, 'Laki-laki', '', 'Teknologi', 'ganjil_2026', 'Aktif', 'uji'.$index.'@example.test'];
    }

    $this->post(route('santris.import.preview'), ['file' => makeSantriImportUpload($tooManyRows)])
        ->assertSessionHasErrors('file');
    expect(Santri::query()->count())->toBe(0);
});

test('template XLSX memuat semua komponen Office Open XML yang wajib dan valid', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $response = $this->actingAs($admin)->get(route('santris.import.template'));

    $response->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->assertHeader('content-disposition', 'attachment; filename=template-data-santri.xlsx');

    $contents = $response->streamedContent();

    expect($contents)->toStartWith('PK')
        ->and(str_contains($contents, '<!DOCTYPE html>'))->toBeFalse();

    $path = tempnam(sys_get_temp_dir(), 'sik-template-audit-');
    file_put_contents($path, $contents);

    try {
        $zip = new ZipArchive;
        expect($zip->open($path))->toBeTrue();

        $requiredParts = [
            '[Content_Types].xml',
            '_rels/.rels',
            'xl/workbook.xml',
            'xl/_rels/workbook.xml.rels',
            'xl/worksheets/sheet1.xml',
        ];

        foreach ($requiredParts as $part) {
            expect($zip->locateName($part))->not->toBeFalse()
                ->and($zip->getFromName($part))->toBeString();
        }

        libxml_use_internal_errors(true);

        foreach ($requiredParts as $part) {
            $document = new DOMDocument;
            expect($document->loadXML((string) $zip->getFromName($part), LIBXML_NONET))->toBeTrue()
                ->and(libxml_get_errors())->toBe([]);
            libxml_clear_errors();
        }

        $contentTypes = (string) $zip->getFromName('[Content_Types].xml');
        expect($contentTypes)->toContain('spreadsheetml.sheet.main+xml')
            ->toContain('spreadsheetml.worksheet+xml')
            ->toContain('spreadsheetml.styles+xml')
            ->toContain('relationships+xml');

        $packageRels = simplexml_load_string((string) $zip->getFromName('_rels/.rels'));
        expect($packageRels)->toBeInstanceOf(SimpleXMLElement::class);
        $packageRels->registerXPathNamespace('p', 'http://schemas.openxmlformats.org/package/2006/relationships');
        $officeDocument = $packageRels->xpath('/p:Relationships/p:Relationship[@Id="rId1"]');
        expect($officeDocument)->toHaveCount(1)
            ->and((string) $officeDocument[0]['Target'])->toBe('xl/workbook.xml');

        $workbookRels = simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
        expect($workbookRels)->toBeInstanceOf(SimpleXMLElement::class);
        $workbookRels->registerXPathNamespace('p', 'http://schemas.openxmlformats.org/package/2006/relationships');
        $worksheetRel = $workbookRels->xpath('/p:Relationships/p:Relationship[@Id="rId1"]');
        $stylesRel = $workbookRels->xpath('/p:Relationships/p:Relationship[@Id="rId2"]');
        expect($worksheetRel)->toHaveCount(1)
            ->and((string) $worksheetRel[0]['Target'])->toBe('worksheets/sheet1.xml')
            ->and($stylesRel)->toHaveCount(1)
            ->and((string) $stylesRel[0]['Type'])->toEndWith('/styles')
            ->and($zip->locateName('xl/'.$worksheetRel[0]['Target']))->not->toBeFalse();

        $workbookDocument = new DOMDocument;
        $workbookDocument->loadXML((string) $zip->getFromName('xl/workbook.xml'), LIBXML_NONET);
        $workbookXpath = new DOMXPath($workbookDocument);
        $workbookXpath->registerNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbookXpath->registerNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $sheet = $workbookXpath->query('/m:workbook/m:sheets/m:sheet')->item(0);
        expect($sheet)->not->toBeNull()
            ->and($sheet->getAttribute('name'))->toBe('Data Santri')
            ->and($sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id'))->toBe('rId1');

        $sheetDocument = new DOMDocument;
        $sheetDocument->loadXML((string) $zip->getFromName('xl/worksheets/sheet1.xml'), LIBXML_NONET);
        $sheetXpath = new DOMXPath($sheetDocument);
        $sheetXpath->registerNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $headers = [];

        foreach ($sheetXpath->query('/m:worksheet/m:sheetData/m:row[@r="1"]/m:c/m:is/m:t') as $node) {
            $headers[] = $node->nodeValue;
        }

        expect($headers)->toBe([
            'Nomor Induk/NIM',
            'Nama Santri',
            'Jenis Kelamin',
            'Program Studi',
            'Kelompok',
            'Periode',
            'Status Santri',
            'Email (opsional)',
        ]);

        $zip->close();
    } finally {
        @unlink($path);
    }
});

test('template XLSX dapat dibaca ulang oleh parser import Santri', function () {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $contents = $this->actingAs($admin)->get(route('santris.import.template'))->streamedContent();

    $templatePath = tempnam(sys_get_temp_dir(), 'sik-template-source-');
    $filledPath = tempnam(sys_get_temp_dir(), 'sik-template-filled-');
    file_put_contents($templatePath, $contents);

    try {
        $source = new ZipArchive;
        expect($source->open($templatePath))->toBeTrue();

        $originalSheet = (string) $source->getFromName('xl/worksheets/sheet1.xml');
        $dataRow = '<row r="2">'
            .'<c r="A2" t="inlineStr"><is><t>001234</t></is></c>'
            .'<c r="B2" t="inlineStr"><is><t>Alya Rahma</t></is></c>'
            .'<c r="C2" t="inlineStr"><is><t>Perempuan</t></is></c>'
            .'<c r="D2" t="inlineStr"><is><t>Sistem Informasi</t></is></c>'
            .'<c r="E2" t="inlineStr"><is><t>Teknologi</t></is></c>'
            .'<c r="F2" t="inlineStr"><is><t>ganjil_2026</t></is></c>'
            .'<c r="G2" t="inlineStr"><is><t>Aktif</t></is></c>'
            .'<c r="H2" t="inlineStr"><is><t>alya@example.test</t></is></c>'
            .'</row>';

        $filledSheet = str_replace('</sheetData>', $dataRow.'</sheetData>', $originalSheet);
        expect($filledSheet)->not->toBe($originalSheet);

        $target = new ZipArchive;
        expect($target->open($filledPath, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();

        for ($index = 0; $index < $source->numFiles; $index++) {
            $name = (string) $source->getNameIndex($index);
            $part = $name === 'xl/worksheets/sheet1.xml'
                ? $filledSheet
                : (string) $source->getFromIndex($index);
            expect($target->addFromString($name, $part))->toBeTrue();
        }

        expect($target->close())->toBeTrue();
        $source->close();

        $rows = app(SantriSpreadsheet::class)->read($filledPath);

        expect($rows)->toHaveCount(1)
            ->and($rows[0]['sheet'])->toBe('Data Santri')
            ->and($rows[0]['nomor_induk'])->toBe('001234')
            ->and($rows[0]['nama'])->toBe('Alya Rahma')
            ->and($rows[0]['jenis_kelamin'])->toBe('Perempuan')
            ->and($rows[0]['program_studi'])->toBe('Sistem Informasi')
            ->and($rows[0]['kelompok'])->toBe('Teknologi')
            ->and($rows[0]['periode'])->toBe('ganjil_2026')
            ->and($rows[0]['status'])->toBe('Aktif')
            ->and($rows[0]['email'])->toBe('alya@example.test');
    } finally {
        @unlink($templatePath);
        @unlink($filledPath);
    }
});

test('proses download template tidak menghasilkan output sebelum binary XLSX', function () {
    expect(function_exists('exec'))->toBeTrue();

    $scriptPath = tempnam(sys_get_temp_dir(), 'sik-template-stream-script-');
    $outputPath = tempnam(sys_get_temp_dir(), 'sik-template-stream-output-');

    file_put_contents($scriptPath, sprintf(
        "<?php\nob_start();\nrequire %s;\n\$app = require %s;\n\$app->make(\\Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();\n\$response = \$app->make(\\App\\Http\\Controllers\\SantriController::class)->downloadImportTemplate(\$app->make(\\App\\Services\\SantriSpreadsheet::class));\n\$response->sendContent();\nfile_put_contents(%s, ob_get_clean());\n",
        var_export(base_path('vendor/autoload.php'), true),
        var_export(base_path('bootstrap/app.php'), true),
        var_export($outputPath, true)
    ));

    try {
        exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg($scriptPath).' 2>&1', $output, $exitCode);

        expect($exitCode)->toBe(0);

        $streamed = (string) file_get_contents($outputPath);

        expect(substr($streamed, 0, 2))->toBe('PK')
            ->and(str_contains($streamed, '<!DOCTYPE html>'))->toBeFalse();

        $archive = new ZipArchive;
        expect($archive->open($outputPath))->toBeTrue()
            ->and($archive->getFromName('xl/workbook.xml'))->toContain('Data Santri');
        $archive->close();
    } finally {
        @unlink($scriptPath);
        @unlink($outputPath);
    }
});
