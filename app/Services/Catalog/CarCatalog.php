<?php

namespace App\Services\Catalog;

use App\Services\Fuseki\FusekiClient;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Reads the whole catalogue from Apache Jena Fuseki and filters it in memory.
 *
 * Keyword searches are sent to Fuseki as part of the listing query. The graph
 * holds 914 vehicles, which is small enough for one SPARQL query to be cheaper
 * and far more predictable than composing a different query for every
 * combination of filters, facets and sort orders. Results are cached for a few
 * minutes because the dataset is read-only between imports.
 */
class CarCatalog
{
    public function __construct(private readonly FusekiClient $fuseki) {}

    /**
     * @return Collection<int, CarListing>
     */
    public function all(?string $term = null): Collection
    {
        $term = $this->searchTerm($term);

        // Cache the raw SPARQL bindings (plain arrays) rather than a Collection
        // of value objects: file caches across processes are happier with
        // arrays, and hydrating 914 rows is cheap.
        $rows = Cache::remember(
            $this->cacheKey($term),
            now()->addSeconds((int) config('fuseki.cache_ttl', 300)),
            fn (): array => $this->fuseki->select($this->listingsQuery($term)),
        );

        return collect($rows)
            ->map(fn (array $row): CarListing => $this->hydrate($row))
            ->sortBy('order')
            ->values();
    }

    public function find(string $id): ?CarListing
    {
        $id = trim($id);

        return $this->all()->first(
            fn (CarListing $car): bool => $car->id === $id
                || Str::endsWith($car->uri, '#'.$id)
                || $car->uri === $id,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function filtersFrom(array $input): array
    {
        return [
            'q' => $this->stringOrNull($input['q'] ?? null),
            'brand' => $this->stringList($input['brand'] ?? null),
            'body_type' => $this->stringList($input['body_type'] ?? null),
            'transmission' => $this->stringList($input['transmission'] ?? null),
            'drivetrain' => $this->stringList($input['drivetrain'] ?? null),
            'fuel' => $this->stringList($input['fuel'] ?? null),
            'location' => $this->stringList($input['location'] ?? null),
            'min_price' => $this->intOrNull($input['min_price'] ?? null),
            'max_price' => $this->intOrNull($input['max_price'] ?? null),
            'min_year' => $this->intOrNull($input['min_year'] ?? null),
            'max_mileage' => $this->intOrNull($input['max_mileage'] ?? null),
            'with_financing' => filter_var($input['with_financing'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'with_image' => filter_var($input['with_image'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'sort' => $this->sortOption($input['sort'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 24): LengthAwarePaginator
    {
        $term = $this->searchTerm($filters['q'] ?? null);
        $filtered = $this->sort($this->apply($this->all($term), $filters), (string) ($filters['sort'] ?? 'terbaru'));

        $page = Paginator::resolveCurrentPage() ?: 1;

        return new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            [
                'path' => Paginator::resolveCurrentPath(),
                'query' => request()->query(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function facets(array $filters): array
    {
        $cars = $this->all($this->searchTerm($filters['q'] ?? null));

        return [
            'brand' => $this->facetCounts($this->apply($cars, $filters, 'brand'), 'brand', $filters['brand'] ?? [], 15),
            'body_type' => $this->facetCounts($this->apply($cars, $filters, 'body_type'), 'bodyType', $filters['body_type'] ?? [], 12),
            'transmission' => $this->facetCounts($this->apply($cars, $filters, 'transmission'), 'transmission', $filters['transmission'] ?? [], 8),
            'drivetrain' => $this->facetCounts($this->apply($cars, $filters, 'drivetrain'), 'drivetrain', $filters['drivetrain'] ?? [], 8),
            'fuel' => $this->facetCounts($this->apply($cars, $filters, 'fuel'), 'fuel', $filters['fuel'] ?? [], 8),
            'location' => $this->facetCounts($this->apply($cars, $filters, 'location'), 'location', $filters['location'] ?? [], 15),
            'total' => $this->apply($cars, $filters)->count(),
            'with_financing' => $this->apply($cars, $filters)->filter(fn (CarListing $car): bool => $car->hasFinancing())->count(),
            'with_image' => $this->apply($cars, $filters)->filter(fn (CarListing $car): bool => filled($car->imageUrl))->count(),
            'min_price' => (int) ($this->apply($cars, $filters)->min('price') ?? 0),
            'max_price' => (int) ($this->apply($cars, $filters)->max('price') ?? 0),
            'min_year' => (int) ($cars->min('year') ?? 1988),
            'max_year' => (int) ($cars->max('year') ?? (int) date('Y')),
        ];
    }

    /**
     * @return list<array{value: string, label: string, count: int, selected: bool}>
     */
    public function brandFacets(int $limit = 15): array
    {
        return $this->facetCounts($this->all(), 'brand', [], $limit);
    }

    /**
     * @return list<array{value: string, label: string, count: int, selected: bool}>
     */
    public function locationFacets(int $limit = 12): array
    {
        return $this->facetCounts($this->all(), 'location', [], $limit);
    }

    /**
     * @return Collection<int, CarListing>
     */
    public function related(CarListing $car, int $limit = 4): Collection
    {
        return $this->all()
            ->where('brand', $car->brand)
            ->where('id', '!==', $car->id)
            ->sortByDesc('postedAt')
            ->take($limit)
            ->values();
    }

    /**
     * @return array{total: int, brands: int, locations: int, lowest: int, highest: int, with_image: int, with_financing: int, oldest_year: int, newest_year: int, latest_posted: ?string}
     */
    public function statistics(): array
    {
        $cars = $this->all();

        return [
            'total' => $cars->count(),
            'brands' => $cars->pluck('brand')->unique()->count(),
            'locations' => $cars->pluck('location')->unique()->count(),
            'lowest' => (int) ($cars->min('price') ?? 0),
            'highest' => (int) ($cars->max('price') ?? 0),
            'with_image' => $cars->filter(fn (CarListing $car): bool => filled($car->imageUrl))->count(),
            'with_financing' => $cars->filter(fn (CarListing $car): bool => $car->hasFinancing())->count(),
            'oldest_year' => (int) ($cars->min('year') ?? 1988),
            'newest_year' => (int) ($cars->max('year') ?? (int) date('Y')),
            'latest_posted' => $cars->max('postedAt'),
        ];
    }

    public function forgetCache(): void
    {
        Cache::forever(
            $this->cacheVersionKey(),
            ((int) Cache::get($this->cacheVersionKey(), 1)) + 1,
        );
    }

    private function cacheKey(?string $term = null): string
    {
        $version = (int) Cache::get($this->cacheVersionKey(), 1);
        $base = 'fuseki:'.config('fuseki.dataset').":catalogue:live:v{$version}";

        return $term === null ? $base : $base.':search:'.sha1(Str::lower($term));
    }

    private function cacheVersionKey(): string
    {
        return 'fuseki:'.config('fuseki.dataset').':catalogue:version';
    }

    private function searchTerm(mixed $term): ?string
    {
        $term = is_string($term) ? trim($term) : '';

        return $term === '' ? null : $term;
    }

    private function searchFilter(?string $term): string
    {
        if ($term === null) {
            return '';
        }

        $literal = json_encode($term, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($literal === false) {
            return '';
        }

        return <<<SPARQL
              BIND(LCASE({$literal}) AS ?searchTerm)
              FILTER(
                CONTAINS(LCASE(?label), ?searchTerm)
                || CONTAINS(LCASE(?model), ?searchTerm)
                || CONTAINS(LCASE(?yearText), ?searchTerm)
                || CONTAINS(LCASE(COALESCE(?brandLabel, "")), ?searchTerm)
                || CONTAINS(LCASE(COALESCE(?bodyTypeLabel, "")), ?searchTerm)
                || CONTAINS(LCASE(COALESCE(?transmissionLabel, "")), ?searchTerm)
                || CONTAINS(LCASE(COALESCE(?drivetrainLabel, "")), ?searchTerm)
                || CONTAINS(LCASE(COALESCE(?fuelLabel, "")), ?searchTerm)
                || CONTAINS(LCASE(COALESCE(?locationLabel, "")), ?searchTerm)
              )
            SPARQL;
    }

    private function listingsQuery(?string $term = null): string
    {
        $namespace = (string) config('fuseki.namespace');
        $searchFilter = $this->searchFilter($term);

        return <<<SPARQL
            PREFIX mb: <{$namespace}>
            PREFIX rdfs: <http://www.w3.org/2000/01/rdf-schema#>

            SELECT ?s ?label ?order
                   ?brand ?brandLabel ?model
                   ?bodyType ?bodyTypeLabel
                   ?transmission ?transmissionLabel
                   ?drivetrain ?drivetrainLabel
                   ?fuel ?fuelLabel
                   ?location ?locationLabel
                   ?priceText ?price ?yearText ?mileage ?posted ?image
                   ?installment ?term ?downPayment ?discount
                   ?warranty ?testDrive ?maintenance ?booking ?sellerStatus
            WHERE {
              ?s a mb:MobilBekas ;
                 rdfs:label ?label ;
                 mb:orderBaru ?order ;
                 mb:memilikiManufaktur ?brand ;
                 mb:namaTipe ?model ;
                 mb:memilikiJenisMobil ?bodyType ;
                 mb:memilikiPenggerak ?drivetrain ;
                 mb:memilikiTransmisi ?transmission ;
                 mb:memilikiBahanBakar ?fuel ;
                 mb:berlokasiDi ?location ;
                 mb:hargaTeks ?priceText ;
                 mb:hargaNumerik ?price ;
                 mb:tahunPembuatan ?yearText ;
                 mb:kilometer ?mileage ;
                 mb:tanggalPosting ?posted .

              OPTIONAL { ?brand rdfs:label ?brandLabel }
              OPTIONAL { ?bodyType rdfs:label ?bodyTypeLabel }
              OPTIONAL { ?transmission rdfs:label ?transmissionLabel }
              OPTIONAL { ?drivetrain rdfs:label ?drivetrainLabel }
              OPTIONAL { ?fuel rdfs:label ?fuelLabel }
              OPTIONAL { ?location rdfs:label ?locationLabel }
              OPTIONAL { ?s mb:gambarURL ?image }
              OPTIONAL { ?s mb:cicilan ?installment }
              OPTIONAL { ?s mb:jangkaWaktu ?term }
              OPTIONAL { ?s mb:uangMuka ?downPayment }
              OPTIONAL { ?s mb:diskon ?discount }
              OPTIONAL { ?s mb:garansi ?warranty }
              OPTIONAL { ?s mb:testDrive ?testDrive }
              OPTIONAL { ?s mb:jasaPerawatan ?maintenance }
              OPTIONAL { ?s mb:fiturBooking ?booking }
              OPTIONAL { ?s mb:statusPenjual ?sellerStatus }
              {$searchFilter}
            }
            ORDER BY ?order
            SPARQL;
    }

    /**
     * @param  array<string, string>  $row
     */
    private function hydrate(array $row): CarListing
    {
        $uri = $row['s'] ?? '';
        [$mileageMin, $mileageMax] = $this->parseMileage($row['mileage'] ?? '');
        $yearText = trim((string) ($row['yearText'] ?? ''));

        return new CarListing(
            id: $this->localName($uri),
            uri: $uri,
            label: trim((string) ($row['label'] ?? 'Tanpa nama')),
            order: (int) ($row['order'] ?? 0),
            brand: $this->value($row, 'brandLabel', 'brand'),
            model: trim((string) ($row['model'] ?? '')),
            bodyType: $this->value($row, 'bodyTypeLabel', 'bodyType'),
            transmission: $this->value($row, 'transmissionLabel', 'transmission'),
            drivetrain: $this->value($row, 'drivetrainLabel', 'drivetrain'),
            fuel: $this->value($row, 'fuelLabel', 'fuel'),
            location: $this->value($row, 'locationLabel', 'location'),
            priceText: trim((string) ($row['priceText'] ?? '')),
            price: (int) ($row['price'] ?? 0),
            yearText: $yearText,
            year: $this->parseYear($yearText),
            mileageText: trim((string) ($row['mileage'] ?? '')),
            mileageMin: $mileageMin,
            mileageMax: $mileageMax,
            postedAt: $this->nullIfBlank($row['posted'] ?? null),
            imageUrl: $this->nullIfBlank($row['image'] ?? null),
            installmentText: $this->nullIfBlank($row['installment'] ?? null),
            installmentMonthly: $this->parseRupiah($row['installment'] ?? null),
            termText: $this->nullIfBlank($row['term'] ?? null),
            termMonths: $this->parseTerm($row['term'] ?? null),
            downPaymentText: $this->nullIfBlank($row['downPayment'] ?? null),
            downPayment: $this->parseRupiah($row['downPayment'] ?? null),
            discountText: $this->nullIfBlank($row['discount'] ?? null),
            discountAmount: $this->parseRupiah($row['discount'] ?? null),
            warrantyText: $this->nullIfBlank($row['warranty'] ?? null),
            testDriveText: $this->nullIfBlank($row['testDrive'] ?? null),
            maintenanceText: $this->nullIfBlank($row['maintenance'] ?? null),
            bookingText: $this->nullIfBlank($row['booking'] ?? null),
            sellerStatusText: $this->nullIfBlank($row['sellerStatus'] ?? null),
        );
    }

    /**
     * @param  array<string, string>  $row
     */
    private function value(array $row, string $labelKey, string $iriKey): string
    {
        $label = trim((string) ($row[$labelKey] ?? ''));

        if ($label !== '') {
            return $label;
        }

        return Str::headline(str_replace('_', ' ', $this->localName($row[$iriKey] ?? '')));
    }

    private function localName(string $iri): string
    {
        if ($iri === '') {
            return '';
        }

        if (str_contains($iri, '#')) {
            return Str::afterLast($iri, '#');
        }

        return Str::afterLast($iri, '/');
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function parseMileage(?string $text): array
    {
        if (! filled($text)) {
            return [null, null];
        }

        preg_match_all('/\d[\d\.]*/', $text, $matches);

        $numbers = array_values(array_filter(array_map(
            fn (string $number): int => (int) str_replace('.', '', $number),
            $matches[0],
        ), fn (int $number): bool => $number >= 0));

        if ($numbers === []) {
            return [null, null];
        }

        return [min($numbers), max($numbers)];
    }

    private function parseYear(string $text): int
    {
        if (preg_match('/(19|20)\d{2}/', $text, $matches) === 1) {
            return (int) $matches[0];
        }

        return 0;
    }

    private function parseRupiah(?string $text): ?int
    {
        if (! filled($text)) {
            return null;
        }

        if (preg_match('/([\d.,]+)\s*jt/i', $text, $matches) === 1) {
            return (int) round(((float) str_replace(',', '.', $matches[1])) * 1_000_000);
        }

        if (preg_match('/(\d[\d\.]*)/', $text, $matches) === 1) {
            return (int) str_replace('.', '', $matches[1]);
        }

        return null;
    }

    private function parseTerm(?string $text): ?int
    {
        if (filled($text) && preg_match('/x\s*(\d+)\s*bulan/i', $text, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    private function nullIfBlank(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }

    /**
     * @param  Collection<int, CarListing>  $cars
     * @param  array<string, mixed>  $filters
     * @return Collection<int, CarListing>
     */
    private function apply(Collection $cars, array $filters, ?string $except = null): Collection
    {
        $term = $filters['q'] ?? null;

        if (filled($term) && $except !== 'q') {
            $needle = Str::lower((string) $term);
            $cars = $cars->filter(fn (CarListing $car): bool => str_contains($car->searchText(), $needle));
        }

        $textFilters = [
            'brand' => 'brand',
            'body_type' => 'bodyType',
            'transmission' => 'transmission',
            'drivetrain' => 'drivetrain',
            'fuel' => 'fuel',
            'location' => 'location',
        ];

        foreach ($textFilters as $key => $property) {
            $selected = $filters[$key] ?? [];

            if ($selected !== [] && $except !== $key) {
                $cars = $cars->filter(
                    fn (CarListing $car): bool => in_array($car->{$property}, $selected, true),
                );
            }
        }

        if ($except !== 'price') {
            if (($filters['min_price'] ?? null) !== null) {
                $cars = $cars->filter(fn (CarListing $car): bool => $car->price >= (int) $filters['min_price']);
            }

            if (($filters['max_price'] ?? null) !== null) {
                $cars = $cars->filter(fn (CarListing $car): bool => $car->price <= (int) $filters['max_price']);
            }
        }

        if ($except !== 'year' && ($filters['min_year'] ?? null) !== null) {
            $cars = $cars->filter(fn (CarListing $car): bool => $car->year >= (int) $filters['min_year']);
        }

        if ($except !== 'mileage' && ($filters['max_mileage'] ?? null) !== null) {
            $cars = $cars->filter(
                fn (CarListing $car): bool => $car->mileageMax === null || $car->mileageMax <= (int) $filters['max_mileage'],
            );
        }

        if ($except !== 'with_financing' && ($filters['with_financing'] ?? false)) {
            $cars = $cars->filter(fn (CarListing $car): bool => $car->hasFinancing());
        }

        if ($except !== 'with_image' && ($filters['with_image'] ?? false)) {
            $cars = $cars->filter(fn (CarListing $car): bool => filled($car->imageUrl));
        }

        return $cars;
    }

    /**
     * @param  Collection<int, CarListing>  $cars
     * @return Collection<int, CarListing>
     */
    private function sort(Collection $cars, string $sort): Collection
    {
        return match ($sort) {
            'harga_terendah' => $cars->sortBy([['price', 'asc'], ['id', 'asc']])->values(),
            'harga_tertinggi' => $cars->sortBy([['price', 'desc'], ['id', 'asc']])->values(),
            'tahun_terbaru' => $cars->sortBy([['year', 'desc'], ['id', 'asc']])->values(),
            'kilometer_terendah' => $cars->sort(fn (CarListing $a, CarListing $b): int => ($a->mileageMax ?? PHP_INT_MAX) <=> ($b->mileageMax ?? PHP_INT_MAX))->values(),
            default => $cars->sortByDesc('postedAt')->values(),
        };
    }

    /**
     * @param  Collection<int, CarListing>  $cars
     * @param  list<string>  $selected
     * @return list<array{value: string, label: string, count: int, selected: bool}>
     */
    private function facetCounts(Collection $cars, string $property, array $selected, int $limit): array
    {
        return $cars
            ->groupBy(fn (CarListing $car): string => (string) $car->{$property})
            ->map(fn (Collection $group, string $value): array => [
                'value' => $value,
                'label' => $value,
                'count' => $group->count(),
                'selected' => in_array($value, $selected, true),
            ])
            ->sortByDesc('count')
            ->take($limit)
            ->values()
            ->all();
    }

    private function stringOrNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        $values = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map(
            fn (mixed $item): string => is_string($item) ? trim($item) : '',
            $values,
        ), fn (string $item): bool => $item !== ''));
    }

    private function sortOption(mixed $value): string
    {
        $allowed = ['terbaru', 'harga_terendah', 'harga_tertinggi', 'tahun_terbaru', 'kilometer_terendah'];

        return in_array($value, $allowed, true) ? (string) $value : 'terbaru';
    }
}
