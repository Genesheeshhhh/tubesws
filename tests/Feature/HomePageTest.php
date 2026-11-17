<?php

namespace Tests\Feature;

use Tests\Support\InteractsWithFuseki;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use InteractsWithFuseki;

    public function test_the_home_page_renders_the_catalogue_from_fuseki(): void
    {
        $this->fakeFuseki();

        $this->get(route('beranda'))
            ->assertOk()
            ->assertSee('BMW 318i (1996)')
            ->assertSee('iklan aktif di Medan');
    }

    public function test_the_home_page_reports_catalogue_totals(): void
    {
        $this->fakeFuseki();

        $statistics = $this->get(route('beranda'))->assertOk()->viewData('statistics');

        $this->assertSame(3, $statistics['total']);
        $this->assertSame(3, $statistics['brands']);
        $this->assertSame(3, $statistics['locations']);
        $this->assertSame(85_000_000, $statistics['lowest']);
        $this->assertSame(180_000_000, $statistics['highest']);
    }

    public function test_the_home_page_publishes_website_json_ld_with_a_search_action(): void
    {
        $this->fakeFuseki();

        $jsonLd = $this->get(route('beranda'))->assertOk()->viewData('jsonLd');

        $this->assertCount(1, $jsonLd);
        $this->assertSame('https://schema.org', $jsonLd[0]['@context']);
        $this->assertSame('WebSite', $jsonLd[0]['@type']);
        $this->assertSame('Organization', $jsonLd[0]['publisher']['@type']);
        $this->assertStringContainsString('{search_term_string}', $jsonLd[0]['potentialAction']['target']['urlTemplate']);
    }

    public function test_public_pages_do_not_expose_semantic_tools(): void
    {
        $this->fakeFuseki();

        $this->get(route('beranda'))
            ->assertOk()
            ->assertDontSee('Web semantik')
            ->assertDontSee('SPARQL');

        $this->get(route('mobil.index'))
            ->assertOk()
            ->assertDontSee('Web semantik')
            ->assertDontSee('SPARQL');
    }

    public function test_an_empty_graph_still_renders_both_entry_pages(): void
    {
        $this->fakeFuseki()->listings = [];

        $this->get(route('beranda'))->assertOk()->assertSee('0');
        $this->get(route('mobil.index'))->assertOk()->assertSee('Belum ada mobil yang cocok');
    }
}
