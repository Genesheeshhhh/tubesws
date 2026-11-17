<?php

namespace Tests\Feature;

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

    public function test_the_graph_is_available_as_turtle(): void
    {
        $this->fakeFuseki();

        $this->get(route('semantik.graph'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/turtle; charset=utf-8')
            ->assertSee('mb:MobilBekas');
    }
}
