<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\Import\ExcelReader;
use App\Services\Import\ListingImporter;
use App\Models\Car;
use Illuminate\Support\Str;

$r = new ExcelReader();
$rows = $r->read('olx-gabungan-kategori-rapi-pisah.xlsx');

$importer = app(ListingImporter::class);
$ref = new ReflectionClass($importer);
$m = $ref->getMethod('normalizeTransmission'); $m->setAccessible(true);
$f = $ref->getMethod('normalizeFuel'); $f->setAccessible(true);
$d = $ref->getMethod('normalizeDrivetrain'); $d->setAccessible(true);

$updated = 0;
foreach ($rows as $record) {
    $refVal = $record['order_baru'] ?? '';
    if ($refVal === '') continue;
    $car = Car::where('source','olx-import')->where('source_reference',$refVal)->first();
    if (!$car) continue;
    $t = $m->invoke($importer, $record['Transmisi'] ?? null);
    $fl = $f->invoke($importer, $record['Bahan Bakar'] ?? null);
    $dr = $d->invoke($importer, $record['Penggerak'] ?? null);
    if ($t !== $car->getRawOriginal('transmission') || $fl !== $car->getRawOriginal('fuel') || $dr !== $car->getRawOriginal('drivetrain')) {
        $car->transmission = $t;
        $car->drivetrain = $dr;
        $car->fuel = $fl;
        $car->save();
        $updated++;
    }
}
echo "updated: $updated\n";
