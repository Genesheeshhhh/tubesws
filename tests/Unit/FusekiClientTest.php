<?php

namespace Tests\Unit;

use App\Services\Fuseki\FusekiClient;
use App\Services\Fuseki\FusekiException;
use App\Services\Fuseki\SparqlResult;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FusekiClientTest extends TestCase
{
    public function test_select_flattens_sparql_json_bindings(): void
    {
        Http::fake([
            '*/TUBESWS/query' => Http::response([
                'head' => ['vars' => ['n']],
                'results' => ['bindings' => [
                    ['n' => ['type' => 'literal', 'value' => '914']],
                ]],
            ]),
        ]);

        $rows = $this->app->make(FusekiClient::class)->select('SELECT (COUNT(*) AS ?n) WHERE { ?s ?p ?o }');

        $this->assertSame([['n' => '914']], $rows);
    }

    public function test_ask_returns_the_boolean_answer(): void
    {
        Http::fake([
            '*/TUBESWS/query' => Http::response(['boolean' => true]),
        ]);

        $this->assertTrue($this->app->make(FusekiClient::class)->ask('ASK { ?s ?p ?o }'));
    }

    public function test_a_failed_query_throws_a_fuseki_exception(): void
    {
        Http::fake([
            '*/TUBESWS/query' => Http::response('Encountered " <bad>"', 400),
        ]);

        $this->expectException(FusekiException::class);
        $this->expectExceptionMessage('Kueri SPARQL gagal');

        $this->app->make(FusekiClient::class)->select('SELECT * WHERE { broken');
    }

    public function test_the_ping_endpoint_reports_availability(): void
    {
        Http::fake([
            '*/$/ping' => Http::response('ok', 200),
        ]);

        $this->assertTrue($this->app->make(FusekiClient::class)->isAvailable());
    }

    public function test_result_kind_is_detected_after_prefix_declarations(): void
    {
        $this->assertSame('select', SparqlResult::kindOf("PREFIX mb: <http://example.test#>\nSELECT * WHERE { ?s ?p ?o }"));
        $this->assertSame('ask', SparqlResult::kindOf('ASK { ?s ?p ?o }'));
        $this->assertSame('construct', SparqlResult::kindOf("PREFIX mb: <http://example.test#>\nCONSTRUCT { ?s ?p ?o } WHERE { ?s ?p ?o }"));
    }
}
