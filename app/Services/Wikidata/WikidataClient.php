<?php

namespace App\Services\Wikidata;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class WikidataClient
{
    private string $endpoint = 'https://query.wikidata.org/sparql';

    public function getEntity(string $qid): array
    {
        if (! preg_match('/^Q[0-9]+$/', $qid)) {
            throw new RuntimeException('ID Wikidata tidak valid.');
        }

        $sparql = <<<SPARQL
        SELECT ?item ?itemLabel ?itemDescription
        WHERE {
            VALUES ?item { wd:{$qid} }

            SERVICE wikibase:label {
                bd:serviceParam wikibase:language "id,en".
            }
        }
        SPARQL;

        $response = Http::timeout(30)
            ->retry(2, 500)
            ->withHeaders([
                'Accept' => 'application/sparql-results+json',
                'User-Agent' => 'TUBESWS/1.0 (Laravel Semantic Web Project)',
            ])
            ->get($this->endpoint, [
                'query' => $sparql,
                'format' => 'json',
            ]);

        if ($response->failed()) {
            throw new RuntimeException(
                'Gagal menghubungi Wikidata: HTTP '.$response->status()
            );
        }

        $bindings = $response->json('results.bindings', []);

        if (empty($bindings)) {
            throw new RuntimeException('Entitas tidak ditemukan di Wikidata.');
        }

        $entity = $bindings[0];

        return [
            'id' => $qid,
            'uri' => $entity['item']['value'] ?? null,
            'label' => $entity['itemLabel']['value'] ?? $qid,
            'description' => $entity['itemDescription']['value'] ?? 'Tidak tersedia',
        ];
    }
}