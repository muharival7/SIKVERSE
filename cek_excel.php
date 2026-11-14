<?php

$file = 'C:/Users/Muharival Asril/Downloads/template-data-santri.xlsx';

$content = file_get_contents($file);

$content = ltrim($content, "\r\n");

$testFile = 'C:/Users/Muharival Asril/Downloads/template-data-santri-test.xlsx';

file_put_contents($testFile, $content);

$zip = new ZipArchive;

$result = $zip->open($testFile);

var_dump($result);

if ($result === true) {
    echo "ZIP berhasil dibuka!\n";

    for ($i = 0; $i < $zip->numFiles; $i++) {
        echo $zip->getNameIndex($i)."\n";
    }

    $zip->close();
}
