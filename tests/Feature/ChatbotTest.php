<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
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

        $response = $this->postJson(route('chatbot.reply'), ['message' => 'cari toyata']);

        $response->assertOk()
            ->assertJsonPath('filters.q', 'toyota')
            ->assertJsonCount(1, 'cars')
            ->assertJsonPath('cars.0.id', 'Mobil_6');

        $this->assertStringContainsString('Saya artikan "toyata" sebagai "toyota"', $response->json('reply'));
        $this->assertStringContainsString('q=toyota', (string) $response->json('url'));
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

    public function test_gemini_filters_are_normalised_by_the_catalogue_before_searching(): void
    {
        $this->fakeFuseki();
        $this->enableAi();
        $this->fakeGeminiDecision([
            'filters' => [
                'q' => 'Avanza',
                'brand' => ['Toyota'],
                'max_price' => 200_000_000,
                'with_financing' => true,
                'sort' => 'harga_terendah',
            ],
            'answer' => 'Saya carikan Avanza Toyota yang bisa dicicil.',
        ]);

        $response = $this->postJson(route('chatbot.reply'), [
            'message' => 'cari avanza toyota yang bisa dicicil',
        ]);

        $response->assertOk()
            ->assertJsonPath('filters.q', 'Avanza')
            ->assertJsonPath('filters.brand.0', 'Toyota')
            ->assertJsonPath('filters.max_price', 200_000_000)
            ->assertJsonPath('filters.with_financing', true)
            ->assertJsonPath('filters.sort', 'harga_terendah')
            ->assertJsonCount(1, 'cars')
            ->assertJsonPath('cars.0.id', 'Mobil_6');

        $this->assertStringContainsString('Saya carikan Avanza Toyota', $response->json('reply'));
        $this->assertStringContainsString('Saya menemukan 1 mobil', $response->json('reply'));
    }

    public function test_gemini_typos_are_corrected_with_the_same_catalogue_dictionary(): void
    {
        $this->fakeFuseki();
        $this->enableAi();
        $this->fakeGeminiDecision([
            'filters' => ['q' => 'toyata'],
            'answer' => 'Saya carikan toyata untuk Anda.',
        ]);

        $response = $this->postJson(route('chatbot.reply'), ['message' => 'cari toyata']);

        $response->assertOk()
            ->assertJsonPath('filters.q', 'toyota')
            ->assertJsonCount(1, 'cars')
            ->assertJsonPath('cars.0.id', 'Mobil_6');

        $this->assertStringContainsString('Saya artikan "toyata" sebagai "toyota"', $response->json('reply'));
    }

    public function test_invalid_ai_values_cannot_invent_catalogue_results(): void
    {
        $this->fakeFuseki();
        $this->enableAi();
        $this->fakeGeminiDecision([
            'filters' => ['brand' => ['Merek Palsu']],
            'answer' => 'Tentu, ini merek terkenal.',
        ]);

        $response = $this->postJson(route('chatbot.reply'), [
            'message' => 'cari merek palsu',
        ]);

        $response->assertOk()
            ->assertJsonCount(0, 'cars')
            ->assertJsonPath('filters.brand.0', 'Merek Palsu');

        $this->assertStringContainsString('Belum ada mobil', $response->json('reply'));
        $this->assertStringNotContainsString('terkenal', $response->json('reply'));
    }

    public function test_ai_falls_back_to_rules_when_gemini_is_unavailable(): void
    {
        $this->fakeFuseki();
        $this->enableAi();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response('service unavailable', 503),
        ]);

        $this->postJson(route('chatbot.reply'), ['message' => 'mobil di bawah 100 juta'])
            ->assertOk()
            ->assertJsonPath('filters.max_price', 100_000_000)
            ->assertJsonCount(1, 'cars')
            ->assertJsonPath('cars.0.id', 'Mobil_5');
    }

    public function test_the_rules_provider_never_calls_gemini(): void
    {
        $this->fakeFuseki();

        config()->set('gemini.provider', 'rules');
        config()->set('gemini.api_key', 'test-key-not-real');

        Http::fake();

        $this->postJson(route('chatbot.reply'), ['message' => 'mobil di bawah 100 juta'])
            ->assertOk()
            ->assertJsonPath('filters.max_price', 100_000_000);

        Http::assertNothingSent();
    }

    public function test_the_current_page_and_car_are_included_in_the_ai_context(): void
    {
        $this->fakeFuseki();
        $this->enableAi();
        $this->fakeGeminiDecision([
            'filters' => ['q' => 'Avanza'],
            'answer' => 'Saya carikan yang mirip dengan mobil yang sedang Anda lihat.',
        ]);

        $this->postJson(route('chatbot.reply'), [
            'message' => 'yang ini ada yang mirip?',
            'context' => [
                'page' => '/mobil/Mobil_6',
                'car' => 'Mobil_6',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('filters.q', 'Avanza')
            ->assertJsonPath('cars.0.id', 'Mobil_6');

        Http::assertSent(fn ($request): bool => str_contains($request->body(), 'Toyota Avanza')
            && str_contains($request->body(), 'Halaman yang sedang dibuka')
            && str_contains($request->body(), 'Mobil_6'));
    }

    public function test_count_questions_still_use_the_offline_statistics_answer(): void
    {
        $this->fakeFuseki();
        $this->enableAi();
        Http::fake();

        $response = $this->postJson(route('chatbot.reply'), ['message' => 'ada berapa mobil?'])
            ->assertOk()
            ->assertJsonCount(0, 'cars');

        $this->assertStringContainsString('3 iklan', $response->json('reply'));
        Http::assertNothingSent();
    }

    public function test_the_widget_advertises_ai_only_when_a_key_is_configured(): void
    {
        $this->fakeFuseki();

        $this->get(route('beranda'))
            ->assertOk()
            ->assertSee('Offline', false);

        config()->set('gemini.provider', 'auto');
        config()->set('gemini.api_key', 'test-key-not-real');

        $this->get(route('beranda'))
            ->assertOk()
            ->assertSee('dibatasi katalog lokal', false);
    }

    public function test_a_regex_pattern_is_forwarded_to_the_catalogue(): void
    {
        $this->fakeFuseki();

        $response = $this->postJson(route('chatbot.reply'), [
            'message' => 'cari dengan regex Toyota|Brio',
        ]);

        $response->assertOk()
            ->assertJsonPath('filters.q', 'Toyota|Brio')
            ->assertJsonPath('filters.regex', true)
            ->assertJsonCount(2, 'cars')
            ->assertJsonPath('cars.0.id', 'Mobil_6')
            ->assertJsonPath('cars.1.id', 'Mobil_7');

        $this->assertStringContainsString('regex=1', (string) $response->json('url'));
        $this->assertStringContainsString('pola regex', $response->json('reply'));
    }

    public function test_a_broken_regex_pattern_is_reported_instead_of_failing_silently(): void
    {
        $this->fakeFuseki();

        $response = $this->postJson(route('chatbot.reply'), ['message' => 'regex [']);

        $response->assertOk()
            ->assertJsonPath('filters.regex_error', true)
            ->assertJsonCount(0, 'cars');

        $this->assertStringContainsString('Pola regex tidak valid', $response->json('reply'));
    }

    public function test_gemini_can_request_a_regex_search_too(): void
    {
        $this->fakeFuseki();
        $this->enableAi();
        $this->fakeGeminiDecision([
            'filters' => ['q' => 'Toyota|Brio', 'regex' => true],
            'answer' => 'Saya pakai pola Toyota atau Brio.',
        ]);

        $this->postJson(route('chatbot.reply'), ['message' => 'pakai pola Toyota atau Brio'])
            ->assertOk()
            ->assertJsonPath('filters.q', 'Toyota|Brio')
            ->assertJsonPath('filters.regex', true)
            ->assertJsonCount(2, 'cars')
            ->assertJsonPath('cars.0.id', 'Mobil_6');
    }

    public function test_a_fuseki_outage_returns_a_service_unavailable_response(): void
    {
        $this->fakeFuseki()->fail = true;

        $this->postJson(route('chatbot.reply'), ['message' => 'toyota'])
            ->assertStatus(503);
    }

    private function enableAi(): void
    {
        config()->set('gemini.provider', 'auto');
        config()->set('gemini.api_key', 'test-key-not-real');
    }

    /**
     * @param  array<string, mixed>  $decision
     */
    private function fakeGeminiDecision(array $decision): void
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [[
                    'content' => ['parts' => [[
                        'text' => json_encode($decision, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    ]]],
                ]],
            ], 200, ['Content-Type' => 'application/json']),
        ]);
    }
}
