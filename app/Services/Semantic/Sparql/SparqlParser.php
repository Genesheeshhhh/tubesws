<?php

namespace App\Services\Semantic\Sparql;

/**
 * Recursive-descent parser for the SPARQL subset the public endpoint answers.
 *
 * Supported: PREFIX declarations, SELECT (with DISTINCT and *), basic graph
 * patterns of plain triple patterns joined by shared variables, and the LIMIT
 * and OFFSET modifiers. Everything else is refused with a named error rather
 * than silently mis-evaluated.
 */
class SparqlParser
{
    private const UNSUPPORTED = [
        'OPTIONAL', 'FILTER', 'UNION', 'GRAPH', 'BIND', 'VALUES', 'MINUS',
        'SERVICE', 'GROUP', 'HAVING', 'ORDER', 'ASK', 'CONSTRUCT', 'DESCRIBE',
        'COUNT', 'SUM', 'AVG', 'MIN', 'MAX', 'SAMPLE', 'GROUP_CONCAT', 'EXISTS',
        'NOT', 'IN',
    ];

    private const PATH_CHARACTERS = ['/', '*', '+', '?', '|', '^', '!'];

    /** @var list<array{0: string, 1: string}> */
    private array $tokens = [];

    private int $position = 0;

    /**
     * Prefixes declared by the query itself, which take precedence over the
     * defaults the endpoint is configured with.
     *
     * @var array<string, string>
     */
    private array $declared = [];

    /**
     * @param  array<string, string>  $defaultPrefixes
     */
    public function parse(string $query, array $defaultPrefixes = []): SparqlQuery
    {
        $this->tokens = $this->tokenize($query);
        $this->position = 0;
        $this->declared = [];

        $prefixes = $defaultPrefixes;

        while ($this->isWord('PREFIX')) {
            $this->advance();
            // Stored without the colon, which is how expandPname() looks a
            // prefix up ("schema:name" resolves against the key "schema").
            $name = rtrim($this->expectPname('a PREFIX name')->value, ':');
            $namespace = $this->expectIri('a namespace IRI')->value;
            $prefixes[$name] = $namespace;
            $this->declared[$name] = $namespace;
        }

        $this->expectWord('SELECT', 'the query must start with SELECT');

        $distinct = false;

        while ($this->isWordAny('DISTINCT', 'REDUCED')) {
            $distinct = true;
            $this->advance();
        }

        $select = $this->parseSelectList();

        if ($this->isWord('WHERE')) {
            $this->advance();
        }

        $this->expectPunct('{', 'an opening brace before the graph pattern');
        $patterns = $this->parseTriplesBlock();
        $this->expectPunct('}', 'a closing brace after the graph pattern');

        $limit = null;
        $offset = null;

        while (! $this->atEnd()) {
            if ($this->isWord('LIMIT')) {
                $this->advance();
                $limit = $this->expectInteger('LIMIT');
            } elseif ($this->isWord('OFFSET')) {
                $this->advance();
                $offset = $this->expectInteger('OFFSET');
            } else {
                break;
            }
        }

        if (! $this->atEnd()) {
            throw new SparqlSyntaxException(
                'Unexpected input at position '.$this->position.'. '.$this->describeSupport().''
            );
        }

        if ($patterns === []) {
            throw new SparqlSyntaxException('The graph pattern must contain at least one triple pattern.');
        }

        if ($select === []) {
            $select = null;
        }

        return new SparqlQuery($prefixes, $select, $distinct, $patterns, $limit, $offset);
    }

    /**
     * @return list<string>|null
     */
    private function parseSelectList(): ?array
    {
        if ($this->isPunct('*')) {
            $this->advance();

            return null;
        }

        $variables = [];

        while ($this->currentType() === 'var') {
            $variables[] = ltrim($this->advance()['text'], '?$');
        }

        if ($variables === []) {
            throw new SparqlSyntaxException('SELECT must name at least one variable, or use *.');
        }

        return $variables;
    }

    /**
     * @return list<TriplePattern>
     */
    private function parseTriplesBlock(): array
    {
        $patterns = [];

        while (! $this->isPunct('}')) {
            if ($this->atEnd()) {
                throw new SparqlSyntaxException('The graph pattern was not closed with a brace.');
            }

            array_push($patterns, ...$this->parseTriplePattern());
        }

        return $patterns;
    }

    /**
     * A statement may bind one subject to several properties, written with
     * ";" between them and "," between objects, so one statement expands to
     * several triple patterns. Returning them all is what keeps
     * "?s schema:name ?n ; schema:brand ?b" from silently dropping ?b.
     *
     * @return list<TriplePattern>
     */
    private function parseTriplePattern(): array
    {
        $subject = $this->parseTerm('a subject');

        if ($subject->isLiteral()) {
            throw new SparqlSyntaxException('A triple pattern subject must be a variable or an IRI.');
        }

        $patterns = [];

        do {
            $predicate = $this->parsePredicate();

            foreach ($this->parseObjectList() as $object) {
                $patterns[] = new TriplePattern($subject, $predicate, $object);
            }
        } while ($this->isPunct(';') && $this->consumeSemicolon());

        if ($this->isPunct('.')) {
            $this->advance();
        }

        return $patterns;
    }

    /**
     * Consume a ";" that is followed by another predicate, treating a trailing
     * ";" before "." or "}" as the SPARQL grammar's optional final separator.
     */
    private function consumeSemicolon(): bool
    {
        $this->advance();

        if ($this->isPunct('.') || $this->isPunct('}') || $this->atEnd()) {
            return false;
        }

        return true;
    }

    /**
     * @return list<Term>
     */
    private function parseObjectList(): array
    {
        $objects = [$this->parseTerm('an object')];

        while ($this->isPunct(',')) {
            $this->advance();
            $objects[] = $this->parseTerm('an object');
        }

        return $objects;
    }

    private function parsePredicate(): Term
    {
        if ($this->isWord('a')) {
            $this->advance();

            return Term::iri($this->namespaces()['rdf'].'type');
        }

        $predicate = $this->parseTerm('a predicate');

        if ($predicate->isLiteral()) {
            throw new SparqlSyntaxException('A triple pattern predicate must be an IRI, a variable, or the keyword a.');
        }

        return $predicate;
    }

    private function parseTerm(string $role): Term
    {
        $token = $this->advance();
        $text = $token['text'];

        return match ($token['type']) {
            'var' => Term::variable($text),
            'iriref' => Term::iri(trim($text, '<>')),
            'string' => $this->finishLiteral($text),
            'pname' => Term::iri($this->expandPname($text)),
            'number' => Term::literal($text, $this->namespaces()['xsd'].'integer'),
            'bool' => Term::literal($text, $this->namespaces()['xsd'].'boolean'),
            default => throw new SparqlSyntaxException("Expected {$role} but found \"{$text}\"."),
        };
    }

    private function finishLiteral(string $raw): Term
    {
        $value = $this->decodeStringLiteral($raw);
        $datatype = null;
        $language = null;

        if ($this->isPunct('^') || $this->currentText() === '^^') {
            if ($this->currentText() === '^^') {
                $this->advance();
                $datatype = $this->parseTerm('a datatype IRI')->value;
            } else {
                throw new SparqlSyntaxException('A literal datatype must be introduced by ^^.');
            }
        } elseif ($this->currentType() === 'lang') {
            $language = ltrim($this->advance()['text'], '@');
        }

        return Term::literal($value, $datatype, $language);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    private function tokenize(string $query): array
    {
        $tokens = [];
        $length = mb_strlen($query);
        $offset = 0;

        while ($offset < $length) {
            $character = $query[$offset];

            if (preg_match('/\s/', $character) === 1) {
                $offset++;

                continue;
            }

            if ($character === '#') {
                while ($offset < $length && $query[$offset] !== "\n") {
                    $offset++;
                }

                continue;
            }

            $rest = substr($query, $offset);

            if ($character === '<'
                && preg_match('/^<((?:[^<>"{}|^`\\\\]|\\\\.)*)>/', $rest, $matches) === 1
                && preg_match('/[\x00-\x20]/', $matches[1]) === 0) {
                $tokens[] = ['type' => 'iriref', 'text' => $matches[0]];
                $offset += strlen($matches[0]);

                continue;
            }

            if ($character === '?' || $character === '$') {
                if (preg_match('/^[\?\$]([A-Za-z_][A-Za-z0-9_]*)/', $rest, $matches) === 1) {
                    $tokens[] = ['type' => 'var', 'text' => $matches[0]];
                    $offset += strlen($matches[0]);

                    continue;
                }
            }

            if ($character === '"' || $character === "'") {
                if (preg_match('/^("(?:[^"\\\\]|\\\\.)*"|\'(?:[^\'\\\\]|\\\\.)*\')/s', $rest, $matches) === 1) {
                    $tokens[] = ['type' => 'string', 'text' => $matches[0]];
                    $offset += strlen($matches[0]);

                    continue;
                }
            }

            if (preg_match('/^@([A-Za-z][A-Za-z0-9\-]*)/', $rest, $matches) === 1) {
                $tokens[] = ['type' => 'lang', 'text' => $matches[0]];
                $offset += strlen($matches[0]);

                continue;
            }

            if ($character === '^' && str_starts_with($rest, '^^')) {
                $tokens[] = ['type' => 'punct', 'text' => '^^'];
                $offset += 2;

                continue;
            }

            if (str_contains('{}.;,()^', $character)) {
                $tokens[] = ['type' => 'punct', 'text' => $character];
                $offset++;

                continue;
            }

            if (in_array($character, self::PATH_CHARACTERS, true)) {
                throw new SparqlSyntaxException(
                    "Property paths are not supported (found \"{$character}\"). Use a plain triple pattern."
                );
            }

            if (preg_match('/^[+-]?[0-9]+/', $rest, $matches) === 1) {
                $tokens[] = ['type' => 'number', 'text' => $matches[0]];
                $offset += strlen($matches[0]);

                continue;
            }

            if (preg_match('/^([A-Za-z_][A-Za-z0-9_.\-]*:)?([A-Za-z0-9_][A-Za-z0-9_\-]*)?/', $rest, $matches) === 1
                && str_contains($matches[0], ':')) {
                $tokens[] = ['type' => 'pname', 'text' => $matches[0]];
                $offset += strlen($matches[0]);

                continue;
            }

            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*/', $rest, $matches) === 1) {
                $word = $matches[0];

                foreach (self::UNSUPPORTED as $keyword) {
                    if (strcasecmp($word, $keyword) === 0) {
                        throw new SparqlSyntaxException("{$keyword} is not supported by this endpoint.");
                    }
                }

                $isBoolean = in_array(strtolower($word), ['true', 'false'], true);

                $tokens[] = ['type' => $isBoolean ? 'bool' : 'word', 'text' => $word];
                $offset += strlen($word);

                continue;
            }

            throw new SparqlSyntaxException("Cannot tokenize query at offset {$offset}.");
        }

        return $tokens;
    }

    private function expandPname(string $pname): string
    {
        [$prefix, $local] = explode(':', $pname, 2);

        $namespaces = $this->namespaces();

        if (! array_key_exists($prefix, $namespaces)) {
            throw new SparqlSyntaxException(
                "Unknown prefix \"{$prefix}:\". Declare it with PREFIX {$prefix}: <...> or use a full IRI."
            );
        }

        return $namespaces[$prefix].$local;
    }

    private function decodeStringLiteral(string $raw): string
    {
        $inner = substr($raw, 1, -1);

        return preg_replace_callback(
            '/\\\\([tbnrf"\'\\\\]|u[0-9A-Fa-f]{4}|U[0-9A-Fa-f]{8})/',
            function (array $matches): string {
                return match ($matches[1]) {
                    't' => "\t",
                    'b' => "\x08",
                    'n' => "\n",
                    'r' => "\r",
                    'f' => "\f",
                    '"' => '"',
                    "'" => "'",
                    '\\' => '\\',
                    default => mb_chr((int) hexdec(substr($matches[1], 1)), 'UTF-8'),
                };
            },
            $inner,
        ) ?? $inner;
    }

    /**
     * Prefixes the query may use: the endpoint defaults, overridden by anything
     * the query declared for itself.
     *
     * @return array<string, string>
     */
    private function namespaces(): array
    {
        return array_merge(config('semantic.namespaces'), $this->declared);
    }

    private function describeSupport(): string
    {
        return 'Supported: PREFIX, SELECT (DISTINCT or *), plain triple patterns, LIMIT and OFFSET. ';
    }

    private function expectWord(string $word, string $message): void
    {
        if (! $this->isWord($word)) {
            throw new SparqlSyntaxException($message.'. '.$this->describeSupport());
        }

        $this->advance();
    }

    private function expectPunct(string $punctuation, string $message): void
    {
        if (! $this->isPunct($punctuation)) {
            throw new SparqlSyntaxException($message.'.');
        }

        $this->advance();
    }

    private function expectPname(string $message): Term
    {
        if ($this->currentType() !== 'pname') {
            throw new SparqlSyntaxException("Expected {$message}.");
        }

        return Term::literal($this->advance()['text']);
    }

    private function expectIri(string $message): Term
    {
        if ($this->currentType() !== 'iriref') {
            throw new SparqlSyntaxException("Expected {$message}.");
        }

        return Term::iri(trim($this->advance()['text'], '<>'));
    }

    private function expectInteger(string $modifier): int
    {
        if ($this->currentType() !== 'number' || str_starts_with($this->currentText(), '-')) {
            throw new SparqlSyntaxException("{$modifier} must be followed by a non-negative integer.");
        }

        return (int) $this->advance()['text'];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function advance(): array
    {
        if ($this->atEnd()) {
            throw new SparqlSyntaxException('The query ended unexpectedly.');
        }

        return $this->tokens[$this->position++];
    }

    private function currentType(): ?string
    {
        return $this->tokens[$this->position]['type'] ?? null;
    }

    private function currentText(): ?string
    {
        return $this->tokens[$this->position]['text'] ?? null;
    }

    private function isPunct(string $punctuation): bool
    {
        return $this->currentType() === 'punct' && $this->currentText() === $punctuation;
    }

    private function isWord(string $word): bool
    {
        return $this->currentType() === 'word' && strcasecmp((string) $this->currentText(), $word) === 0;
    }

    private function isWordAny(string ...$words): bool
    {
        foreach ($words as $word) {
            if ($this->isWord($word)) {
                return true;
            }
        }

        return false;
    }

    private function atEnd(): bool
    {
        return $this->position >= count($this->tokens);
    }
}
