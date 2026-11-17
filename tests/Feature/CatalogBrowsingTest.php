<?php

namespace Tests\Feature;

use Tests\Support\InteractsWithFuseki;
use Tests\TestCase;

class CatalogBrowsingTest extends TestCase
{
    use InteractsWithFuseki;

    public function test_the_catalogue_lists_every_vehicle_in_the_graph(): void
    {
        $this->fakeFuseki();

        $this->get(route('mobil.index'))
            ->assertOk()
            ->assertSee('BMW 318i (1996)')
            ->assertSee('Toyota Avanza (2020)')
            ->assertSee('Honda Brio (2022)')
            ->assertViewHas('cars', fn ($cars): bool => $cars->total() === 3);
    }

    public function test_the_catalogue_filters_by_brand(): void
    {
        $this->fakeFuseki();

        $this->get(route('mobil.index', ['brand' => ['Toyota']]))
            ->assertOk()
            ->assertSee('Toyota Avanza (2020)')
            ->assertDontSee('Honda Brio (2022)')
            ->assertViewHas('cars', fn ($cars): bool => $cars->pluck('id')->all() === ['Mobil_6']);
    }

    public function test_the_catalogue_searches_labels_and_models(): void
    {
        $this->fakeFuseki();

        $this->get(route('mobil.index', ['q' => 'Brio']))
            ->assertOk()
            ->assertSee('Honda Brio (2022)')
            ->assertDontSee('BMW 318i (1996)')
            ->assertViewHas('cars', fn ($cars): bool => $cars->pluck('id')->all() === ['Mobil_7']);
    }

    public function test_a_misspelled_search_term_is_corrected_and_reported(): void
    {
        $this->fakeFuseki();

        $this->get(route('mobil.index', ['q' => 'toyata']))
            ->assertOk()
            ->assertSee('Toyota Avanza (2020)')
            ->assertDontSee('BMW 318i (1996)')
            ->assertSee('Tidak menemukan')
            ->assertSee('toyata')
            ->assertSee('toyota')
            ->assertViewHas('search', fn (array $search): bool => $search['original'] === 'toyata'
                && $search['resolved'] === 'toyota'
                && $search['corrected'] === true);
    }

    public function test_a_partially_typed_term_still_matches_without_being_corrected(): void
    {
        $this->fakeFuseki();

        $this->get(route('mobil.index', ['q' => 'avan']))
            ->assertOk()
            ->assertDontSee('Tidak menemukan')
            ->assertViewHas('search', fn (array $search): bool => $search['corrected'] === false)
            ->assertViewHas('cars', fn ($cars): bool => $cars->pluck('id')->all() === ['Mobil_6']);
    }

    public function test_a_multi_word_search_requires_every_token_to_match(): void
    {
        $this->fakeFuseki();

        $this->get(route('mobil.index', ['q' => 'toyota matic']))
            ->assertOk()
            ->assertViewHas('cars', fn ($cars): bool => $cars->pluck('id')->all() === ['Mobil_6']);
    }

    public function test_a_search_made_only_of_filler_words_does_not_narrow_the_catalogue(): void
    {
        $this->fakeFuseki();

        $this->get(route('mobil.index', ['q' => 'mobil murah']))
            ->assertOk()
            ->assertViewHas('cars', fn ($cars): bool => $cars->total() === 3);
    }

    public function test_the_catalogue_sorts_by_lowest_price(): void
    {
        $this->fakeFuseki();

        $this->get(route('mobil.index', ['sort' => 'harga_terendah']))
            ->assertOk()
            ->assertViewHas('cars', fn ($cars): bool => $cars->pluck('id')->all() === ['Mobil_5', 'Mobil_7', 'Mobil_6']);
    }

    public function test_the_catalogue_combines_financing_and_brand_filters(): void
    {
        $this->fakeFuseki();

        $this->get(route('mobil.index', ['with_financing' => 1]))
            ->assertOk()
            ->assertViewHas('cars', fn ($cars): bool => $cars->pluck('id')->sort()->values()->all() === ['Mobil_6', 'Mobil_7']);
    }

    public function test_a_vehicle_detail_page_shows_specifications_without_raw_rdf(): void
    {
        $this->fakeFuseki();

        $this->get(route('mobil.show', ['car' => 'Mobil_5']))
            ->assertOk()
            ->assertSee('BMW 318i (1996)')
            ->assertSee('Rp 85.000.000')
            ->assertSee('Spesifikasi')
            ->assertDontSee('Triple RDF')
            ->assertDontSee('RDF mentah');
    }

    public function test_an_unknown_vehicle_returns_not_found(): void
    {
        $this->fakeFuseki();

        $this->get(route('mobil.show', ['car' => 'Mobil_999']))->assertNotFound();
    }

    public function test_a_vehicle_can_be_downloaded_as_turtle(): void
    {
        $this->fakeFuseki();

        $this->get(route('mobil.turtle', ['car' => 'Mobil_5']))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/turtle; charset=utf-8')
            ->assertSee('MobilBekas');
    }

    public function test_a_fuseki_outage_renders_the_custom_service_page(): void
    {
        $this->fakeFuseki()->fail = true;

        $this->get(route('mobil.index'))
            ->assertStatus(503)
            ->assertSee('Katalog sedang tidak tersedia')
            ->assertSee('php artisan fuseki:load --check');
    }
}
