<?php

namespace Tests\Feature;

use Tests\Support\InteractsWithFuseki;
use Tests\TestCase;

class RdfaPageTest extends TestCase
{
    use InteractsWithFuseki;

    public function test_the_dedicated_page_annotates_a_vehicle_with_schema_org_facts(): void
    {
        $this->fakeFuseki();

        $this->get(route('semantik.rdfa'))
            ->assertOk()
            ->assertSee('Laman RDFa')
            ->assertSee('BMW 318i (1996)')
            ->assertSee('typeof="Vehicle"', false)
            ->assertSee('property="model"', false)
            ->assertSee('content="85000000"', false)
            ->assertSee('content="IDR"', false);
    }

    public function test_the_catalogue_detail_page_exposes_the_same_rdfa_facts(): void
    {
        $this->fakeFuseki();

        $this->get(route('mobil.show', ['car' => 'Mobil_5']))
            ->assertOk()
            ->assertSee('typeof="Vehicle"', false)
            ->assertSee('property="brand"', false)
            ->assertSee('content="BMW"', false);
    }

    public function test_the_statement_page_uses_the_graph_iri_as_the_rdfa_subject(): void
    {
        $this->fakeFuseki();

        $this->get(route('semantik.show', ['car' => 'Mobil_5']))
            ->assertOk()
            ->assertSee('typeof="Vehicle"', false)
            ->assertSee('resource="http://www.semanticweb.org/mobil-bekas-medan#Mobil_5"', false);
    }

    public function test_rdfa_attributes_escape_values_from_the_graph(): void
    {
        $this->fakeFuseki();

        $this->fuseki->listings[0]['label'] = 'BMW <script>alert(1)</script>';

        $this->get(route('semantik.rdfa'))
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }
}
