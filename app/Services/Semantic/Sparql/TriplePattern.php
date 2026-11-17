<?php

namespace App\Services\Semantic\Sparql;

final readonly class TriplePattern
{
    public function __construct(
        public Term $subject,
        public Term $predicate,
        public Term $object,
    ) {}
}
