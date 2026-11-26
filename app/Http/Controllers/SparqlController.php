<?php

namespace App\Http\Controllers;

use App\Services\Fuseki\FusekiClient;
use App\Services\Fuseki\FusekiException;
use App\Services\Fuseki\SparqlResult;
use App\Services\Sparql\ExternalSparqlClient;
use App\Services\Sparql\ExternalSparqlException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * SPARQL explorer and machine endpoint.
 *
 * Queries are executed by Fuseki itself, not by a parser in this application,
 * so visitors can use the full SPARQL 1.1 language against the TUBESWS dataset.
 * A small allowlist of public endpoints (Wikidata, DBpedia) can be selected as
 * well; arbitrary URLs are never accepted from the request.
 */
class SparqlController extends Controller
{
    public function index(
        Request $request,
        FusekiClient $fuseki,
        ExternalSparqlClient $external,
    ): View {
        $endpointKey = $this->endpointKey($request->input('endpoint'));
        $endpoint = $this->endpoint($endpointKey);

        $query = trim((string) $request->input('query', ''));
        $result = null;
        $error = null;

        if ($query !== '') {
            try {
                $result = $this->execute($query, $fuseki, $external, $endpointKey, $endpoint);
            } catch (FusekiException|ExternalSparqlException $exception) {
                $error = $exception->getMessage();
            }
        }

        return view('semantic.sparql', [
            'query' => $query,
            'result' => $result,
            'error' => $error,
            'examples' => $this->examples($endpointKey),
            'endpointKey' => $endpointKey,
            'endpoint' => $endpoint,
            'endpoints' => $this->endpointOptions(),
            'dataset' => [
                'name' => $fuseki->datasetName(),
                'url' => $fuseki->datasetUrl(),
                'query' => $fuseki->queryEndpoint(),
            ],
        ]);
    }

    public function query(
        Request $request,
        FusekiClient $fuseki,
        ExternalSparqlClient $external,
    ): Response|JsonResponse {
        $query = trim((string) $request->input('query', ''));

        if ($query === '') {
            return response()->json(['error' => 'Parameter "query" wajib diisi.'], 400);
        }

        $requested = $request->input('endpoint');
        $endpointKey = is_string($requested) ? $requested : (string) config('sparql.default', 'lokal');

        if (! array_key_exists($endpointKey, config('sparql.endpoints', []))) {
            return response()->json(['error' => 'Endpoint tidak dikenal.'], 400);
        }

        $endpoint = $this->endpoint($endpointKey);
        $accept = $this->acceptHeader($request);

        try {
            $response = $endpointKey === 'lokal'
                ? $fuseki->raw($query, $accept)
                : $external->raw(
                    (string) $endpoint['url'],
                    $query,
                    $accept,
                    (int) $endpoint['timeout'],
                    (string) $endpoint['user_agent'],
                );

            return response($response->body(), 200, [
                'Content-Type' => $response->header('Content-Type') ?: $accept,
            ]);
        } catch (FusekiException|ExternalSparqlException $exception) {
            if ($request->expectsJson() || ! str_contains((string) $request->header('Accept'), 'text/html')) {
                return response()->json(['error' => $exception->getMessage()], 400);
            }

            return response($exception->getMessage(), 400, ['Content-Type' => 'text/plain; charset=utf-8']);
        }
    }

    /**
     * @param  array<string, mixed>  $endpoint
     */
    private function execute(
        string $query,
        FusekiClient $fuseki,
        ExternalSparqlClient $external,
        string $endpointKey,
        array $endpoint,
    ): SparqlResult {
        if ($endpointKey === 'lokal') {
            return match (SparqlResult::kindOf($query)) {
                'ask' => SparqlResult::fromAsk($fuseki->selectJson($query)),
                'construct' => SparqlResult::fromConstruct($fuseki->construct($query)),
                default => SparqlResult::fromSelect($fuseki->selectJson($query)),
            };
        }

        $url = (string) $endpoint['url'];
        $timeout = (int) $endpoint['timeout'];
        $userAgent = (string) $endpoint['user_agent'];

        return match (SparqlResult::kindOf($query)) {
            'ask' => SparqlResult::fromAsk($external->selectJson($url, $query, $timeout, $userAgent)),
            'construct' => SparqlResult::fromConstruct($external->construct($url, $query, $timeout, $userAgent)),
            default => SparqlResult::fromSelect($external->selectJson($url, $query, $timeout, $userAgent)),
        };
    }

    private function endpointKey(mixed $value): string
    {
        $key = is_string($value) ? $value : (string) config('sparql.default', 'lokal');

        if (! array_key_exists($key, config('sparql.endpoints', []))) {
            return (string) config('sparql.default', 'lokal');
        }

        return $key;
    }

    /**
     * @return array<string, mixed>
     */
    private function endpoint(string $key): array
    {
        /** @var array<string, mixed> $endpoint */
        $endpoint = config('sparql.endpoints.'.$key);

        return $endpoint;
    }

    /**
     * @return list<array{key: string, label: string, description: string, url: string}>
     */
    private function endpointOptions(): array
    {
        return collect(config('sparql.endpoints', []))
            ->map(fn (array $definition, string $key): array => [
                'key' => $key,
                'label' => (string) $definition['label'],
                'description' => (string) $definition['description'],
                'url' => (string) $definition['url'],
            ])
            ->values()
            ->all();
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
    private function examples(string $endpointKey): array
    {
        return match ($endpointKey) {
            'wikidata' => $this->wikidataExamples(),
            'dbpedia' => $this->dbpediaExamples(),
            default => $this->localExamples(),
        };
    }

    /**
     * @return list<array{title: string, description: string, query: string}>
     */
    private function localExamples(): array
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
            [
                'title' => 'Federasi lokal + Wikidata',
                'description' => 'Menggabungkan merek di TUBESWS dengan label Wikidata lewat SERVICE (butuh koneksi keluar).',
                'query' => <<<SPARQL
                    PREFIX mb: <{$mb}>
                    PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>
                    PREFIX owl: <http://www.w3.org/2002/07/owl#>

                    SELECT ?merek ?label WHERE {
                      ?mobil a mb:MobilBekas ;
                             mb:memilikiManufaktur ?brand .
                      ?brand rdfs:label ?merek ;
                             owl:sameAs ?wikidata .
                      SERVICE <https://query.wikidata.org/sparql> {
                        ?wikidata rdfs:label ?label .
                        FILTER(LANG(?label) = "en")
                      }
                    }
                    LIMIT 10
                    SPARQL,
            ],
        ];
    }

    /**
     * @return list<array{title: string, description: string, query: string}>
     */
    private function wikidataExamples(): array
    {
        return [
            [
                'title' => 'Contoh mobil di Wikidata',
                'description' => 'Label otomatis dengan wikibase:label dalam bahasa Indonesia atau Inggris.',
                'query' => <<<'SPARQL'
                    PREFIX wd: <http://www.wikidata.org/entity/>
                    PREFIX wdt: <http://www.wikidata.org/prop/direct/>
                    PREFIX wikibase: <http://wikiba.se/ontology#>
                    PREFIX bd: <http://www.bigdata.com/rdf#>

                    SELECT ?item ?itemLabel WHERE {
                      ?item wdt:P31 wd:Q42889 .
                      SERVICE wikibase:label {
                        bd:serviceParam wikibase:language "id,en".
                      }
                    }
                    LIMIT 10
                    SPARQL,
            ],
            [
                'title' => 'Detail entitas Toyota (Q80305)',
                'description' => 'Membaca label, deskripsi, dan negara asal dari satu entitas.',
                'query' => <<<'SPARQL'
                    PREFIX wd: <http://www.wikidata.org/entity/>
                    PREFIX wdt: <http://www.wikidata.org/prop/direct/>
                    PREFIX wikibase: <http://wikiba.se/ontology#>
                    PREFIX bd: <http://www.bigdata.com/rdf#>

                    SELECT ?item ?itemLabel ?itemDescription ?countryLabel WHERE {
                      VALUES ?item { wd:Q80305 }
                      OPTIONAL { ?item wdt:P17 ?country }
                      SERVICE wikibase:label {
                        bd:serviceParam wikibase:language "id,en".
                      }
                    }
                    SPARQL,
            ],
            [
                'title' => 'ASK: apakah Toyota bergolongan perusahaan?',
                'description' => 'Contoh query ASK yang menghasilkan true/false.',
                'query' => <<<'SPARQL'
                    PREFIX wd: <http://www.wikidata.org/entity/>
                    PREFIX wdt: <http://www.wikidata.org/prop/direct/>

                    ASK { wd:Q80305 wdt:P31 wd:Q4830453 }
                    SPARQL,
            ],
        ];
    }

    /**
     * @return list<array{title: string, description: string, query: string}>
     */
    private function dbpediaExamples(): array
    {
        return [
            [
                'title' => 'Properti entitas Toyota',
                'description' => 'Menampilkan predikat dan nilai yang dimiliki sumber DBpedia.',
                'query' => <<<'SPARQL'
                    PREFIX dbr: <http://dbpedia.org/resource/>

                    SELECT ?property ?value WHERE {
                      dbr:Toyota ?property ?value .
                    }
                    LIMIT 25
                    SPARQL,
            ],
            [
                'title' => 'Abstrak mobil dalam bahasa Inggris',
                'description' => 'Memakai FILTER(LANG(...)) untuk memilih terjemahan tertentu.',
                'query' => <<<'SPARQL'
                    PREFIX dbo: <http://dbpedia.org/ontology/>

                    SELECT ?car ?abstract WHERE {
                      ?car a dbo:Automobile ;
                           dbo:abstract ?abstract .
                      FILTER(LANG(?abstract) = "en")
                    }
                    LIMIT 10
                    SPARQL,
            ],
        ];
    }
}
