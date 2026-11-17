<?php

namespace App\Services\Semantic\Sparql;

final readonly class SparqlQuery
{
    /**
     * @param  array<string, string>  $prefixes
     * @param  list<string>|null  $select  null selects every projected variable
     * @param  list<TriplePattern>  $patterns
     */
    public function __construct(
        public array $prefixes,
        public ?array $select,
        public bool $distinct,
        public array $patterns,
        public ?int $limit,
        public ?int $offset,
    ) {}
}
