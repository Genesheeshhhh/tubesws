<?php

namespace App\Services\Semantic\Sparql;

/**
 * A SPARQL SELECT result: the projected variable order and one row per
 * solution, with null for a variable the pattern left unbound.
 */
final readonly class QueryResult
{
    /**
     * @param  list<string>  $variables
     * @param  list<array<int, Term|null>>  $rows
     */
    public function __construct(
        public array $variables,
        public array $rows,
        public int $total,
    ) {}

    /**
     * @return array{variables: list<string>, rows: list<array<string, string|null>>}
     */
    public function toArray(): array
    {
        return [
            'variables' => $this->variables,
            'rows' => array_map(function (array $row): array {
                $assoc = [];

                foreach ($this->variables as $position => $variable) {
                    $term = $row[$position] ?? null;
                    $assoc[$variable] = $term?->value;
                }

                return $assoc;
            }, $this->rows),
        ];
    }

    /**
     * The result in the W3C SPARQL 1.1 Query Results JSON format.
     *
     * Rows arrive as positional arrays of terms, so the projected variable order
     * is what maps a term back to the variable it was bound for. An unbound
     * variable is left out of its row entirely, as the specification requires.
     *
     * @return array{head: array{vars: list<string>}, results: array{bindings: list<array<string, array<string, string>>>}}
     */
    public function toSparqlJson(): array
    {
        return [
            'head' => ['vars' => $this->variables],
            'results' => [
                'bindings' => array_map(function (array $row): array {
                    $bindings = [];

                    foreach ($this->variables as $position => $variable) {
                        $term = $row[$position] ?? null;

                        if (! $term instanceof Term || $term->isVariable()) {
                            continue;
                        }

                        $bindings[$variable] = array_filter([
                            'type' => $term->isLiteral() ? 'literal' : 'uri',
                            'value' => $term->value,
                            'datatype' => $term->datatype,
                            'xml:lang' => $term->language,
                        ], fn (?string $value): bool => $value !== null);
                    }

                    return $bindings;
                }, $this->rows),
            ],
        ];
    }

    public function toCsv(): string
    {
        $handle = fopen('php://temp', 'r+');

        fputcsv($handle, $this->variables, ',', '"', '');

        foreach ($this->toArray()['rows'] as $row) {
            fputcsv($handle, array_map(
                fn (?string $value): string => $value ?? '',
                array_values($row),
            ), ',', '"', '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }
}
