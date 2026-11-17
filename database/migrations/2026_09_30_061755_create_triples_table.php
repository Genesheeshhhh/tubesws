<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('triples', function (Blueprint $table) {
            $table->id();
            $table->string('subject', 500);
            $table->string('predicate', 300);
            $table->string('object', 500);
            $table->boolean('object_is_iri')->default(false);
            $table->string('datatype', 120)->nullable();
            $table->string('graph', 60)->default('default');

            // Pattern matching resolves a bound subject before a bound
            // predicate, then falls back to a predicate-only scan, so each
            // column is indexed in that order of selectivity.
            $table->index(['subject', 'predicate']);
            $table->index(['predicate', 'object']);
            $table->index('graph');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('triples');
    }
};
