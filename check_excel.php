<?php
require __DIR__.'/vendor/autoload.php';

use App\Services\Import\ExcelReader;

$r = new ExcelReader();
$rows = $r->read('olx-gabungan-kategori-rapi-pisah.xlsx');
if (empty($rows)) {
    echo "no rows\n";
    exit;
}
print_r(array_keys($rows[0]));
echo "\n---\n";
print_r($rows[0]);
echo "\nCOUNT: ".count($rows)."\n";
