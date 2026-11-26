<?php

namespace Tests\Feature;

use Tests\Support\InteractsWithFuseki;
use Tests\TestCase;

class CarContactAndMapTest extends TestCase
{
    use InteractsWithFuseki;

    public function test_detail_page_offers_a_whatsapp_link_with_the_listing_prefilled(): void
    {
        $this->fakeFuseki();

        $this->get(route('mobil.show', ['car' => 'Mobil_5']))
            ->assertOk()
            ->assertSee('Tanya via WhatsApp')
            ->assertSee('wa.me/6285277027100', false)
            ->assertSee('Halo%2C%20saya%20tertarik%20dengan%20BMW%20318i%20%281996%29', false);
    }

    public function test_international_whatsapp_numbers_are_normalized(): void
    {
        $this->fakeFuseki();
        config()->set('services.whatsapp.number', '+62 852-7702-7100');

        $this->get(route('mobil.show', ['car' => 'Mobil_5']))
            ->assertOk()
            ->assertSee('wa.me/6285277027100', false);
    }

    public function test_detail_page_renders_an_openstreetmap_panel_for_the_location(): void
    {
        $this->fakeFuseki();

        $this->get(route('mobil.show', ['car' => 'Mobil_5']))
            ->assertOk()
            ->assertSee('Lokasi unit')
            ->assertSee('id="car-map"', false)
            ->assertSee('data-location="Medan Petisah"', false)
            ->assertSee('https://nominatim.openstreetmap.org/search?format=jsonv2', false);
    }

    public function test_map_attributes_escape_values_from_the_graph(): void
    {
        $this->fakeFuseki();
        $this->fuseki->listings[0]['locationLabel'] = 'Medan " onmouseover="alert(1)';

        $this->get(route('mobil.show', ['car' => 'Mobil_5']))
            ->assertOk()
            ->assertSee('data-location="Medan &quot; onmouseover=&quot;alert(1)"', false)
            ->assertDontSee('data-location="Medan " onmouseover="alert(1)"', false);
    }
}
