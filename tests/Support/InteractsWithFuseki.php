<?php

namespace Tests\Support;

use App\Services\Fuseki\FusekiClient;
use Illuminate\Support\Facades\Cache;

trait InteractsWithFuseki
{
    protected FakeFusekiClient $fuseki;

    protected function fakeFuseki(): FakeFusekiClient
    {
        Cache::flush();

        $this->fuseki = new FakeFusekiClient();

        $this->app->instance(FusekiClient::class, $this->fuseki);

        return $this->fuseki;
    }
}
