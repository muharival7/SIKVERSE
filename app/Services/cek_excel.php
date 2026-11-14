<?php

$path = 'C:/Users/Muharival Asril/Downloads/template-data-santri.xlsx';

$zip = new ZipArchive;

if ($zip->open($path) !== true) {
    exit('Gagal membuka ZIP');
}

$files = [
    '[Content_Types].xml',
    'xl/workbook.xml',
    'xl/styles.xml',
    'xl/worksheets/sheet1.xml',
];

foreach ($files as $file) {
    $xml = $zip->getFromName($file);

    if ($xml === false) {
        echo "$file TIDAK DITEMUKAN\n";

        continue;
    }

    libxml_use_internal_errors(true);

    if (simplexml_load_string($xml)) {
        echo "$file XML VALID\n";
    } else {
        echo "$file XML TIDAK VALID\n";
    }
}

$zip->close();
