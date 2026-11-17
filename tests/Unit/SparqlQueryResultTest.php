<?php

namespace Tests\Unit;

use App\Services\Semantic\Sparql\QueryResult;
use App\Services\Semantic\Sparql\Term;
use PHPUnit\Framework\TestCase;

class SparqlQueryResultTest extends TestCase
{
    public function test_it_projects_positional_terms_onto_the_declared_variables(): void
    {
        $result = new QueryResult(
            variables: ['mobil', 'nama'],
            rows: [
                [Term::iri('http://localhost:8000/mobil/avanza'), Term::literal('Toyota Avanza')],
                [Term::iri('http://localhost:8000/mobil/brio'), Term::literal('Honda Brio')],
            ],
            total: 2,
        );

        $this->assertSame([
            ['mobil' => 'http://localhost:8000/mobil/avanza', 'nama' => 'Toyota Avanza'],
            ['mobil' => 'http://localhost:8000/mobil/brio', 'nama' => 'Honda Brio'],
        ], $result->toArray()['rows']);
    }

    public function test_a_short_row_leaves_the_missing_variable_null(): void
    {
        $result = new QueryResult(
            variables: ['mobil', 'nama'],
            rows: [[Term::iri('http://localhost:8000/mobil/avanza')]],
            total: 1,
        );

        $this->assertSame(['mobil' => 'http://localhost:8000/mobil/avanza', 'nama' => null], $result->toArray()['rows'][0]);
    }

    public function test_it_serializes_to_the_w3c_results_shape(): void
    {
        $result = new QueryResult(
            variables: ['mobil'],
            rows: [[Term::iri('http://localhost:8000/mobil/avanza')]],
            total: 1,
        );

        $this->assertSame([
            'head' => ['vars' => ['mobil']],
            'results' => [
                'bindings' => [
                    ['mobil' => ['type' => 'uri', 'value' => 'http://localhost:8000/mobil/avanza']],
                ],
            ],
        ], $result->toSparqlJson());
    }

    public function test_a_literal_binding_reports_its_datatype(): void
    {
        $result = new QueryResult(
            variables: ['harga'],
            rows: [[Term::literal('150000000', 'http://www.w3.org/2001/XMLSchema#integer')]],
            total: 1,
        );

        $this->assertSame([
            'type' => 'literal',
            'value' => '150000000',
            'datatype' => 'http://www.w3.org/2001/XMLSchema#integer',
        ], $result->toSparqlJson()['results']['bindings'][0]['harga']);
    }

    public function test_a_language_tagged_literal_reports_xml_lang_and_no_datatype(): void
    {
        $result = new QueryResult(
            variables: ['label'],
            rows: [[Term::literal('mobil', null, 'id')]],
            total: 1,
        );

        $binding = $result->toSparqlJson()['results']['bindings'][0]['label'];

        $this->assertSame('id', $binding['xml:lang']);
        $this->assertArrayNotHasKey('datatype', $binding);
    }

    public function test_an_unbound_variable_is_left_out_of_the_binding_entirely(): void
    {
        $result = new QueryResult(
            variables: ['mobil', 'nama'],
            rows: [[Term::iri('http://localhost:8000/mobil/avanza'), null]],
            total: 1,
        );

        $binding = $result->toSparqlJson()['results']['bindings'][0];

        $this->assertSame(['mobil'], array_keys($binding));
    }

    public function test_a_variable_left_in_a_row_is_not_treated_as_a_binding(): void
    {
        $result = new QueryResult(
            variables: ['mobil'],
            rows: [[Term::variable('mobil')]],
            total: 1,
        );

        $this->assertSame([], $result->toSparqlJson()['results']['bindings'][0]);
    }

    public function test_it_serializes_to_csv_with_a_header_row(): void
    {
        $result = new QueryResult(
            variables: ['mobil', 'nama'],
            rows: [
                [Term::iri('http://localhost:8000/mobil/avanza'), Term::literal('Toyota, Avanza')],
                [Term::iri('http://localhost:8000/mobil/brio'), null],
            ],
            total: 2,
        );

        $this->assertSame(
            "mobil,nama\nhttp://localhost:8000/mobil/avanza,\"Toyota, Avanza\"\nhttp://localhost:8000/mobil/brio,\n",
            $result->toCsv(),
        );
    }

    public function test_an_empty_result_still_declares_its_variables(): void
    {
        $result = new QueryResult(variables: ['mobil'], rows: [], total: 0);

        $this->assertSame(['head' => ['vars' => ['mobil']], 'results' => ['bindings' => []]], $result->toSparqlJson());
        $this->assertSame("mobil\n", $result->toCsv());
    }
}
