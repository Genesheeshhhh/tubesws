<?php

use App\Http\Controllers\CarController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\SemanticController;
use App\Http\Controllers\SparqlController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Katalog publik
|--------------------------------------------------------------------------
|
| Seluruh data berasal dari dataset TUBESWS di Apache Jena Fuseki. Tidak ada
| data katalog yang dibaca dari PostgreSQL, sehingga basis data lama tetap utuh
| dan bisa dipakai untuk keperluan lain.
|
*/

Route::get('/', [HomeController::class, 'index'])->name('beranda');

Route::get('mobil', [CarController::class, 'index'])->name('mobil.index');

// Turtle untuk satu listing. Dideklarasikan sebelum rute detail supaya akhiran
// ".ttl" tidak dianggap bagian dari id.
Route::get('mobil/{car}.ttl', [CarController::class, 'turtle'])
    ->where('car', 'Mobil_[0-9]+')
    ->name('mobil.turtle');

Route::get('mobil/{car}', [CarController::class, 'show'])
    ->where('car', 'Mobil_[0-9]+')
    ->name('mobil.show');

/*
|--------------------------------------------------------------------------
| Asisten katalog (offline)
|--------------------------------------------------------------------------
|
| Menjawab pertanyaan bahasa alami dari data katalog yang sudah dimuat, tanpa
| memanggil layanan eksternal.
|
*/

Route::post('asisten', ChatbotController::class)
    ->middleware('throttle:30,1')
    ->name('chatbot.reply');

/*
|--------------------------------------------------------------------------
| Favorit (disimpan di session, tanpa tabel basis data)
|--------------------------------------------------------------------------
*/

Route::get('favorit', [FavoriteController::class, 'index'])->name('favorit.index');
Route::post('favorit/{car}', [FavoriteController::class, 'store'])->name('favorit.store');
Route::delete('favorit/{car}', [FavoriteController::class, 'destroy'])->name('favorit.destroy');

/*
|--------------------------------------------------------------------------
| Web semantik
|--------------------------------------------------------------------------
|
| Halaman manusia, unduhan RDF, ontologi sumber, dan endpoint SPARQL yang
| diteruskan langsung ke Fuseki.
|
*/

Route::prefix('semantik')->name('semantik.')->group(function (): void {
    Route::get('/', [SemanticController::class, 'index'])->name('index');
    Route::get('rdfa', [SemanticController::class, 'rdfa'])->name('rdfa');
    Route::get('mobil/{car}', [SemanticController::class, 'show'])
        ->where('car', 'Mobil_[0-9]+')
        ->name('show');
    Route::get('graph.ttl', [SemanticController::class, 'graph'])->name('graph');
    Route::get('ontology', [SemanticController::class, 'ontology'])->name('ontology');
    Route::get('ontology.xml', [SemanticController::class, 'ontologyDownload'])->name('ontology.download');

    Route::get('sparql', [SparqlController::class, 'index'])->name('sparql');
    Route::match(['get', 'post'], 'sparql/query', [SparqlController::class, 'query'])->name('sparql.query');
});
