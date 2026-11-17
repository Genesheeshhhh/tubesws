<?php

namespace App\Services\Semantic;

use App\Services\Fuseki\FusekiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Reads the shape of the TUBESWS graph for the semantic explorer pages.
 *
 * Everything shown is derived from triples that are actually in Fuseki, so the
 * class list, predicate list and counts cannot drift away from the data.
 */
class OntologyCatalogue
{
    public function __construct(private readonly FusekiClient $fuseki) {}

    /**
     * @return array{triples: int, subjects: int, predicates: int, iriObjects: int, literalObjects: int}
     */
    public function statistics(): array
    {
        return Cache::remember($this->cacheKey('statistics'), now()->addSeconds((int) config('fuseki.cache_ttl', 300)), function (): array {
            $summary = $this->fuseki->select(
                'SELECT (COUNT(*) AS ?triples) (COUNT(DISTINCT ?s) AS ?subjects) (COUNT(DISTINCT ?p) AS ?predicates) WHERE { ?s ?p ?o }'
            )[0] ?? [];

            $iri = $this->fuseki->select(
                'SELECT (COUNT(*) AS ?n) WHERE { ?s ?p ?o . FILTER(isIRI(?o)) }'
            )[0]['n'] ?? 0;

            $literal = $this->fuseki->select(
                'SELECT (COUNT(*) AS ?n) WHERE { ?s ?p ?o . FILTER(isLiteral(?o)) }'
            )[0]['n'] ?? 0;

            return [
                'triples' => (int) ($summary['triples'] ?? 0),
                'subjects' => (int) ($summary['subjects'] ?? 0),
                'predicates' => (int) ($summary['predicates'] ?? 0),
                'iriObjects' => (int) $iri,
                'literalObjects' => (int) $literal,
            ];
        });
    }

    /**
     * @return list<array{iri: string, label: string, prefixed: string, local: bool, instances: int}>
     */
    public function classes(): array
    {
        return Cache::remember($this->cacheKey('classes'), now()->addSeconds((int) config('fuseki.cache_ttl', 300)), function (): array {
            $rows = $this->fuseki->select(<<<'SPARQL'
                PREFIX owl: <http://www.w3.org/2002/07/owl#>
                PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>

                SELECT ?class ?label (COUNT(?instance) AS ?instances)
                WHERE {
                  ?class a owl:Class .
                  OPTIONAL { ?class rdfs:label ?label }
                  OPTIONAL { ?instance a ?class }
                }
                GROUP BY ?class ?label
                ORDER BY DESC(?instances)
                SPARQL);

            return array_map(fn (array $row): array => [
                'iri' => $row['class'] ?? '',
                'label' => $row['label'] ?? Str::headline($this->localName($row['class'] ?? '')),
                'prefixed' => 'mb:'.$this->localName($row['class'] ?? ''),
                'local' => str_starts_with($row['class'] ?? '', (string) config('fuseki.namespace')),
                'instances' => (int) ($row['instances'] ?? 0),
            ], $rows);
        });
    }

    /**
     * @return list<array{iri: string, label: string, prefixed: string, type: string, typeLabel: string, uses: int}>
     */
    public function predicates(): array
    {
        return Cache::remember($this->cacheKey('predicates'), now()->addSeconds((int) config('fuseki.cache_ttl', 300)), function (): array {
            $rows = $this->fuseki->select(<<<'SPARQL'
                PREFIX owl: <http://www.w3.org/2002/07/owl#>
                PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>

                SELECT ?predicate ?label ?type (COUNT(?subject) AS ?uses)
                WHERE {
                  ?predicate a ?type .
                  FILTER(?type IN (owl:ObjectProperty, owl:DatatypeProperty))
                  OPTIONAL { ?predicate rdfs:label ?label }
                  OPTIONAL { ?subject ?predicate ?object }
                }
                GROUP BY ?predicate ?label ?type
                ORDER BY DESC(?uses)
                SPARQL);

            return array_map(function (array $row): array {
                $type = $row['type'] ?? '';

                return [
                    'iri' => $row['predicate'] ?? '',
                    'label' => $row['label'] ?? Str::headline($this->localName($row['predicate'] ?? '')),
                    'prefixed' => 'mb:'.$this->localName($row['predicate'] ?? ''),
                    'type' => $type,
                    'typeLabel' => str_ends_with($type, 'ObjectProperty') ? 'Object property' : 'Datatype property',
                    'uses' => (int) ($row['uses'] ?? 0),
                ];
            }, $rows);
        });
    }

    /**
     * @return array<string, string>
     */
    public function namespaces(): array
    {
        return [
            'mb' => (string) config('fuseki.namespace'),
            'owl' => 'http://www.w3.org/2002/07/owl#',
            'rdf' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#',
            'rdfs' => 'http://www.w3.org/2000/01/rdf-schema#',
            'xsd' => 'http://www.w3.org/2001/XMLSchema#',
            'schema' => 'https://schema.org/',
        ];
    }

    /**
     * @return list<array{subject: string, predicate: string, object: string, objectIsIri: bool, predicateLabel: string, objectLabel: ?string}>
     */
    public function statementsFor(string $subject): array
    {
        $subject = trim($subject);

        if ($subject === '') {
            return [];
        }

        $escaped = str_replace(['\\', '>'], ['\\\\', '\\>'], $subject);

        $rows = $this->fuseki->select(<<<SPARQL
            PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>

            SELECT ?p ?o ?isIri ?label ?oLabel
            WHERE {
              <{$escaped}> ?p ?o .
              BIND(isIRI(?o) AS ?isIri)
              OPTIONAL { ?p rdfs:label ?label }
              OPTIONAL { ?o rdfs:label ?oLabel }
            }
            ORDER BY ?p
            SPARQL);

        return array_map(fn (array $row): array => [
            'subject' => $subject,
            'predicate' => $row['p'] ?? '',
            'object' => $row['o'] ?? '',
            'objectIsIri' => filter_var($row['isIri'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'predicateLabel' => $row['label'] ?? Str::headline($this->localName($row['p'] ?? '')),
            'objectLabel' => filled($row['oLabel'] ?? null) ? $row['oLabel'] : null,
        ], $rows);
    }

    public function subjectTurtle(string $subject): string
    {
        $escaped = str_replace(['\\', '>'], ['\\\\', '\\>'], trim($subject));

        return $this->fuseki->construct(<<<SPARQL
            CONSTRUCT { <{$escaped}> ?p ?o }
            WHERE { <{$escaped}> ?p ?o }
            SPARQL);
    }

    public function graphTurtle(): string
    {
        return $this->fuseki->graph();
    }

    public function ontologyPath(): string
    {
        return (string) config('fuseki.ontology_path');
    }

    public function ontologyUrl(): string
    {
        return (string) config('fuseki.ontology_url');
    }

    public function datasetUrl(): string
    {
        return $this->fuseki->datasetUrl();
    }

    public function datasetName(): string
    {
        return $this->fuseki->datasetName();
    }

    public function isAvailable(): bool
    {
        return $this->fuseki->isAvailable();
    }

    public function forgetCache(): void
    {
        Cache::forget($this->cacheKey('statistics'));
        Cache::forget($this->cacheKey('classes'));
        Cache::forget($this->cacheKey('predicates'));
    }

    private function cacheKey(string $suffix): string
    {
        return 'fuseki:'.config('fuseki.dataset').':ontology:'.$suffix.':v1';
    }

    private function localName(string $iri): string
    {
        if ($iri === '') {
            return '';
        }

        if (str_contains($iri, '#')) {
            return Str::afterLast($iri, '#');
        }

        return Str::afterLast($iri, '/');
    }
}
