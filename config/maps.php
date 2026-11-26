<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Peta katalog
    |--------------------------------------------------------------------------
    |
    | Dataset hanya menyimpan nama kecamatan, bukan koordinat. Browser
    | menerjemahkan nama tersebut lewat Nominatim (OpenStreetMap). Titik jatuh
    | back ke pusat Medan bila geocoding gagal atau sedang tidak tersedia.
    |
    */

    'fallback' => [
        'lat' => (float) env('MAP_FALLBACK_LAT', 3.5952),
        'lng' => (float) env('MAP_FALLBACK_LNG', 98.6722),
    ],

    'zoom' => (int) env('MAP_ZOOM', 12),

    'search_suffix' => env('MAP_SEARCH_SUFFIX', ', Sumatera Utara, Indonesia'),

];
