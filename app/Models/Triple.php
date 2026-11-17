<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * One RDF statement in the site's knowledge graph.
 */
#[Table('triples')]
#[Fillable([
    'subject',
    'predicate',
    'object',
    'object_is_iri',
    'datatype',
    'graph',
])]
class Triple extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'object_is_iri' => 'boolean',
        ];
    }
}
