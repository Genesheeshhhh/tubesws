<?php

use App\Services\Fuseki\FusekiClient;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;

/*
 * Uji cepat query SPARQL terhadap dataset TUBESWS di Apache Jena Fuseki.
 *
 * Jalankan:
 *   php check-sparql.php
 *   php check-sparql.php "SELECT * WHERE { ?s ?p ?o } LIMIT 5"
 */

require __DIR__.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$mb = (string) config('fuseki.namespace');

$query = $argv[1] ?? <<<SPARQL
    PREFIX mb: <{$mb}>
    PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>

    SELECT ?nama ?harga ?tahun
    WHERE {
      ?mobil a mb:MobilBekas ;
             rdfs:label ?nama ;
             mb:hargaNumerik ?harga ;
             mb:tahunPembuatan ?tahun .
    }
    ORDER BY DESC(?harga)
    LIMIT 10
    SPARQL;

$fuseki = app(FusekiClient::class);

echo 'Endpoint : '.$fuseki->queryEndpoint().PHP_EOL;
echo 'Query    :'.PHP_EOL.$query.PHP_EOL.PHP_EOL;

try {
    $rows = $fuseki->select($query);

    echo 'Baris    : '.count($rows).PHP_EOL;

    foreach ($rows as $index => $row) {
        echo sprintf('%2d. ', $index + 1).json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;
    }

    echo PHP_EOL.'SPARQL OK'.PHP_EOL;
    exit(0);
} catch (Throwable $exception) {
    echo 'SPARQL GAGAL: '.$exception->getMessage().PHP_EOL;
    echo 'Pastikan Fuseki berjalan dan dataset sudah dimuat: php artisan fuseki:load'.PHP_EOL;
    exit(1);
}
