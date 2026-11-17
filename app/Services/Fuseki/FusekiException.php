<?php

namespace App\Services\Fuseki;

use RuntimeException;

class FusekiException extends RuntimeException
{
    public static function unreachable(string $endpoint, string $reason): self
    {
        return new self("Apache Jena Fuseki tidak dapat dihubungi di {$endpoint}: {$reason}");
    }

    public static function queryFailed(string $endpoint, string $reason): self
    {
        return new self("Kueri SPARQL gagal di {$endpoint}: {$reason}");
    }
}
