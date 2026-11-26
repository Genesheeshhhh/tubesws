<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Endpoint konsol SPARQL
    |--------------------------------------------------------------------------
    |
    | Hanya kunci di bawah ini yang boleh diminta dari UI/API. URL yang dikirim
    | pengunjung tidak pernah dipakai langsung, sehingga konsol tidak bisa
    | disalahgunakan sebagai HTTP client sembarangan (SSRF).
    |
    */

    'default' => env('SPARQL_DEFAULT_ENDPOINT', 'lokal'),

    'endpoints' => [
        'lokal' => [
            'label' => 'Dataset lokal — TUBESWS (Fuseki)',
            'description' => 'Katalog mobil bekas Medan. Gunakan prefiks mb: untuk ontologi lokal.',
            'url' => rtrim((string) env('FUSEKI_URL', 'http://127.0.0.1:3030'), '/')
                .'/'.trim((string) env('FUSEKI_DATASET', 'TUBESWS'), '/').'/query',
            'timeout' => (int) env('FUSEKI_TIMEOUT', 30),
            'user_agent' => 'TUBESWS/1.0 (Laravel Semantic Web Project)',
            'local' => true,
        ],

        'wikidata' => [
            'label' => 'Wikidata (query.wikidata.org)',
            'description' => 'Dataset terbuka lintas domain: label, deskripsi, dan relasi merek mobil.',
            'url' => env('WIKIDATA_SPARQL_URL', 'https://query.wikidata.org/sparql'),
            'timeout' => (int) env('WIKIDATA_TIMEOUT', 30),
            'user_agent' => 'TUBESWS/1.0 (Laravel Semantic Web Project)',
            'local' => false,
        ],

        'dbpedia' => [
            'label' => 'DBpedia (dbpedia.org)',
            'description' => 'Data terstruktur hasil ekstraksi Wikipedia, misalnya fakta otomotif umum.',
            'url' => env('DBPEDIA_SPARQL_URL', 'https://dbpedia.org/sparql'),
            'timeout' => (int) env('DBPEDIA_TIMEOUT', 30),
            'user_agent' => 'TUBESWS/1.0 (Laravel Semantic Web Project)',
            'local' => false,
        ],
    ],

];
