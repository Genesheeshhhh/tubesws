<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('car_inquiries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('car_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 120);
            $table->string('email');
            $table->string('phone', 30);
            $table->text('message');
            $table->timestamps();

            $table->index(['car_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('car_inquiries');
    }
};
