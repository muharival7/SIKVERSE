<?php

    namespace App\Services;

    use RuntimeException;
    use SimpleXMLElement;
    use ZipArchive;

    class SantriSpreadsheet
    {
        private const MAIN_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

        private const DOCUMENT_REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

        private const PACKAGE_REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

        private const MAX_ROWS = 1500;

        /**
         * @return list<array{row: int, nomor_induk: string, nama: string, jenis_kelamin: string, program_studi: string, kelompok: string, periode: string, status: string, email: string}>
         */
        public function read(string $filePath): array
        {
            $archive = new ZipArchive;

            if ($archive->open($filePath) !== true) {
                throw new RuntimeException('Berkas tidak dapat dibaca sebagai Excel .xlsx.');
            }

            try {
                $this->activeSharedStrings = $this->readSharedStrings($archive);
                $sheetPath = $this->firstWorksheetPath($archive);
                $sheetStats = $archive->statName($sheetPath);

                if (! is_array($sheetStats) || (int) ($sheetStats['size'] ?? 0) > 20_000_000) {
                    throw new RuntimeException('Ukuran isi lembar Excel terlalu besar.');
                }

                $sheetContents = $archive->getFromName($sheetPath);

                if (! is_string($sheetContents)) {
                    throw new RuntimeException('Lembar pertama pada Excel tidak ditemukan.');
                }

                $worksheet = $this->loadXml($sheetContents, 'Struktur lembar Excel tidak valid.');
                $worksheet->registerXPathNamespace('main', self::MAIN_NS);
                $rows = $worksheet->xpath('//main:sheetData/main:row') ?: [];
                $headerRow = null;
                $normalizedHeaders = [];
                $headerColumns = [];
                $result = [];
                $aliases = $this->headerAliases();

                foreach ($rows as $rowXml) {
                    $rowNumber = (int) ($rowXml['r'] ?? 0);
                    $cells = $this->readCells($rowXml);

                    if ($rowNumber === 1) {
                        $headerRow = $cells;

                        foreach ($cells as $column => $value) {
                            $header = $this->normalizeHeader($value);

                            if ($header === '') {
                                continue;
                            }

                            if (! isset($aliases[$header])) {
                                throw new RuntimeException("Kolom Excel tidak dikenal: {$value}.");
                            }

                            $field = $aliases[$header];

                            if (isset($normalizedHeaders[$field])) {
                                throw new RuntimeException("Kolom {$field} muncul lebih dari satu kali.");
                            }

                            $normalizedHeaders[$field] = true;
                            $headerColumns[$column] = $field;
                        }

                        foreach (['nomor_induk', 'nama', 'jenis_kelamin', 'kelompok'] as $requiredHeader) {
                            if (! isset($normalizedHeaders[$requiredHeader])) {
                                throw new RuntimeException("Kolom wajib {$requiredHeader} tidak ditemukan pada baris pertama.");
                            }
                        }

                        continue;
                    }

                    if ($rowNumber < 2) {
                        continue;
                    }

                    $values = array_fill_keys([
                        'nomor_induk',
                        'nama',
                        'jenis_kelamin',
                        'program_studi',
                        'kelompok',
                        'periode',
                        'status',
                        'email',
                    ], '');

                    foreach ($headerColumns as $column => $field) {
                        $values[$field] = trim((string) ($cells[$column] ?? ''));
                    }

                    if (implode('', $values) === '') {
                        continue;
                    }

                    $values['row'] = $rowNumber;
                    $result[] = $values;

                    if (count($result) > self::MAX_ROWS) {
                        throw new RuntimeException('Excel melebihi batas 1.500 baris data per proses.');
                    }
                }

                if ($headerRow === null) {
                    throw new RuntimeException('Baris judul kolom tidak ditemukan.');
                }

                return $result ?? [];
            } finally {
                $archive->close();
            }
        }

        public function template(): string
        {
            $temporaryPath = tempnam(sys_get_temp_dir(), 'sik-santri-template-');

            if ($temporaryPath === false) {
                throw new RuntimeException('Template Excel tidak dapat dibuat saat ini.');
            }

            $archive = new ZipArchive;

            if ($archive->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                @unlink($temporaryPath);
                throw new RuntimeException('Template Excel tidak dapat dibuat saat ini.');
            }

            $headers = [
                'Nomor Induk/NIM',
                'Nama Santri',
                'Jenis Kelamin',
                'Program Studi',
                'Kelompok',
                'Periode',
                'Status Santri',
                'Email (opsional)',
            ];

            $headerCells = '';

            foreach ($headers as $index => $header) {
                $column = $this->columnName($index + 1);
                $headerCells .= '<c r="' . $column . '1" s="1" t="inlineStr"><is><t>' . htmlspecialchars($header, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</t></is></c>';
            }

            $archive->addFromString('[Content_Types].xml', $this->contentTypesXml());
            $archive->addFromString('_rels/.rels', $this->packageRelationshipsXml());
            $archive->addFromString('xl/workbook.xml', $this->workbookXml());
            $archive->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
            $archive->addFromString('xl/styles.xml', $this->stylesXml());
            $archive->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($headerCells));
            $archive->close();

            $contents = file_get_contents($temporaryPath);
            @unlink($temporaryPath);

            if (! is_string($contents)) {
                throw new RuntimeException('Template Excel tidak dapat dibaca saat ini.');
            }

            return $contents;
        }
        

        /**
         * @return array<string, string>
         */
        private function headerAliases(): array
        {
            return [
                'nim' => 'nomor_induk',
                'nomor induk' => 'nomor_induk',
                'nomor induk nim' => 'nomor_induk',
                'nama' => 'nama',
                'nama santri' => 'nama',
                'nama lengkap' => 'nama',
                'jenis kelamin' => 'jenis_kelamin',
                'gender' => 'jenis_kelamin',
                'program studi' => 'program_studi',
                'prodi' => 'program_studi',
                'kelompok' => 'kelompok',
                'nama kelompok' => 'kelompok',
                'periode' => 'periode',
                'periode sik' => 'periode',
                'semester' => 'periode',
                'status' => 'status',
                'status santri' => 'status',
                'email' => 'email',
                'email opsional' => 'email',
            ];
        }

        private function normalizeHeader(string $header): string
        {
            $header = mb_strtolower(trim($header));
            $header = str_replace(['_', '-', '/', '(', ')'], ' ', $header);

            return trim((string) preg_replace('/\s+/', ' ', $header));
        }

        /**
         * @return array<int, string>
         */
        private function readCells(SimpleXMLElement $row): array
        {
            $row->registerXPathNamespace('main', self::MAIN_NS);
            $cells = [];

            foreach ($row->xpath('main:c') ?: [] as $cell) {
                $column = $this->columnIndex((string) $cell['r']);
                $type = (string) $cell['t'];
                $cell->registerXPathNamespace('main', self::MAIN_NS);
                $value = '';

                if ($type === 'inlineStr') {
                    $textNodes = $cell->xpath('.//main:t') ?: [];
                    $value = implode('', array_map(static fn(SimpleXMLElement $node): string => (string) $node, $textNodes));
                } else {
                    $valueNodes = $cell->xpath('main:v') ?: [];
                    $value = isset($valueNodes[0]) ? (string) $valueNodes[0] : '';

                    if ($type === 's') {
                        $sharedStrings = $this->activeSharedStrings;
                        $value = $sharedStrings[(int) $value] ?? '';
                    }
                }

                $cells[$column] = $value;
            }

            return $cells;
        }

        /** @var list<string> */
        private array $activeSharedStrings = [];

        private function firstWorksheetPath(ZipArchive $archive): string
        {
            $workbookContents = $archive->getFromName('xl/workbook.xml');
            $relationshipsContents = $archive->getFromName('xl/_rels/workbook.xml.rels');

            if (! is_string($workbookContents) || ! is_string($relationshipsContents)) {
                throw new RuntimeException('Berkas bukan workbook Excel yang didukung.');
            }

            if (strlen($workbookContents) > 1_000_000 || strlen($relationshipsContents) > 1_000_000) {
                throw new RuntimeException('Metadata workbook Excel melebihi batas ukuran yang diizinkan.');
            }

            $workbook = $this->loadXml($workbookContents, 'Struktur workbook Excel tidak valid.');
            $workbook->registerXPathNamespace('main', self::MAIN_NS);
            $sheets = $workbook->xpath('//main:sheets/main:sheet') ?: [];
            $relationshipId = isset($sheets[0])
                ? (string) $sheets[0]->attributes(self::DOCUMENT_REL_NS)['id']
                : '';

            if ($relationshipId === '') {
                throw new RuntimeException('Workbook Excel tidak memiliki lembar kerja.');
            }

            $relationships = $this->loadXml($relationshipsContents, 'Relasi workbook Excel tidak valid.');
            $relationships->registerXPathNamespace('package', self::PACKAGE_REL_NS);
            $relationshipNodes = $relationships->xpath('/package:Relationships/package:Relationship') ?: [];

            foreach ($relationshipNodes as $relationship) {
                if ((string) $relationship['Id'] !== $relationshipId) {
                    continue;
                }

                $target = (string) $relationship['Target'];
                $path = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/' . ltrim($target, '/');
                $path = preg_replace('#/+#', '/', $path) ?? '';

                if (! str_starts_with($path, 'xl/worksheets/') || str_contains($path, '../')) {
                    throw new RuntimeException('Lokasi lembar Excel tidak valid.');
                }

                return $path;
            }

            throw new RuntimeException('Relasi lembar Excel tidak ditemukan.');
        }

        /**
         * @return list<string>
         */
        private function readSharedStrings(ZipArchive $archive): array
        {
            $stats = $archive->statName('xl/sharedStrings.xml');

            if (! is_array($stats)) {
                return [];
            }

            if ((int) ($stats['size'] ?? 0) > 5_000_000) {
                throw new RuntimeException('Daftar teks bersama pada Excel terlalu besar.');
            }

            $contents = $archive->getFromName('xl/sharedStrings.xml');

            if (! is_string($contents)) {
                throw new RuntimeException('Daftar teks bersama pada Excel tidak dapat dibaca.');
            }

            $stringsXml = $this->loadXml($contents, 'Daftar teks bersama pada Excel tidak valid.');
            $stringsXml->registerXPathNamespace('main', self::MAIN_NS);
            $values = [];

            foreach ($stringsXml->xpath('//main:si') ?: [] as $sharedString) {
                $sharedString->registerXPathNamespace('main', self::MAIN_NS);
                $textNodes = $sharedString->xpath('.//main:t') ?: [];
                $values[] = implode('', array_map(static fn(SimpleXMLElement $node): string => (string) $node, $textNodes));
            }

            return $values;
        }

        private function loadXml(string $contents, string $errorMessage): SimpleXMLElement
        {
            $xml = @simplexml_load_string($contents, SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);

            if (! $xml instanceof SimpleXMLElement) {
                throw new RuntimeException($errorMessage);
            }

            return $xml;
        }

        private function columnIndex(string $cellReference): int
        {
            if (! preg_match('/^([A-Z]+)\d+$/i', $cellReference, $matches)) {
                throw new RuntimeException('Alamat sel Excel tidak valid.');
            }

            $index = 0;

            foreach (str_split(strtoupper($matches[1])) as $character) {
                $index = ($index * 26) + ord($character) - 64;
            }

            return $index - 1;
        }

        private function columnName(int $index): string
        {
            $name = '';

            while ($index > 0) {
                $remainder = ($index - 1) % 26;
                $name = chr(65 + $remainder) . $name;
                $index = intdiv($index - 1, 26);
            }

            return $name;
        }

        private function contentTypesXml(): string
        {
            return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                . '<Default Extension="xml" ContentType="application/xml"/>'
                . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
                . '</Types>';
        }

        private function packageRelationshipsXml(): string
        {
            return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="' . self::PACKAGE_REL_NS . '">'
                . '<Relationship Id="rId1" Type="' . self::DOCUMENT_REL_NS . '/officeDocument" Target="xl/workbook.xml"/>'
                . '</Relationships>';
        }

        private function workbookXml(): string
        {
            return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<workbook xmlns="' . self::MAIN_NS . '" xmlns:r="' . self::DOCUMENT_REL_NS . '">'
                . '<sheets><sheet name="Data Santri" sheetId="1" r:id="rId1"/></sheets>'
                . '</workbook>';
        }

        private function workbookRelationshipsXml(): string
        {
            return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<Relationships xmlns="' . self::PACKAGE_REL_NS . '">'
                . '<Relationship Id="rId1" Type="' . self::DOCUMENT_REL_NS . '/worksheet" Target="worksheets/sheet1.xml"/>'
                . '<Relationship Id="rId2" Type="' . self::DOCUMENT_REL_NS . '/styles" Target="styles.xml"/>'
                . '</Relationships>';
        }

        private function stylesXml(): string
        {
            return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<styleSheet xmlns="' . self::MAIN_NS . '">'
                . '<numFmts count="1"><numFmt numFmtId="164" formatCode="@"/></numFmts>'
                . '<fonts count="2"><font><sz val="11"/><name val="Aptos"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Aptos"/></font></fonts>'
                . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF760D12"/><bgColor indexed="64"/></patternFill></fill></fills>'
                . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
                . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
                . '<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>'
                . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
                . '</styleSheet>';
        }

        private function worksheetXml(string $headerCells): string
        {
            return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                . '<worksheet xmlns="' . self::MAIN_NS . '">'
                . '<dimension ref="A1:H1"/><sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
                . '<cols><col min="1" max="1" width="22" customWidth="1" style="2"/><col min="2" max="2" width="28" customWidth="1"/><col min="3" max="8" width="20" customWidth="1"/></cols>'
                . '<sheetData><row r="1" ht="26" customHeight="1">' . $headerCells . '</row></sheetData>'
                . '<autoFilter ref="A1:H1"/><pageMargins left="0.25" right="0.25" top="0.5" bottom="0.5" header="0.2" footer="0.2"/>'
                . '</worksheet>';
        }
    }
