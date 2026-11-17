<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('car_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('alt_text')->nullable();
            $table->boolean('is_placeholder')->default(false);
            $table->timestamps();

            $table->index(['car_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_images');
    }
};
