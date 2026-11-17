<?php

namespace App\Console\Commands;

use App\Enums\CarCondition;
use App\Enums\CarStatus;
use App\Enums\Drivetrain;
use App\Models\Car;
use App\Models\User;
use App\Services\Catalog\ListingPhotoStore;
use App\Services\Semantic\CarGraphBuilder;
use App\Services\Semantic\SiteGraphBuilder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Seeds a small, hand-written showcase of listings next to whatever was imported.
 *
 * The imported catalogue has no seller account a person can log into and no
 * listing in a draft state, so both flows would otherwise be impossible to try
 * out locally. Everything here is keyed by slug and skips listings that already
 * exist, which keeps the command safe to re-run next to imported data.
 */
#[Signature('app:seed-showcase-listings {--email=demo@motormurah.test : Account to own the showcase listings}')]
#[Description('Seed a demo seller with showcase listings covering every listing state')]
class SeedShowcaseListingsCommand extends Command
{
    /**
     * @var list<array<string, mixed>>
     */
    private const LISTINGS = [
        [
            'slug' => 'showcase-toyota-avanza-1-3-g',
            'brand' => 'Toyota',
            'model' => 'Avanza',
            'variant' => '1.3 G CVT',
            'drivetrain' => Drivetrain::RearWheelDrive,
            'year' => 2019,
            'price' => 168_000_000,
            'mileage_min' => 35_000,
            'mileage_max' => 42_000,
            'condition' => CarCondition::Used,
            'color' => 'Putih',
            'body_type' => 'MPV',
            'city' => 'Bandung',
            'location' => 'Jl. Soekarno Hatta 812',
            'description' => 'Avanza 1.3 G CVT tahun 2019, servis rutin di dealer resmi, ban baru, dan Ready to sell. Cocok untuk keluarga.',
            'status' => CarStatus::Available,
            'is_verified' => true,
            'has_warranty' => true,
            'home_test_drive' => true,
            'down_payment' => 30_000_000,
            'installment_monthly' => 3_890_000,
            'installment_term' => 36,
        ],
        [
            'slug' => 'showcase-honda-brio-e-cvt-sat',
            'brand' => 'Honda',
            'model' => 'Brio',
            'variant' => 'E CVT SAT',
            'drivetrain' => Drivetrain::FrontWheelDrive,
            'year' => 2021,
            'price' => 149_000_000,
            'mileage_min' => 18_000,
            'mileage_max' => 24_000,
            'condition' => CarCondition::Used,
            'color' => 'Biru',
            'body_type' => 'Hatchback',
            'city' => 'Jakarta Selatan',
            'location' => 'Jl. Tebet Barat Dalam No. 45',
            'description' => 'Brio E CVT dengan fitur lengkap, mobil kedua, Kilometran rendah, dan masih dalam masa garansi pabrikan.',
            'status' => CarStatus::Available,
            'is_verified' => true,
            'booking_is_safe' => true,
            'free_insurance' => true,
            'free_maintenance' => true,
        ],
        [
            'slug' => 'showcase-mitsubishi-pajero-sport-4x4',
            'brand' => 'Mitsubishi',
            'model' => 'Pajero Sport',
            'variant' => '4x4 Dakar',
            'drivetrain' => Drivetrain::FourWheelDrive,
            'year' => 2017,
            'price' => 315_000_000,
            'mileage_min' => 78_000,
            'mileage_max' => 85_000,
            'condition' => CarCondition::Used,
            'color' => 'Hitam',
            'body_type' => 'SUV',
            'city' => 'Surabaya',
            'location' => 'Jl. A. Yani 210',
            'description' => 'Pajero Sport Dakar 4x4, riwayat servis lengkap dan ban baru. Cocok untuk perjalanan jauh serta off-road ringan.',
            'status' => CarStatus::Sold,
            'sold_at' => '2026-02-10 10:00:00',
            'has_warranty' => true,
            'discount_amount' => 5_000_000,
        ],
        [
            'slug' => 'showcase-suzuki-ignis-rs-2022',
            'brand' => 'Suzuki',
            'model' => 'Ignis',
            'variant' => 'RS',
            'drivetrain' => Drivetrain::FrontWheelDrive,
            'year' => 2022,
            'price' => 132_000_000,
            'mileage_min' => 9_000,
            'mileage_max' => 12_000,
            'condition' => CarCondition::Used,
            'color' => 'Silver',
            'body_type' => 'Hatchback',
            'city' => 'Semarang',
            'location' => 'Jl. Majapahit 77',
            'description' => 'Ignis RS 2022, unit masih garansi, siap pakai. Foto placeholder akan diganti begitu foto asli diunggah.',
            'status' => CarStatus::Draft,
        ],
    ];

    public function handle(ListingPhotoStore $photos, CarGraphBuilder $graph, SiteGraphBuilder $site): int
    {
        $email = (string) $this->option('email');

        $seller = User::query()->firstOrCreate(
            ['email' => $email],
            [
                'name' => 'Motor Murah Bandung',
                'password' => 'password',
                'phone' => '08111222333',
                'city' => 'Bandung',
            ],
        );

        $created = 0;

        foreach (self::LISTINGS as $attributes) {
            if (Car::query()->where('slug', $attributes['slug'])->exists()) {
                continue;
            }

            $car = new Car;
            $car->fill($this->attributesFor($attributes));
            $car->seller()->associate($seller);
            $car->save();

            $photos->ensureArtwork($car);
            $graph->publish($car->refresh()->load(['images', 'seller']));

            $created++;
        }

        $site->syncAll();

        $this->components->twoColumnDetail('Seller', $seller->email);
        $this->components->twoColumnDetail('Listings added', $created);
        $this->components->twoColumnDetail('Listings skipped', count(self::LISTINGS) - $created);

        if ($created > 0) {
            $this->components->info('Showcase listings are ready. Log in to manage them.');
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function attributesFor(array $attributes): array
    {
        $status = $attributes['status'];
        $condition = $attributes['condition'];

        return [
            'slug' => $attributes['slug'],
            'brand' => $attributes['brand'],
            'model' => $attributes['model'],
            'variant' => $attributes['variant'],
            'year' => $attributes['year'],
            'price' => $attributes['price'],
            'mileage_min' => $attributes['mileage_min'],
            'mileage_max' => $attributes['mileage_max'],
            'transmission' => null,
            'drivetrain' => $attributes['drivetrain']->value,
            'fuel' => null,
            'condition' => $condition->value,
            'color' => $attributes['color'],
            'body_type' => $attributes['body_type'],
            'city' => $attributes['city'],
            'location' => $attributes['location'],
            'description' => $attributes['description'],
            'status' => $status->value,
            'sold_at' => $attributes['sold_at'] ?? null,
            'posted_at' => $attributes['posted_at'] ?? now()->subDays(7),
            'is_verified' => $attributes['is_verified'] ?? false,
            'has_warranty' => $attributes['has_warranty'] ?? false,
            'booking_is_safe' => $attributes['booking_is_safe'] ?? false,
            'free_insurance' => $attributes['free_insurance'] ?? false,
            'home_test_drive' => $attributes['home_test_drive'] ?? false,
            'free_maintenance' => $attributes['free_maintenance'] ?? false,
            'down_payment' => $attributes['down_payment'] ?? null,
            'installment_monthly' => $attributes['installment_monthly'] ?? null,
            'installment_term' => $attributes['installment_term'] ?? null,
            'discount_amount' => $attributes['discount_amount'] ?? null,
            'source' => 'showcase',
        ];
    }
}
