<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Site identity
    |--------------------------------------------------------------------------
    |
    | The publisher behind the graph. Every IRI minted by the site is derived
    | from "url" below, so the same triples resolve against whichever host the
    | application is currently served from.
    |
    */

    'url' => env('SEMANTIC_URL', env('APP_URL', 'http://localhost:8000')),

    'name' => env('SEMANTIC_SITE_NAME', 'Mobil Bekas Medan'),

    'description' => env(
        'SEMANTIC_SITE_DESCRIPTION',
        'Katalog mobil bekas di Medan dan sekitarnya. Temukan harga, lokasi, foto, dan pilihan cicilan untuk mobil yang Anda inginkan.'
    ),

    'language' => 'id-ID',

    'currency' => 'IDR',

    /*
    |--------------------------------------------------------------------------
    | Namespaces
    |--------------------------------------------------------------------------
    |
    | schema.org is reused as the upper ontology because the domain is already
    | described by it. Marketplace concepts that schema.org does not cover live
    | in the local "app" namespace, which the graph states explicitly with
    | owl:equivalentProperty-style annotations in the explorer.
    |
    */

    'namespaces' => [
        'mb' => env('FUSEKI_NAMESPACE', 'http://www.semanticweb.org/mobil-bekas-medan#'),
        'app' => env('SEMANTIC_APP_NAMESPACE', rtrim(env('SEMANTIC_URL', env('APP_URL', 'http://localhost:8000')), '/').'/vocab#'),
        'schema' => 'https://schema.org/',
        'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
        'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
        'owl' => 'http://www.w3.org/2002/07/owl#',
        'xsd' => 'http://www.w3.org/2001/XMLSchema#',
        'dcterms' => 'http://purl.org/dc/terms/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Datatypes
    |--------------------------------------------------------------------------
    */

    'datatypes' => [
        'string' => 'http://www.w3.org/2001/XMLSchema#string',
        'integer' => 'http://www.w3.org/2001/XMLSchema#integer',
        'decimal' => 'http://www.w3.org/2001/XMLSchema#decimal',
        'boolean' => 'http://www.w3.org/2001/XMLSchema#boolean',
        'date' => 'http://www.w3.org/2001/XMLSchema#date',
        'dateTime' => 'http://www.w3.org/2001/XMLSchema#dateTime',
        'gYear' => 'http://www.w3.org/2001/XMLSchema#gYear',
    ],

];
