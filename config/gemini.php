<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Asisten berbasis AI (opsional)
    |--------------------------------------------------------------------------
    |
    | Gemini hanya dipakai untuk memahami pertanyaan dan merangkai kalimat.
    | Filter dan hasil tetap dihitung oleh CarCatalog dari dataset Fuseki.
    | Tanpa API key, chatbot otomatis memakai aturan lama (offline).
    |
    | provider: auto  -> pakai AI bila API key tersedia, selain itu aturan
    |           ai    -> paksa AI; tetap jatuh ke aturan bila gagal
    |           rules -> jangan pernah memanggil AI
    |
    */

    'provider' => env('CHATBOT_PROVIDER', 'auto'),

    'api_key' => env('GEMINI_API_KEY'),

    'model' => env('GEMINI_MODEL', 'gemini-flash-lite-latest'),

    'url' => env(
        'GEMINI_URL',
        'https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent'
    ),

    'timeout' => (int) env('GEMINI_TIMEOUT', 12),

    'cache_ttl' => (int) env('GEMINI_CACHE_TTL', 300),

];
