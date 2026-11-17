<?php

namespace App\Services\Fuseki;

/**
 * Normalised SPARQL response so the explorer can render SELECT, ASK and
 * CONSTRUCT results without caring which shape Fuseki used on the wire.
 */
final readonly class SparqlResult
{
    /**
     * @param  list<string>  $variables
     * @param  list<array<string, array{type: string, value: string, datatype?: string, lang?: string}>>  $rows
     */
    public function __construct(
        public string $kind,
        public array $variables = [],
        public array $rows = [],
        public ?bool $boolean = null,
        public ?string $turtle = null,
    ) {}

    /**
     * @param  array<string, mixed>  $json
     */
    public static function fromSelect(array $json): self
    {
        return new self(
            kind: 'select',
            variables: array_values($json['head']['vars'] ?? []),
            rows: array_values($json['results']['bindings'] ?? []),
        );
    }

    /**
     * @param  array<string, mixed>  $json
     */
    public static function fromAsk(array $json): self
    {
        return new self(kind: 'ask', boolean: (bool) ($json['boolean'] ?? false));
    }

    public static function fromConstruct(string $turtle): self
    {
        return new self(kind: 'construct', turtle: $turtle);
    }

    public function rowCount(): int
    {
        return count($this->rows);
    }

    /**
     * Best-effort detection of the query form. PREFIX/BASE declarations are
     * skipped so the actual verb decides the result shape.
     */
    public static function kindOf(string $query): string
    {
        $withoutPrefixes = preg_replace('/^\s*(?:PREFIX|BASE)\s+.*$/mi', '', $query) ?? $query;
        $verb = strtoupper((string) strtok(ltrim($withoutPrefixes), " \t\r\n"));

        return match ($verb) {
            'ASK' => 'ask',
            'CONSTRUCT', 'DESCRIBE' => 'construct',
            default => 'select',
        };
    }
}
