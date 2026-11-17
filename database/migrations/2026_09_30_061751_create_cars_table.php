<?php

use App\Enums\CarCondition;
use App\Enums\CarStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('seller_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('slug')->unique();

            $table->string('brand', 60);
            $table->string('model', 80);
            $table->string('variant', 80)->nullable();
            $table->smallInteger('year');
            $table->unsignedBigInteger('price');
            $table->unsignedInteger('mileage_min')->nullable();
            $table->unsignedInteger('mileage_max')->nullable();

            // Transmission and fuel are absent from the imported catalogue, so
            // they stay nullable instead of being guessed at import time.
            $table->string('transmission', 20)->nullable();
            $table->string('fuel', 20)->nullable();
            $table->string('condition', 20)->default(CarCondition::Used->value);
            $table->string('color', 40)->nullable();
            $table->string('body_type', 60)->nullable();

            $table->string('city', 60)->default('Medan');
            $table->string('location', 120);
            $table->text('description')->nullable();

            $table->string('status', 20)->default(CarStatus::Available->value);
            $table->timestamp('sold_at')->nullable();
            $table->boolean('is_verified')->default(false);
            $table->boolean('has_warranty')->default(false);
            $table->boolean('booking_is_safe')->default(false);
            $table->boolean('free_insurance')->default(false);
            $table->boolean('home_test_drive')->default(false);
            $table->boolean('free_maintenance')->default(false);

            $table->unsignedBigInteger('down_payment')->nullable();
            $table->unsignedBigInteger('installment_monthly')->nullable();
            $table->unsignedSmallInteger('installment_term')->nullable();
            $table->unsignedBigInteger('discount_amount')->nullable();

            $table->timestamp('posted_at');
            $table->unsignedInteger('view_count')->default(0);

            $table->string('source', 40)->default('manual');
            $table->string('source_reference', 120)->nullable();

            $table->timestamps();

            // Facet and filter queries: brand/location/price browsing, year and
            // price range narrowing, availability filtering, default ordering.
            $table->index(['brand', 'model']);
            $table->index(['city', 'location']);
            $table->index(['status', 'posted_at']);
            $table->index(['price', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cars');
    }
};
