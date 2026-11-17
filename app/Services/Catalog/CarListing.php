<?php

namespace App\Services\Catalog;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * A single listing read from the TUBESWS graph.
 *
 * The XML ontology uses Indonesian property names and keeps some numbers as
 * formatted text ("Rp 85.000.000", "175.000-180.000 km"). This value object
 * keeps both the original text for display and parsed numbers for filtering so
 * the UI never has to re-invent the parsing rules.
 */
final readonly class CarListing
{
    public function __construct(
        public string $id,
        public string $uri,
        public string $label,
        public int $order,
        public string $brand,
        public string $model,
        public string $bodyType,
        public string $transmission,
        public string $drivetrain,
        public string $fuel,
        public string $location,
        public string $priceText,
        public int $price,
        public string $yearText,
        public int $year,
        public string $mileageText,
        public ?int $mileageMin,
        public ?int $mileageMax,
        public ?string $postedAt,
        public ?string $imageUrl,
        public ?string $installmentText,
        public ?int $installmentMonthly,
        public ?string $termText,
        public ?int $termMonths,
        public ?string $downPaymentText,
        public ?int $downPayment,
        public ?string $discountText,
        public ?int $discountAmount,
        public ?string $warrantyText,
        public ?string $testDriveText,
        public ?string $maintenanceText,
        public ?string $bookingText,
        public ?string $sellerStatusText,
    ) {}

    public function priceLabel(): string
    {
        return 'Rp '.number_format($this->price, 0, ',', '.');
    }

    public function priceInMillions(): string
    {
        return 'Rp '.number_format($this->price / 1_000_000, 1, ',', '.').' jt';
    }

    public function yearLabel(): string
    {
        return $this->yearText !== '' ? $this->yearText : '-';
    }

    public function mileageMidpoint(): ?int
    {
        if ($this->mileageMin === null && $this->mileageMax === null) {
            return null;
        }

        return (int) round((($this->mileageMin ?? 0) + ($this->mileageMax ?? 0)) / 2);
    }

    public function mileageLabel(): string
    {
        if ($this->mileageMin !== null && $this->mileageMax !== null && $this->mileageMin !== $this->mileageMax) {
            return number_format($this->mileageMin, 0, ',', '.').'–'.number_format($this->mileageMax, 0, ',', '.').' km';
        }

        $value = $this->mileageMax ?? $this->mileageMin;

        return $value !== null
            ? number_format($value, 0, ',', '.').' km'
            : ($this->mileageText !== '' ? $this->mileageText : '-');
    }

    public function postedLabel(): string
    {
        return $this->postedDate()?->translatedFormat('d M Y') ?? '-';
    }

    public function postedDiff(): string
    {
        return $this->postedDate()?->diffForHumans() ?? '-';
    }

    public function postedDate(): ?CarbonImmutable
    {
        if ($this->postedAt === null || $this->postedAt === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse($this->postedAt);
        } catch (\Throwable) {
            return null;
        }
    }

    public function hasFinancing(): bool
    {
        return $this->installmentMonthly !== null || $this->installmentText !== null;
    }

    public function installmentLabel(): ?string
    {
        if ($this->installmentMonthly !== null) {
            return 'Rp '.number_format($this->installmentMonthly, 0, ',', '.').'/bulan';
        }

        return $this->installmentText;
    }

    public function downPaymentLabel(): ?string
    {
        if ($this->downPayment !== null) {
            return 'Rp '.number_format($this->downPayment, 0, ',', '.');
        }

        return $this->downPaymentText;
    }

    public function termLabel(): ?string
    {
        if ($this->termMonths !== null) {
            return $this->termMonths.' bulan';
        }

        return $this->termText;
    }

    public function discountLabel(): ?string
    {
        if ($this->discountAmount !== null) {
            return 'Hemat Rp '.number_format($this->discountAmount, 0, ',', '.');
        }

        return $this->discountText;
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public function perks(): array
    {
        $perks = [
            ['label' => 'Garansi', 'value' => $this->warrantyText],
            ['label' => 'Test drive', 'value' => $this->testDriveText],
            ['label' => 'Perawatan', 'value' => $this->maintenanceText],
            ['label' => 'Booking', 'value' => $this->bookingText],
            ['label' => 'Status penjual', 'value' => $this->sellerStatusText],
        ];

        return array_values(array_filter(
            $perks,
            fn (array $perk): bool => filled($perk['value']),
        ));
    }

    /**
     * @return array<string, string>
     */
    public function specificationRows(): array
    {
        $rows = [
            'Manufaktur' => $this->brand,
            'Tipe / model' => $this->model,
            'Tahun pembuatan' => $this->yearLabel(),
            'Jenis mobil' => $this->bodyType,
            'Transmisi' => $this->transmission,
            'Sistem penggerak' => $this->drivetrain,
            'Bahan bakar' => $this->fuel,
            'Kilometer' => $this->mileageLabel(),
            'Lokasi' => $this->location,
            'Tanggal posting' => $this->postedLabel(),
            'Nomor data' => (string) $this->order,
        ];

        return array_filter($rows, fn (string $value): bool => $value !== '' && $value !== '-');
    }

    public function searchText(): string
    {
        return Str::lower(implode(' ', [
            $this->label,
            $this->brand,
            $this->model,
            $this->bodyType,
            $this->transmission,
            $this->drivetrain,
            $this->fuel,
            $this->location,
            $this->yearText,
        ]));
    }

    public function summary(): string
    {
        return implode(' · ', array_filter([
            $this->yearLabel(),
            $this->transmission,
            $this->mileageLabel(),
            $this->location,
        ]));
    }

    /**
     * Short JSON-LD description used on the detail page. The full semantic
     * representation is the RDF graph in Fuseki, not this convenience copy.
     *
     * @return array<string, mixed>
     */
    public function toJsonLd(): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Vehicle',
            '@id' => $this->uri,
            'name' => $this->label,
            'brand' => ['@type' => 'Brand', 'name' => $this->brand],
            'model' => $this->model,
            'vehicleModelDate' => $this->year,
            'bodyType' => $this->bodyType,
            'vehicleTransmission' => $this->transmission,
            'driveWheelConfiguration' => $this->drivetrain,
            'fuelType' => $this->fuel,
            'mileageFromOdometer' => $this->mileageMidpoint() !== null ? [
                '@type' => 'QuantitativeValue',
                'value' => $this->mileageMidpoint(),
                'unitCode' => 'KMT',
            ] : null,
            'image' => $this->imageUrl,
            'offers' => [
                '@type' => 'Offer',
                'price' => $this->price,
                'priceCurrency' => 'IDR',
                'availability' => 'https://schema.org/InStock',
                'areaServed' => $this->location,
            ],
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
