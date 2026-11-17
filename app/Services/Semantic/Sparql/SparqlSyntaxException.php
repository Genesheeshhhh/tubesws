<?php

namespace App\Services\Semantic\Sparql;

use RuntimeException;

/**
 * Thrown when a query uses a feature outside the supported subset, so the
 * endpoint can say which construct is missing instead of answering wrongly.
 */
class SparqlSyntaxException extends RuntimeException {}
