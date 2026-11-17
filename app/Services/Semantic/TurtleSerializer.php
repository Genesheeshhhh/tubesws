<?php

namespace App\Services\Semantic;

use App\Models\Triple;

/**
 * Serialises stored statements as RDF 1.1 Turtle.
 */
class TurtleSerializer
{
    public function __construct(private readonly Vocabulary $vocabulary) {}

    /**
     * @param  iterable<Triple>  $triples
     */
    public function serialize(iterable $triples, string $baseIri, array $extraHeader = []): string
    {
        $grouped = [];

        foreach ($triples as $triple) {
            $grouped[$triple->subject][$triple->predicate][] = $triple;
        }

        ksort($grouped);

        $lines = [];

        foreach ($this->prefixes() as $prefix => $namespace) {
            $lines[] = "@prefix {$prefix}: <{$namespace}> .";
        }

        foreach ($extraHeader as $key => $value) {
            $lines[] = "# {$key}: {$value}";
        }

        $lines[] = '';

        foreach ($grouped as $subject => $predicates) {
            $lines[] = $this->subject($subject, $baseIri).' ';

            $predicateLines = [];

            foreach ($predicates as $predicate => $objects) {
                $values = array_map(
                    fn (Triple $triple): string => $this->object($triple, $baseIri),
                    $objects,
                );

                $predicateLines[] = '    '.$this->predicate($predicate).' '.implode(' , ', $values).' ;';
            }

            $lines[] = implode("\n", $predicateLines);
            $lines[] = ' .';
            $lines[] = '';
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @return array<string, string>
     */
    public function prefixes(): array
    {
        return config('semantic.namespaces');
    }

    private function subject(string $iri, string $baseIri): string
    {
        return $this->contract($iri, $baseIri, true);
    }

    private function object(Triple $triple, string $baseIri): string
    {
        if ($triple->object_is_iri) {
            return $this->contract($triple->object, $baseIri, true);
        }

        $literal = '"'.addcslashes($triple->object, "\"\\\n\r\t").'"';

        if ($triple->datatype === null || $triple->datatype === $this->vocabulary->datatypeFor(Vocabulary::XSD_STRING)) {
            return $literal;
        }

        return $literal.'^^'.$this->contract($triple->datatype, $baseIri, true);
    }

    private function predicate(string $iri): string
    {
        return $this->contract($iri, '', false);
    }

    /**
     * Shorten an absolute IRI to a prefixed name, or a relative reference when
     * it sits underneath the base IRI.
     */
    private function contract(string $iri, string $baseIri, bool $isSubject): string
    {
        foreach ($this->prefixes() as $prefix => $namespace) {
            if ($iri === $namespace) {
                return $prefix.':';
            }

            if (str_starts_with($iri, $namespace) && $this->isValidLocalName(substr($iri, strlen($namespace)))) {
                return $prefix.':'.substr($iri, strlen($namespace));
            }
        }

        if ($baseIri !== '' && $iri !== $baseIri && str_starts_with($iri, $baseIri)) {
            return '<'.substr($iri, strlen($baseIri)).'>';
        }

        return '<'.$iri.'>';
    }

    private function isValidLocalName(string $local): bool
    {
        return $local !== ''
            && preg_match('/^[A-Za-z_][A-Za-z0-9_\-]*(\.[A-Za-z0-9_\-]+)*$/', $local) === 1;
    }
}
