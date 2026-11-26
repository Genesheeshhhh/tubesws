<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\Support\InteractsWithFuseki;
use Tests\TestCase;

class SemanticExplorerTest extends TestCase
{
    use InteractsWithFuseki;

    public function test_the_dataset_overview_lists_classes_predicates_and_prefixes(): void
    {
        $this->fakeFuseki();

        $this->get(route('semantik.index'))
            ->assertOk()
            ->assertSee('Struktur dataset')
            ->assertSee('Kelas OWL')
            ->assertSee('Mobil Bekas')
            ->assertSee('memilikiManufaktur')
            ->assertSee('TUBESWS');
    }

    public function test_a_vehicle_has_a_raw_rdf_page(): void
    {
        $this->fakeFuseki();

        $this->get(route('semantik.show', ['car' => 'Mobil_5']))
            ->assertOk()
            ->assertSee('Individu MobilBekas')
            ->assertSee('Triple subjek')
            ->assertSee('Harga Numerik')
            ->assertSee('Mobil_5');
    }

    public function test_the_ontology_page_describes_the_source_file(): void
    {
        $this->fakeFuseki();

        $this->get(route('semantik.ontology'))
            ->assertOk()
            ->assertSee('Ontologi sumber')
            ->assertSee('mobil-bekas-medan-owl.xml')
            ->assertSee('php artisan fuseki:load --replace');
    }

    public function test_the_sparql_explorer_runs_a_select_query(): void
    {
        $this->fakeFuseki();

        $this->get(route('semantik.sparql', ['query' => 'SELECT * WHERE { ?s ?p ?o } LIMIT 5']))
            ->assertOk()
            ->assertSee('Hasil')
            ->assertSee('BMW 318i (1996)')
            ->assertSee('?mobil');
    }

    public function test_the_explorer_shows_a_query_error_without_a_server_error(): void
    {
        $this->fakeFuseki()->fail = true;

        $this->get(route('semantik.sparql', ['query' => 'SELECT * WHERE { ?s ?p ?o }']))
            ->assertOk()
            ->assertSee('Query gagal')
            ->assertSee('Fuseki tidak tersedia untuk pengujian.');
    }

    public function test_the_explorer_offers_local_and_external_endpoints(): void
    {
        $this->get(route('semantik.sparql'))
            ->assertOk()
            ->assertSee('Dataset lokal')
            ->assertSee('Wikidata')
            ->assertSee('DBpedia');
    }

    public function test_the_explorer_runs_a_query_against_an_external_endpoint(): void
    {
        Http::fake([
            'query.wikidata.org/*' => Http::response([
                'head' => ['vars' => ['item', 'itemLabel']],
                'results' => ['bindings' => [[
                    'item' => ['type' => 'uri', 'value' => 'http://www.wikidata.org/entity/Q80305'],
                    'itemLabel' => ['type' => 'literal', 'value' => 'Toyota', 'lang' => 'en'],
                ]]],
            ], 200, ['Content-Type' => 'application/sparql-results+json']),
        ]);

        $this->get(route('semantik.sparql', [
            'endpoint' => 'wikidata',
            'query' => 'SELECT ?item ?itemLabel WHERE { ?item wdt:P31 wd:Q42889 } LIMIT 10',
        ]))
            ->assertOk()
            ->assertSee('Wikidata')
            ->assertSee('Toyota');
    }

    public function test_external_endpoint_failures_are_shown_as_query_errors(): void
    {
        Http::fake([
            'query.wikidata.org/*' => Http::response('service unavailable', 503),
        ]);

        $this->get(route('semantik.sparql', [
            'endpoint' => 'wikidata',
            'query' => 'SELECT ?item WHERE { ?item ?p ?o } LIMIT 1',
        ]))
            ->assertOk()
            ->assertSee('Query gagal')
            ->assertSee('Endpoint eksternal gagal');
    }

    public function test_the_raw_endpoint_forwards_queries_to_the_selected_external_endpoint(): void
    {
        Http::fake([
            'dbpedia.org/*' => Http::response('{"boolean":true}', 200, [
                'Content-Type' => 'application/sparql-results+json',
            ]),
        ]);

        $this->get(route('semantik.sparql.query', [
            'endpoint' => 'dbpedia',
            'query' => 'ASK { ?s ?p ?o }',
        ]), ['Accept' => 'application/sparql-results+json'])
            ->assertOk()
            ->assertSee('"boolean":true', false);
    }

    public function test_the_raw_endpoint_rejects_unknown_endpoints(): void
    {
        $this->getJson(route('semantik.sparql.query', [
            'endpoint' => 'http://evil.example/sparql',
            'query' => 'SELECT * WHERE { ?s ?p ?o }',
        ]))
            ->assertStatus(400)
            ->assertJson(['error' => 'Endpoint tidak dikenal.']);
    }

    public function test_the_graph_is_available_as_turtle(): void
    {
        $this->fakeFuseki();

        $this->get(route('semantik.graph'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/turtle; charset=utf-8')
            ->assertSee('mb:MobilBekas');
    }
}
