<?php

namespace App\Services\Semantic\Sparql;

/**
 * A variable, an IRI, or a literal — the three kinds of term a triple pattern
 * may contain.
 */
final readonly class Term
{
    private function __construct(
        public string $kind,
        public string $value,
        public ?string $datatype = null,
        public ?string $language = null,
    ) {}

    public static function variable(string $name): self
    {
        return new self('variable', ltrim($name, '?$'));
    }

    public static function iri(string $iri): self
    {
        return new self('iri', $iri);
    }

    public static function literal(string $value, ?string $datatype = null, ?string $language = null): self
    {
        return new self('literal', $value, $datatype, $language);
    }

    public function isVariable(): bool
    {
        return $this->kind === 'variable';
    }

    public function isIri(): bool
    {
        return $this->kind === 'iri';
    }

    public function isLiteral(): bool
    {
        return $this->kind === 'literal';
    }
}
