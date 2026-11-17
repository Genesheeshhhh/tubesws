<?php

namespace App\Services\Import;

use App\Enums\CarBrand;
use App\Enums\CarCondition;
use App\Enums\CarStatus;
use App\Enums\Drivetrain;
use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Models\Car;
use App\Models\CarImage;
use App\Models\User;
use App\Services\Catalog\PlaceholderImage;
use App\Services\Semantic\CarGraphBuilder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ListingImporter
{
    public function __construct(
        private readonly ExcelReader $reader,
        private readonly ListingNormalizer $normalizer,
        private readonly PlaceholderImage $placeholders,
        private readonly CarGraphBuilder $graph,
    ) {}

    /**
     * @return array{total: int, imported: int, skipped: int, photos: int, placeholders: int, failed: int}
     */
    public function import(string $path, User $seller, bool $withImages = true, ?string $sheet = null): array
    {
        $report = [
            'total' => 0,
            'imported' => 0,
            'skipped' => 0,
            'photos' => 0,
            'placeholders' => 0,
            'failed' => 0,
        ];

        $now = CarbonImmutable::now();
        $existing = Car::query()
            ->where('source', 'olx-import')
            ->pluck('source_reference')
            ->flip()
            ->all();

        foreach ($this->reader->read($path, $sheet) as $record) {
            $report['total']++;

            $reference = $record['order_baru'] ?? '';

            if ($reference !== '' && isset($existing[$reference])) {
                $report['skipped']++;

                continue;
            }

            $attributes = $this->mapRecord($record, $seller, $now);

            if ($attributes === null) {
                $report['failed']++;

                continue;
            }

            $car = Car::create($attributes);

            $report['imported']++;

            if ($withImages) {
                $outcome = $this->storePhotos($car, $record['image'] ?? null);

                $report['photos'] += $outcome['photos'];
                $report['placeholders'] += $outcome['placeholders'];
            } else {
                $this->storePlaceholder($car);
            }

            $this->graph->publish($car);
        }

        return $report;
    }

    /**
     * @param  array<string, string>  $record
     * @return array<string, mixed>|null
     */
    private function mapRecord(array $record, User $seller, CarbonImmutable $now): ?array
    {
        $price = $this->normalizer->amount($record['Harga'] ?? null);
        $catalogueName = trim($record['Mobil'] ?? '');
        $year = $this->normalizer->year($record['Tahun Pembuatan'] ?? null);
        $location = trim($record['Lokasi'] ?? '');

        if ($price === null || $catalogueName === '' || $year === null || $location === '') {
            return null;
        }

        [$brand, $model] = CarBrand::split($catalogueName);
        [$city, $district] = $this->normalizer->location($location);
        [$mileageMin, $mileageMax] = $this->normalizer->mileageRange($record['Kilometer'] ?? null);
        $installment = $this->normalizer->amount($record['Cicilan'] ?? null);

        $transmission = $this->normalizeTransmission($record['Transmisi'] ?? null);
        $fuel = $this->normalizeFuel($record['Bahan Bakar'] ?? null);
        $drivetrain = $this->normalizeDrivetrain($record['Penggerak'] ?? null);

        return [
            'seller_id' => $seller->id,
            'slug' => $this->uniqueSlug($brand, $model, $year),
            'brand' => $brand,
            'model' => $model !== '' ? $model : '-',
            'year' => $year,
            'price' => $price,
            'mileage_min' => $mileageMin,
            'mileage_max' => $mileageMax,
            'transmission' => $transmission,
            'drivetrain' => $drivetrain,
            'fuel' => $fuel,
            'condition' => CarCondition::Used->value,
            'city' => $city !== '' ? $city : $seller->city,
            'location' => $district !== '' ? trim($city.' '.$district) : $city,
            'description' => $this->describe($brand, $model, $year, $mileageMin, $mileageMax, $city, $district, $price),
            'status' => CarStatus::Available->value,
            'is_verified' => $this->normalizer->mentions($record['status'] ?? null, 'Terverifikasi'),
            'has_warranty' => $this->normalizer->mentions($record['status'] ?? null, 'Bergaransi'),
            'booking_is_safe' => $this->normalizer->mentions($record['data9'] ?? null, 'BOOKING AMAN'),
            'free_insurance' => $this->normalizer->mentions($record['garansi'] ?? null, 'Gratis Asuransi'),
            'home_test_drive' => $this->normalizer->mentions($record['test drive'] ?? null, 'Test Drive'),
            'free_maintenance' => $this->normalizer->mentions($record['jasa perawatan'] ?? null, 'Gratis'),
            'down_payment' => $this->normalizer->amount($record['DP'] ?? null),
            'installment_monthly' => $installment,
            'installment_term' => $this->normalizer->installmentTerm($record['Jangka Waktu'] ?? null),
            'discount_amount' => $this->normalizer->discount($record['diskon'] ?? null),
            'posted_at' => $this->normalizer->postedAt($record['Tanggal Posting'] ?? null, $now) ?? $now,
            'source' => 'olx-import',
            'source_reference' => $record['order_baru'] ?? null,
        ];
    }

    /**
     * @return array{photos: int, placeholders: int}
     */
    private function storePhotos(Car $car, ?string $sourceUrl): array
    {
        $sourceUrl = trim((string) $sourceUrl);

        if (! CarImage::isRemoteUrl($sourceUrl)) {
            $this->storePlaceholder($car);

            return ['photos' => 0, 'placeholders' => 1];
        }

        CarImage::create([
            'car_id' => $car->id,
            'disk' => CarImage::REMOTE_DISK,
            'path' => $sourceUrl,
            'alt_text' => $car->brand.' '.$car->model.' tahun '.$car->year,
            'is_placeholder' => false,
        ]);

        return ['photos' => 1, 'placeholders' => 0];
    }

    private function normalizeTransmission(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $text = Str::lower(trim($value));

        if (Str::contains($text, ['matic', 'otomatis', 'automatic', 'cvt', 'dct', 'at'])) {
            return Transmission::Automatic->value;
        }

        if (Str::contains($text, ['manual', 'mt'])) {
            return Transmission::Manual->value;
        }

        foreach (Transmission::cases() as $case) {
            if (Str::contains($text, Str::lower($case->value))) {
                return $case->value;
            }
        }

        return null;
    }

    private function normalizeFuel(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $text = Str::lower(trim($value));

        if (Str::contains($text, ['listrik', 'electric', 'bev'])) {
            return FuelType::Listrik->value;
        }

        if (Str::contains($text, ['hybrid', 'hibrid'])) {
            return FuelType::Hybrid->value;
        }

        if (Str::contains($text, ['diesel', 'solar'])) {
            return FuelType::Diesel->value;
        }

        if (Str::contains($text, ['bensin', 'petrol', 'gasoline'])) {
            return FuelType::Bensin->value;
        }

        foreach (FuelType::cases() as $case) {
            if (Str::contains($text, Str::lower($case->value))) {
                return $case->value;
            }
        }

        return null;
    }

    private function normalizeDrivetrain(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $text = Str::upper(trim($value));

        if (in_array($text, ['AWD', '4X4', '4WD', 'ALL WHEEL DRIVE', 'ALL-WHEEL DRIVE'], true)) {
            return Drivetrain::AllWheelDrive->value;
        }

        if (in_array($text, ['FWD', '2WD', 'FRONT WHEEL DRIVE', 'FRONT-WHEEL DRIVE'], true) || Str::contains($text, 'FWD')) {
            return Drivetrain::FrontWheelDrive->value;
        }

        if (in_array($text, ['RWD', 'REAR WHEEL DRIVE', 'REAR-WHEEL DRIVE'], true) || Str::contains($text, 'RWD')) {
            return Drivetrain::RearWheelDrive->value;
        }

        if (in_array($text, ['4WD'], true)) {
            return Drivetrain::FourWheelDrive->value;
        }

        foreach (Drivetrain::cases() as $case) {
            if (Str::upper($case->value) === $text || Str::contains($text, Str::upper($case->value))) {
                return $case->value;
            }
        }

        return null;
    }

    private function storePlaceholder(Car $car): void
    {
        $position = $car->images()->count() + 1;

        CarImage::create([
            'car_id' => $car->id,
            'disk' => 'public',
            'path' => $this->placeholders->path($car->slug, $position),
            'alt_text' => $car->brand.' '.$car->model.' tahun '.$car->year,
            'is_placeholder' => true,
        ]);

        Storage::disk('public')->put(
            $this->placeholders->path($car->slug, $position),
            $this->placeholders->svg($car->brand, $car->model, $car->slug),
        );
    }

    private function uniqueSlug(string $brand, string $model, int $year): string
    {
        $base = Str::slug($brand.' '.$model.' '.$year) ?: 'mobil';
        $slug = $base;
        $suffix = 1;

        while (Car::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    private function describe(
        string $brand,
        string $model,
        int $year,
        ?int $mileageMin,
        ?int $mileageMax,
        string $city,
        string $district,
        int $price,
    ): string {
        $name = trim($brand.' '.$model);

        $sentences = [
            $name.' tahun '.$year.' Condition '.CarCondition::Used->label().'.',
        ];

        if ($mileageMin !== null && $mileageMax !== null) {
            $sentences[] = 'Kilometer Tempuh '.number_format($mileageMin, 0, ',', '.')
                .($mileageMin === $mileageMax ? '' : ' - '.number_format($mileageMax, 0, ',', '.')).' km.';
        }

        $sentences[] = 'Berada di '.trim($city.' '.$district).'.';
        $sentences[] = 'Harga ditawarkan Rp '.number_format($price, 0, ',', '.').'.';

        return implode(' ', $sentences);
    }
}
