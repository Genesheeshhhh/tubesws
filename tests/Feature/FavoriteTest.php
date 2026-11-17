<?php

namespace Tests\Feature;

use Tests\Support\InteractsWithFuseki;
use Tests\TestCase;

class FavoriteTest extends TestCase
{
    use InteractsWithFuseki;

    public function test_a_guest_can_save_a_vehicle_to_the_session(): void
    {
        $this->fakeFuseki();

        $this->from(route('mobil.show', ['car' => 'Mobil_5']))
            ->post(route('favorit.store', ['car' => 'Mobil_5']))
            ->assertRedirect(route('mobil.show', ['car' => 'Mobil_5']))
            ->assertSessionHas('favorit', ['Mobil_5']);

        $this->get(route('favorit.index'))
            ->assertOk()
            ->assertSee('BMW 318i (1996)')
            ->assertSee('1 mobil tersimpan');
    }

    public function test_saving_the_same_vehicle_twice_keeps_one_entry(): void
    {
        $this->fakeFuseki();

        $this->post(route('favorit.store', ['car' => 'Mobil_5']));
        $this->post(route('favorit.store', ['car' => 'Mobil_5']))
            ->assertSessionHas('favorit', ['Mobil_5']);
    }

    public function test_a_saved_vehicle_can_be_removed(): void
    {
        $this->fakeFuseki();

        $this->withSession(['favorit' => ['Mobil_5', 'Mobil_6']])
            ->delete(route('favorit.destroy', ['car' => 'Mobil_5']))
            ->assertSessionHas('favorit', ['Mobil_6']);

        $this->flushSession();

        $this->withSession(['favorit' => ['Mobil_6']])
            ->get(route('favorit.index'))
            ->assertOk()
            ->assertSee('Toyota Avanza (2020)')
            ->assertDontSee('BMW 318i (1996)');
    }

    public function test_an_unknown_vehicle_cannot_be_saved(): void
    {
        $this->fakeFuseki();

        $this->post(route('favorit.store', ['car' => 'Mobil_999']))->assertNotFound();
    }
}
