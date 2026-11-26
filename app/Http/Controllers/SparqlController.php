<?php

namespace App\Http\Controllers;

use App\Services\Fuseki\FusekiClient;
use App\Services\Fuseki\FusekiException;
use App\Services\Fuseki\SparqlResult;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * SPARQL explorer and machine endpoint.
 *
 * Queries are executed by Fuseki itself, not by a parser in this application,
 * so visitors can use the full SPARQL 1.1 language against the TUBESWS dataset.
 */
class SparqlController extends Controller
{
    public function index(Request $request, FusekiClient $fuseki): View
    {
        $query = trim((string) $request->input('query', ''));
        $result = null;
        $error = null;

        if ($query !== '') {
            try {
                $result = $this->execute($query, $fuseki);
            } catch (FusekiException $exception) {
                $error = $exception->getMessage();
            }
        }

        return view('semantic.sparql', [
            'query' => $query,
            'result' => $result,
            'error' => $error,
            'examples' => $this->examples(),
            'dataset' => [
                'name' => $fuseki->datasetName(),
                'url' => $fuseki->datasetUrl(),
                'query' => $fuseki->queryEndpoint(),
            ],
        ]);
    }

    public function query(Request $request, FusekiClient $fuseki): Response|JsonResponse
    {
        $query = trim((string) $request->input('query', ''));

        if ($query === '') {
            return response()->json(['error' => 'Parameter "query" wajib diisi.'], 400);
        }

        try {
            $accept = $this->acceptHeader($request);
            $response = $fuseki->raw($query, $accept);

            return response($response->body(), 200, [
                'Content-Type' => $response->header('Content-Type') ?: $accept,
            ]);
        } catch (FusekiException $exception) {
            if ($request->expectsJson() || ! str_contains((string) $request->header('Accept'), 'text/html')) {
                return response()->json(['error' => $exception->getMessage()], 400);
            }

            return response($exception->getMessage(), 400, ['Content-Type' => 'text/plain; charset=utf-8']);
        }
    }

    private function execute(string $query, FusekiClient $fuseki): SparqlResult
    {
        return match (SparqlResult::kindOf($query)) {
            'ask' => SparqlResult::fromAsk($fuseki->selectJson($query)),
            'construct' => SparqlResult::fromConstruct($fuseki->construct($query)),
            default => SparqlResult::fromSelect($fuseki->selectJson($query)),
        };
    }

    private function acceptHeader(Request $request): string
    {
        $accept = (string) $request->header('Accept');

        return match (true) {
            str_contains($accept, 'text/csv') => 'text/csv',
            str_contains($accept, 'text/turtle') => 'text/turtle',
            str_contains($accept, 'application/rdf+xml') => 'application/rdf+xml',
            str_contains($accept, 'application/n-triples') => 'application/n-triples',
            default => 'application/sparql-results+json',
        };
    }

    /**
     * @return list<array{title: string, description: string, query: string}>
     */
    private function examples(): array
    {
        $mb = (string) config('fuseki.namespace');

        return [
            [
                'title' => 'Sepuluh mobil termahal',
                'description' => 'Membaca label, harga, dan tahun langsung dari individu MobilBekas.',
                'query' => <<<SPARQL
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
                    SPARQL,
            ],
            [
                'title' => 'Mobil Toyota di Medan Petisah',
                'description' => 'Menelusuri relasi objek: manufaktur dan lokasi ditautkan sebagai node, bukan teks bebas.',
                'query' => <<<SPARQL
                    PREFIX mb: <{$mb}>
                    PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>

                    SELECT ?nama ?harga WHERE {
                      ?mobil a mb:MobilBekas ;
                             rdfs:label ?nama ;
                             mb:hargaNumerik ?harga ;
                             mb:memilikiManufaktur mb:Manufaktur_Toyota ;
                             mb:berlokasiDi mb:Lokasi_Medan_Petisah .
                    }
                    ORDER BY ?harga
                    LIMIT 25
                    SPARQL,
            ],
            [
                'title' => 'Jumlah unit per merek',
                'description' => 'Agregasi GROUP BY dan COUNT untuk melihat persebaran manufaktur.',
                'query' => <<<SPARQL
                    PREFIX mb: <{$mb}>
                    PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>

                    SELECT ?merek (COUNT(?mobil) AS ?jumlah)
                    WHERE {
                      ?mobil a mb:MobilBekas ;
                             mb:memilikiManufaktur ?manufaktur .
                      ?manufaktur rdfs:label ?merek .
                    }
                    GROUP BY ?merek
                    ORDER BY DESC(?jumlah)
                    SPARQL,
            ],
            [
                'title' => 'Mobil yang bisa dicicil',
                'description' => 'Properti opsional hanya muncul pada sebagian individu, jadi gunakan OPTIONAL/FILTER.',
                'query' => <<<SPARQL
                    PREFIX mb: <{$mb}>
                    PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>

                    SELECT ?nama ?cicilan ?tenor ?dp
                    WHERE {
                      ?mobil a mb:MobilBekas ;
                             rdfs:label ?nama ;
                             mb:cicilan ?cicilan .
                      OPTIONAL { ?mobil mb:jangkaWaktu ?tenor }
                      OPTIONAL { ?mobil mb:uangMuka ?dp }
                    }
                    LIMIT 25
                    SPARQL,
            ],
            [
                'title' => 'Kelas dan jumlah individu',
                'description' => 'Menghitung instans setiap kelas OWL yang didefinisikan di ontologi.',
                'query' => <<<SPARQL
                    PREFIX mb: <{$mb}>
                    PREFIX owl: <http://www.w3.org/2002/07/owl#>

                    SELECT ?kelas (COUNT(?individu) AS ?jumlah)
                    WHERE {
                      ?kelas a owl:Class .
                      OPTIONAL { ?individu a ?kelas }
                    }
                    GROUP BY ?kelas
                    ORDER BY DESC(?jumlah)
                    SPARQL,
            ],
            [
                'title' => 'Mobil dengan foto',
                'description' => 'Sebagian iklan tidak menyertakan gambar; FILTER memisahkan yang punya tautan foto.',
                'query' => <<<SPARQL
                    PREFIX mb: <{$mb}>
                    PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>

                    SELECT ?nama ?foto WHERE {
                      ?mobil a mb:MobilBekas ;
                             rdfs:label ?nama ;
                             mb:gambarURL ?foto .
                    }
                    LIMIT 25
                    SPARQL,
            ],

            [
                'title' => 'Mobil tanpa foto (Negasi)',
                'description' => 'Menggunakan FILTER NOT EXISTS untuk mencari mobil yang tidak memiliki gambar.',
                'query' => <<<SPARQL
                    PREFIX mb: <{$mb}>
                    PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>

                    SELECT ?nama
                    WHERE {
                    ?mobil a mb:MobilBekas ;
                            rdfs:label ?nama .

                    FILTER NOT EXISTS {
                        ?mobil mb:gambarURL ?foto .
                    }
                    }
                    LIMIT 25
                    SPARQL,
            ],
        ];
    }
}
