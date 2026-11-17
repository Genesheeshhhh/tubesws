<?php

namespace Tests\Feature;

use Tests\Support\InteractsWithFuseki;
use Tests\TestCase;

class ChatbotTest extends TestCase
{
    use InteractsWithFuseki;

    public function test_the_assistant_widget_is_rendered_on_public_pages(): void
    {
        $this->fakeFuseki();

        $this->get(route('beranda'))
            ->assertOk()
            ->assertSee('Asisten mobil')
            ->assertSee('data-chatbot', false);
    }

    public function test_a_budget_request_returns_only_cars_within_the_budget(): void
    {
        $this->fakeFuseki();

        $this->postJson(route('chatbot.reply'), ['message' => 'mobil di bawah 100 juta'])
            ->assertOk()
            ->assertJsonPath('filters.max_price', 100_000_000)
            ->assertJsonCount(1, 'cars')
            ->assertJsonPath('cars.0.id', 'Mobil_5');
    }

    public function test_a_brand_request_filters_by_brand(): void
    {
        $this->fakeFuseki();

        $this->postJson(route('chatbot.reply'), ['message' => 'cari toyota'])
            ->assertOk()
            ->assertJsonPath('filters.brand.0', 'Toyota')
            ->assertJsonCount(1, 'cars')
            ->assertJsonPath('cars.0.id', 'Mobil_6');
    }

    public function test_a_combined_request_applies_every_recognised_filter(): void
    {
        $this->fakeFuseki();

        $this->postJson(route('chatbot.reply'), ['message' => 'matic di bawah 200 juta bisa dicicil'])
            ->assertOk()
            ->assertJsonPath('filters.transmission.0', 'Matic')
            ->assertJsonPath('filters.with_financing', true)
            ->assertJsonCount(2, 'cars')
            ->assertJsonPath('cars.0.id', 'Mobil_6')
            ->assertJsonPath('cars.1.id', 'Mobil_7');
    }

    public function test_a_cheapest_request_sorts_the_results_by_price(): void
    {
        $this->fakeFuseki();

        $this->postJson(route('chatbot.reply'), ['message' => 'mobil termurah'])
            ->assertOk()
            ->assertJsonPath('filters.sort', 'harga_terendah')
            ->assertJsonPath('cars.0.id', 'Mobil_5')
            ->assertJsonPath('cars.1.id', 'Mobil_7');
    }

    public function test_a_misspelled_message_still_finds_matching_cars(): void
    {
        $this->fakeFuseki();

        $this->postJson(route('chatbot.reply'), ['message' => 'toyata'])
            ->assertOk()
            ->assertJsonCount(1, 'cars')
            ->assertJsonPath('cars.0.id', 'Mobil_6');
    }

    public function test_a_request_without_matches_explains_the_empty_result(): void
    {
        $this->fakeFuseki();

        $response = $this->postJson(route('chatbot.reply'), ['message' => 'mobil 50 juta'])
            ->assertOk()
            ->assertJsonCount(0, 'cars');

        $this->assertStringContainsString('Belum ada', $response->json('reply'));
    }

    public function test_a_greeting_returns_a_helpful_reply_without_listings(): void
    {
        $this->fakeFuseki();

        $response = $this->postJson(route('chatbot.reply'), ['message' => 'halo'])
            ->assertOk()
            ->assertJsonCount(0, 'cars');

        $this->assertStringContainsString('asisten', $response->json('reply'));
    }

    public function test_a_count_question_reports_catalogue_totals(): void
    {
        $this->fakeFuseki();

        $response = $this->postJson(route('chatbot.reply'), ['message' => 'ada berapa mobil'])
            ->assertOk()
            ->assertJsonCount(0, 'cars');

        $this->assertStringContainsString('3 iklan', $response->json('reply'));
    }

    public function test_an_empty_message_is_rejected(): void
    {
        $this->fakeFuseki();

        $this->postJson(route('chatbot.reply'), ['message' => ''])
            ->assertStatus(422)
            ->assertJsonValidationErrors('message');
    }

    public function test_a_fuseki_outage_returns_a_service_unavailable_response(): void
    {
        $this->fakeFuseki()->fail = true;

        $this->postJson(route('chatbot.reply'), ['message' => 'toyota'])
            ->assertStatus(503);
    }
}
