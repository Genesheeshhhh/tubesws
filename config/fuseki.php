<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Apache Jena Fuseki
    |--------------------------------------------------------------------------
    |
    | The catalogue is not stored in MySQL/PostgreSQL anymore: every listing is
    | read from the TUBESWS dataset in Apache Jena Fuseki. The PostgreSQL
    | connection stays configured in config/database.php so the old data can
    | still be used later without dropping it.
    |
    */

    'base_url' => env('FUSEKI_URL', 'http://127.0.0.1:3030'),

    'dataset' => env('FUSEKI_DATASET', 'TUBESWS'),

    'username' => env('FUSEKI_USERNAME'),

    'password' => env('FUSEKI_PASSWORD'),

    'timeout' => (int) env('FUSEKI_TIMEOUT', 30),

    /*
    |--------------------------------------------------------------------------
    | Ontology
    |--------------------------------------------------------------------------
    |
    | The source of truth that is loaded into Fuseki. Keeping the path in
    | configuration lets `php artisan fuseki:load` upload the exact file the
    | UI describes, from any working directory.
    |
    */

    'ontology_path' => env('FUSEKI_ONTOLOGY', base_path('mobil-bekas-medan-owl.xml')),

    'ontology_url' => env('FUSEKI_ONTOLOGY_URL', 'http://www.semanticweb.org/mobil-bekas-medan'),

    'namespace' => env('FUSEKI_NAMESPACE', 'http://www.semanticweb.org/mobil-bekas-medan#'),

    /*
    |--------------------------------------------------------------------------
    | Application cache
    |--------------------------------------------------------------------------
    |
    | Reading 914 listings plus their optional properties is one query, and the
    | data is read-only, so a short cache keeps page loads fast. The load
    | command clears this cache automatically after an import.
    |
    */

    'cache_ttl' => (int) env('FUSEKI_CACHE_TTL', 300),

];
