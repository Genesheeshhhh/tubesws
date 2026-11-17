<?php

namespace Database\Factories;

use App\Enums\CarBrand;
use App\Enums\CarCondition;
use App\Enums\CarStatus;
use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Models\Car;
use App\Models\User;
use App\Services\Semantic\CarGraphBuilder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Car>
 */
class CarFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $brand = fake()->randomElement(CarBrand::cases());
        $model = Str::headline(fake()->word());

        return [
            'seller_id' => User::factory(),
            'slug' => Str::slug($brand->value.' '.$model.' '.$this->faker->unique()->numberBetween(1, 999_999)),
            'brand' => $brand->value,
            'model' => $model,
            'variant' => fake()->randomElement([null, '1.3 G', '2.0 Sport', 'V AT']),
            'year' => fake()->numberBetween(2005, (int) date('Y')),
            'price' => fake()->numberBetween(50_000_000, 800_000_000),
            'mileage_min' => fake()->numberBetween(1_000, 60_000) * 1000,
            'mileage_max' => fake()->numberBetween(60_001, 300_000) * 1000,

            // Absent from the imported catalogue, so these stay empty unless a
            // test explicitly provides the vehicle specification.
            'transmission' => null,
            'drivetrain' => null,
            'fuel' => null,

            'condition' => CarCondition::Used->value,
            'color' => fake()->randomElement(['Hitam', 'Putih', 'Silver', 'Merah', 'Biru']),
            'body_type' => fake()->randomElement(['Sedan', 'MPV', 'SUV', 'Hatchback', 'Pickup']),
            'city' => fake()->randomElement(['Medan', 'Bandung', 'Surabaya', 'Semarang', 'Makassar']),
            'location' => Str::headline(fake()->word()).' '.fake()->numberBetween(1, 99),
            'description' => fake()->paragraph(),
            'status' => CarStatus::Available->value,
            'posted_at' => fake()->dateTimeBetween('-2 years', 'now'),
            'source' => 'manual',
        ];
    }

    /**
     * A saved listing is published to the graph the same way the seller flow
     * publishes it, so tests exercise the graph the site actually serves rather
     * than a model row nothing else has written about.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Car $car): void {
            app(CarGraphBuilder::class)->publish($car->load(['images', 'seller']));
        });
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => CarStatus::Draft->value]);
    }

    public function sold(): static
    {
        return $this->state(fn (): array => [
            'status' => CarStatus::Sold->value,
            'sold_at' => now(),
        ]);
    }

    public function verified(): static
    {
        return $this->state(fn (): array => ['is_verified' => true]);
    }

    /**
     * Every listing is expected to carry at least one image, so a factory-made
     * car gets the placeholder artwork the photo pipeline would create.
     */
    public function withPlaceholderImage(): static
    {
        return $this->afterCreating(function (Car $car): void {
            $car->images()->create([
                'disk' => 'public',
                'path' => 'cars/'.$car->slug.'/01.svg',
                'alt_text' => $car->display_name,
                'is_placeholder' => true,
            ]);
        });
    }

    public function automatic(): static
    {
        return $this->state(fn (): array => ['transmission' => Transmission::Automatic->value]);
    }

    public function petrol(): static
    {
        return $this->state(fn (): array => ['fuel' => FuelType::Bensin->value]);
    }

    public function financed(): static
    {
        return $this->state(fn (): array => [
            'down_payment' => 20_000_000,
            'installment_monthly' => 3_500_000,
            'installment_term' => 36,
        ]);
    }
}
