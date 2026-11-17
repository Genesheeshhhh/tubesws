<?php

namespace App\Services\Fuseki;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Small client for the Apache Jena Fuseki SPARQL 1.1 endpoint.
 *
 * Everything the application knows about the catalogue comes through here, so
 * the dataset name and endpoint live in one place instead of being repeated in
 * controllers and views.
 */
class FusekiClient
{
    public function endpoint(): string
    {
        return rtrim((string) config('fuseki.base_url'), '/')
            .'/'.trim((string) config('fuseki.dataset'), '/');
    }

    public function queryEndpoint(): string
    {
        return $this->endpoint().'/query';
    }

    public function updateEndpoint(): string
    {
        return $this->endpoint().'/update';
    }

    public function graphStoreEndpoint(): string
    {
        return $this->endpoint().'/data';
    }

    /**
     * @return list<array<string, string>>
     */
    public function select(string $sparql): array
    {
        $json = $this->selectJson($sparql);

        $bindings = $json['results']['bindings'] ?? [];

        return array_map(
            fn (array $row): array => array_map(
                fn (array $term): string => (string) ($term['value'] ?? ''),
                $row,
            ),
            $bindings,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function selectJson(string $sparql): array
    {
        $response = $this->request()
            ->withHeaders(['Accept' => 'application/sparql-results+json'])
            ->asForm()
            ->post($this->queryEndpoint(), ['query' => $sparql]);

        if ($response->failed()) {
            throw FusekiException::queryFailed($this->queryEndpoint(), $this->failureReason($response));
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw FusekiException::queryFailed($this->queryEndpoint(), 'respons bukan JSON');
        }

        return $json;
    }

    public function ask(string $sparql): bool
    {
        $json = $this->selectJson($sparql);

        return (bool) ($json['boolean'] ?? false);
    }

    /**
     * Run a CONSTRUCT/DESCRIBE query and return its serialised graph.
     */
    public function construct(string $sparql, string $accept = 'text/turtle'): string
    {
        $response = $this->request()
            ->withHeaders(['Accept' => $accept])
            ->asForm()
            ->post($this->queryEndpoint(), ['query' => $sparql]);

        if ($response->failed()) {
            throw FusekiException::queryFailed($this->queryEndpoint(), $this->failureReason($response));
        }

        return $response->body();
    }

    public function raw(string $sparql, string $accept = 'application/sparql-results+json'): Response
    {
        $response = $this->request()
            ->withHeaders(['Accept' => $accept])
            ->asForm()
            ->post($this->queryEndpoint(), ['query' => $sparql]);

        if ($response->failed()) {
            throw FusekiException::queryFailed($this->queryEndpoint(), $this->failureReason($response));
        }

        return $response;
    }

    public function update(string $sparql): void
    {
        $response = $this->request()
            ->asForm()
            ->post($this->updateEndpoint(), ['update' => $sparql]);

        if ($response->failed()) {
            throw FusekiException::queryFailed($this->updateEndpoint(), $this->failureReason($response));
        }
    }

    /**
     * Upload an RDF document into the default graph of the dataset.
     */
    public function upload(string $contents, string $contentType = 'application/rdf+xml'): void
    {
        $response = $this->request()
            ->withHeaders(['Content-Type' => $contentType])
            ->withBody($contents, $contentType)
            ->put($this->graphStoreEndpoint().'?default');

        if ($response->failed()) {
            throw FusekiException::queryFailed($this->graphStoreEndpoint(), $this->failureReason($response));
        }
    }

    public function clearDefaultGraph(): void
    {
        $response = $this->request()->delete($this->graphStoreEndpoint().'?default');

        if ($response->failed() && $response->status() !== 404) {
            throw FusekiException::queryFailed($this->graphStoreEndpoint(), $this->failureReason($response));
        }
    }

    /**
     * Download the whole default graph, used by the "unduh graf" link.
     */
    public function graph(string $accept = 'text/turtle'): string
    {
        $response = $this->request()
            ->withHeaders(['Accept' => $accept])
            ->get($this->graphStoreEndpoint().'?default');

        if ($response->failed()) {
            throw FusekiException::queryFailed($this->graphStoreEndpoint(), $this->failureReason($response));
        }

        return $response->body();
    }

    /**
     * Create the dataset through the Fuseki admin API when it does not exist.
     */
    public function ensureDataset(): void
    {
        $datasets = $this->request()
            ->withHeaders(['Accept' => 'application/json'])
            ->get(rtrim((string) config('fuseki.base_url'), '/').'/$/datasets');

        if ($datasets->failed()) {
            throw FusekiException::unreachable($this->endpoint(), $this->failureReason($datasets));
        }

        $names = collect($datasets->json('datasets', []))
            ->map(fn (array $dataset): string => (string) ($dataset['ds.name'] ?? ''))
            ->all();

        if (in_array('/'.config('fuseki.dataset'), $names, true)) {
            return;
        }

        $created = $this->request()
            ->asForm()
            ->post(rtrim((string) config('fuseki.base_url'), '/').'/$/datasets', [
                'dbName' => (string) config('fuseki.dataset'),
                'dbType' => 'tdb2',
            ]);

        if ($created->failed()) {
            throw FusekiException::queryFailed($this->endpoint(), $this->failureReason($created));
        }
    }

    public function isAvailable(): bool
    {
        try {
            $response = $this->request()->get(rtrim((string) config('fuseki.base_url'), '/').'/$/ping');

            return $response->successful();
        } catch (ConnectionException) {
            return false;
        }
    }

    public function datasetUrl(): string
    {
        return $this->endpoint();
    }

    public function datasetName(): string
    {
        return (string) config('fuseki.dataset');
    }

    private function request(): PendingRequest
    {
        $request = Http::timeout((int) config('fuseki.timeout', 30))
            ->retry(2, 250, throw: false)
            ->acceptJson();

        if (filled(config('fuseki.username'))) {
            $request = $request->withBasicAuth(
                (string) config('fuseki.username'),
                (string) config('fuseki.password'),
            );
        }

        return $request;
    }

    private function failureReason(Response $response): string
    {
        $body = Str::limit(trim($response->body()), 300);

        return "HTTP {$response->status()}".($body !== '' ? " — {$body}" : '');
    }
}
