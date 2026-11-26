<?php

namespace App\Services\Sparql;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Read-only client for the public SPARQL endpoints listed in config/sparql.php.
 *
 * The URL is always supplied by the application, never by the visitor. Only
 * GET requests with an explicit Accept header are issued, so the explorer can
 * query Wikidata/DBpedia without exposing an update endpoint or an open proxy.
 */
class ExternalSparqlClient
{
    /**
     * @return array<string, mixed>
     */
    public function selectJson(string $url, string $query, int $timeout, string $userAgent): array
    {
        $json = $this->request($url, $query, 'application/sparql-results+json', $timeout, $userAgent)->json();

        if (! is_array($json)) {
            throw new ExternalSparqlException('Endpoint eksternal mengembalikan respons yang bukan JSON.');
        }

        return $json;
    }

    public function construct(string $url, string $query, int $timeout, string $userAgent): string
    {
        return $this->request($url, $query, 'text/turtle', $timeout, $userAgent)->body();
    }

    public function raw(string $url, string $query, string $accept, int $timeout, string $userAgent): Response
    {
        return $this->request($url, $query, $accept, $timeout, $userAgent);
    }

    private function request(
        string $url,
        string $query,
        string $accept,
        int $timeout,
        string $userAgent,
    ): Response {
        try {
            $response = Http::timeout($timeout)
                ->retry(2, 500, throw: false)
                ->withHeaders([
                    'Accept' => $accept,
                    'User-Agent' => $userAgent,
                ])
                ->get($url, ['query' => $query]);
        } catch (ConnectionException $exception) {
            throw new ExternalSparqlException(
                'Endpoint eksternal tidak dapat dihubungi: '.$exception->getMessage()
            );
        }

        if ($response->failed()) {
            throw new ExternalSparqlException(
                'Endpoint eksternal gagal: HTTP '.$response->status()
                .' — '.Str::limit(trim($response->body()), 300)
            );
        }

        return $response;
    }
}
