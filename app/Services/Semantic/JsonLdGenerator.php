<?php

namespace App\Services\Semantic;

use App\Models\Car;

/**
 * Builds the schema.org JSON-LD embedded in each page.
 *
 * The JSON-LD and the Turtle graph are two serialisations of the same
 * statements, so a search engine and a SPARQL client read identical facts.
 */
class JsonLdGenerator
{
    public function __construct(private readonly Vocabulary $vocabulary) {}

    /**
     * @param  array<int, Car>  $cars
     * @return array<string, mixed>
     */
    public function itemList(array $cars, string $name, int $total, int $page, int $perPage, string $url): array
    {
        $elements = [];

        foreach (array_values($cars) as $index => $car) {
            $elements[] = [
                '@type' => 'ListItem',
                'position' => ($page - 1) * $perPage + $index + 1,
                'url' => $car->offerUri(),
                'item' => $this->vehicle($car),
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $name,
            'url' => $url,
            'numberOfItems' => $total,
            'itemListElement' => $elements,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function vehicle(Car $car): array
    {
        $data = [
            '@type' => 'Car',
            '@id' => $car->uri(),
            'name' => $car->display_name,
            'url' => $car->offerUri(),
            'brand' => [
                '@type' => 'Brand',
                'name' => $car->brand,
            ],
            'model' => $car->model,
            'productionDate' => (string) $car->year,
            'itemCondition' => $car->condition->schemaUrl(),
            'offers' => $this->offer($car),
        ];

        if ($car->variant !== null) {
            $data['additionalProperty'][] = $this->property('Varian', $car->variant);
        }

        if ($car->mileageKm !== null) {
            $data['mileageFromOdometer'] = [
                '@type' => 'QuantitativeValue',
                'value' => $car->mileageKm,
                'unitCode' => 'KMT',
            ];
        }

        if ($car->transmission !== null) {
            $data['vehicleTransmission'] = $car->transmission->label();
        }

        if ($car->drivetrain !== null) {
            $data['driveWheelConfiguration'] = $car->drivetrain->schemaUrl();
        }

        if ($car->fuel !== null) {
            $data['fuelType'] = $car->fuel->label();
        }

        if ($car->color !== null) {
            $data['color'] = $car->color;
        }

        if ($car->body_type !== null) {
            $data['bodyType'] = $car->body_type;
        }

        if ($car->description !== null) {
            $data['description'] = $car->description;
        }

        $images = $car->images->map(fn ($image): string => $image->url())->all();

        if ($images !== []) {
            $data['image'] = $images;
        }

        $data['address'] = [
            '@type' => 'PostalAddress',
            'addressLocality' => $car->city,
            'streetAddress' => $car->location,
            'addressCountry' => 'ID',
        ];

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function offer(Car $car): array
    {
        $offer = [
            '@type' => 'Offer',
            '@id' => $this->vocabulary->offerIri($car->slug),
            'url' => $car->offerUri(),
            'price' => $car->price,
            'priceCurrency' => config('semantic.currency'),
            'priceValidUntil' => $car->posted_at->clone()->addDays(30)->toDateString(),
            'availability' => $car->status->schemaUrl(),
            'itemCondition' => $car->condition->schemaUrl(),
            'datePosted' => $car->posted_at->toIso8601String(),
        ];

        if ($car->discount_amount !== null) {
            $offer['priceValidUntil'] = $car->posted_at->clone()->addDays(30)->toDateString();
            $offer['additionalProperty'][] = $this->property('Diskon', 'Rp '.number_format($car->discount_amount, 0, ',', '.'));
        }

        $priceSpecification = [];

        if ($car->installment_monthly !== null) {
            $priceSpecification['installmentAmount'] = [
                '@type' => 'MonetaryAmount',
                'value' => $car->installment_monthly,
                'currency' => config('semantic.currency'),
            ];

            if ($car->installment_term !== null) {
                $priceSpecification['installmentNumber'] = $car->installment_term;
            }
        }

        if ($priceSpecification !== []) {
            $offer['priceSpecification'] = $priceSpecification + ['@type' => 'UnitPriceSpecification'];
        }

        if ($car->down_payment !== null) {
            $offer['additionalProperty'][] = $this->property('Uang muka (DP)', 'Rp '.number_format($car->down_payment, 0, ',', '.'));
        }

        foreach ($this->featureProperties($car) as $property) {
            $offer['additionalProperty'][] = $property;
        }

        if ($car->seller !== null) {
            $offer['seller'] = [
                '@type' => 'Organization',
                'name' => $car->seller->name,
                'url' => $this->vocabulary->absoluteUrl('penjual/'.$car->seller->id),
            ];
        }

        return $offer;
    }

    /**
     * @return list<array<string, string>>
     */
    private function featureProperties(Car $car): array
    {
        $features = array_filter([
            $car->is_verified ? 'Penjual terverifikasi' : null,
            $car->has_warranty ? 'Bergaransi' : null,
            $car->free_insurance ? 'Gratis asuransi 1 tahun' : null,
            $car->home_test_drive ? 'Test drive dari rumah' : null,
            $car->free_maintenance ? 'Gratis biaya perawatan' : null,
            $car->booking_is_safe ? 'Booking aman' : null,
        ]);

        $properties = [
            ['@type' => 'PropertyValue', 'name' => 'Kondisi', 'value' => $car->condition->label()],
        ];

        foreach ($features as $feature) {
            $properties[] = ['@type' => 'PropertyValue', 'name' => 'Keunggulan', 'value' => $feature];
        }

        return $properties;
    }

    /**
     * @return array<string, mixed>
     */
    public function website(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('semantic.name'),
            'url' => $this->vocabulary->siteIri(),
            'inLanguage' => config('semantic.language'),
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $this->vocabulary->absoluteUrl('mobil').'?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function organization(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => config('semantic.name'),
            'url' => $this->vocabulary->siteIri(),
            'description' => config('semantic.description'),
        ];
    }

    /**
     * @param  list<array{name: string, url: string}>  $items
     * @return array<string, mixed>
     */
    public function breadcrumb(array $items): array
    {
        $elements = [];

        foreach (array_values($items) as $index => $item) {
            $element = [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
            ];

            if ($index > 0) {
                $element['item'] = $item['url'];
            }

            $elements[] = $element;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $elements,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function property(string $name, string $value): array
    {
        return [
            '@type' => 'PropertyValue',
            'name' => $name,
            'value' => $value,
        ];
    }
}
