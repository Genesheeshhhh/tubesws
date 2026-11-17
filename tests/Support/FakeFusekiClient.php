<?php

namespace Tests\Support;

use App\Services\Fuseki\FusekiClient;
use App\Services\Fuseki\FusekiException;
use App\Services\Fuseki\SparqlResult;

/**
 * In-memory stand-in for the Fuseki client so feature tests can exercise the
 * catalogue, semantic pages and SPARQL explorer without a running server.
 */
class FakeFusekiClient extends FusekiClient
{
    /** @var list<array<string, string>> */
    public array $listings = [];

    /** @var list<string> */
    public array $queries = [];

    public bool $fail = false;

    public string $failureMessage = 'Fuseki tidak tersedia untuk pengujian.';

    public function __construct()
    {
        $this->listings = [
            $this->listing(
                id: 'Mobil_5',
                label: 'BMW 318i (1996)',
                brand: 'BMW',
                model: '318i',
                bodyType: 'Sedan',
                transmission: 'Matic',
                drivetrain: 'RWD',
                fuel: 'Bensin',
                location: 'Medan Petisah',
                price: 85_000_000,
                year: '1996',
                mileage: '175.000-180.000 km',
                posted: '2026-09-24',
                image: 'https://example.test/bmw.jpg',
            ),
            $this->listing(
                id: 'Mobil_6',
                label: 'Toyota Avanza (2020)',
                brand: 'Toyota',
                model: 'Avanza',
                bodyType: 'MPV',
                transmission: 'Matic',
                drivetrain: 'FWD',
                fuel: 'Bensin',
                location: 'Medan Kota',
                price: 180_000_000,
                year: '2020',
                mileage: '45.000-50.000 km',
                posted: '2026-09-30',
                image: null,
                installment: 'Cicilan Rp 3.5 jt/bulan',
                term: 'Rp 3.500.000 x 60 Bulan',
                downPayment: 'Rp 30.000.000',
                discount: 'DISKON 5jt',
                warranty: 'Garansi 1 tahun',
                testDrive: 'Bisa test drive',
                maintenance: 'Gratis servis',
                booking: 'Booking online',
                sellerStatus: 'Penjual Terverifikasi',
            ),
            $this->listing(
                id: 'Mobil_7',
                label: 'Honda Brio (2022)',
                brand: 'Honda',
                model: 'Brio',
                bodyType: 'City Car',
                transmission: 'Matic',
                drivetrain: 'FWD',
                fuel: 'Bensin',
                location: 'Medan Selayang',
                price: 150_000_000,
                year: '2022',
                mileage: '20.000-25.000 km',
                posted: '2026-09-20',
                image: 'https://example.test/brio.jpg',
                installment: 'Cicilan Rp 2.9 jt/bulan',
                term: 'Rp 2.900.000 x 60 Bulan',
                downPayment: 'Rp 25.000.000',
            ),
        ];
    }

    /**
     * @return list<array<string, string>>
     */
    public function select(string $sparql): array
    {
        $this->guard();

        $this->queries[] = $sparql;

        if (str_contains($sparql, '?s ?label ?order')) {
            return $this->listings;
        }

        if (str_contains($sparql, 'COUNT(*) AS ?triples')) {
            return [['triples' => '150', 'subjects' => '40', 'predicates' => '12']];
        }

        if (str_contains($sparql, 'FILTER(isIRI(?o))')) {
            return [['n' => '90']];
        }

        if (str_contains($sparql, 'FILTER(isLiteral(?o))')) {
            return [['n' => '60']];
        }

        if (str_contains($sparql, 'owl:Class')) {
            return [
                ['class' => $this->namespace().'MobilBekas', 'label' => 'Mobil Bekas', 'instances' => (string) count($this->listings)],
                ['class' => $this->namespace().'Manufaktur', 'label' => 'Manufaktur', 'instances' => '3'],
            ];
        }

        if (str_contains($sparql, 'ObjectProperty')) {
            return [
                ['predicate' => $this->namespace().'memilikiManufaktur', 'label' => 'Memiliki Manufaktur', 'type' => 'http://www.w3.org/2002/07/owl#ObjectProperty', 'uses' => '3'],
                ['predicate' => $this->namespace().'hargaNumerik', 'label' => 'Harga Numerik', 'type' => 'http://www.w3.org/2002/07/owl#DatatypeProperty', 'uses' => '3'],
            ];
        }

        if (preg_match('/<([^>]+)>\s+\?p\s+\?o/', $sparql, $matches) === 1) {
            return [
                ['p' => $this->namespace().'hargaNumerik', 'o' => '85000000', 'isIri' => 'false', 'label' => 'Harga Numerik', 'oLabel' => ''],
                ['p' => 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type', 'o' => $this->namespace().'MobilBekas', 'isIri' => 'true', 'label' => 'Tipe', 'oLabel' => 'Mobil Bekas'],
            ];
        }

        if (str_contains($sparql, '?s ?p ?o')) {
            return [[
                's' => $this->namespace().'Mobil_5',
                'p' => $this->namespace().'hargaNumerik',
                'o' => '85000000',
            ]];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    public function selectJson(string $sparql): array
    {
        $this->guard();

        if (SparqlResult::kindOf($sparql) === 'ask') {
            return ['boolean' => true];
        }

        return [
            'head' => ['vars' => ['mobil', 'nama']],
            'results' => ['bindings' => [[
                'mobil' => ['type' => 'uri', 'value' => $this->namespace().'Mobil_5'],
                'nama' => ['type' => 'literal', 'value' => 'BMW 318i (1996)'],
            ]]],
        ];
    }

    public function construct(string $sparql, string $accept = 'text/turtle'): string
    {
        $this->guard();

        return '@prefix mb: <'.$this->namespace()."> .\n".
            '<'.$this->namespace().'Mobil_5> a mb:MobilBekas .';
    }

    public function graph(string $accept = 'text/turtle'): string
    {
        $this->guard();

        return $this->construct('');
    }

    public function isAvailable(): bool
    {
        return ! $this->fail;
    }

    public function queryEndpoint(): string
    {
        return $this->datasetUrl().'/query';
    }

    public function updateEndpoint(): string
    {
        return $this->datasetUrl().'/update';
    }

    public function datasetUrl(): string
    {
        return 'http://127.0.0.1:3030/TUBESWS';
    }

    public function datasetName(): string
    {
        return 'TUBESWS';
    }

    private function namespace(): string
    {
        return 'http://www.semanticweb.org/mobil-bekas-medan#';
    }

    private function guard(): void
    {
        if ($this->fail) {
            throw new FusekiException($this->failureMessage);
        }
    }

    /**
     * @return array<string, string>
     */
    private function listing(
        string $id,
        string $label,
        string $brand,
        string $model,
        string $bodyType,
        string $transmission,
        string $drivetrain,
        string $fuel,
        string $location,
        int $price,
        string $year,
        string $mileage,
        string $posted,
        ?string $image,
        ?string $installment = null,
        ?string $term = null,
        ?string $downPayment = null,
        ?string $discount = null,
        ?string $warranty = null,
        ?string $testDrive = null,
        ?string $maintenance = null,
        ?string $booking = null,
        ?string $sellerStatus = null,
    ): array {
        $local = fn (string $value): string => $this->namespace().$value;

        return [
            's' => $local($id),
            'label' => $label,
            'order' => substr($id, strrpos($id, '_') + 1),
            'brand' => $local('Manufaktur_'.$brand),
            'brandLabel' => $brand,
            'model' => $model,
            'bodyType' => $local('Jenis_'.$bodyType),
            'bodyTypeLabel' => $bodyType,
            'transmission' => $local('Transmisi_'.$transmission),
            'transmissionLabel' => $transmission,
            'drivetrain' => $local('Penggerak_'.$drivetrain),
            'drivetrainLabel' => $drivetrain,
            'fuel' => $local('BahanBakar_'.$fuel),
            'fuelLabel' => $fuel,
            'location' => $local('Lokasi_'.str_replace(' ', '_', $location)),
            'locationLabel' => $location,
            'priceText' => 'Rp '.number_format($price, 0, ',', '.'),
            'price' => (string) $price,
            'yearText' => $year,
            'mileage' => $mileage,
            'posted' => $posted,
            'image' => $image ?? '',
            'installment' => $installment ?? '',
            'term' => $term ?? '',
            'downPayment' => $downPayment ?? '',
            'discount' => $discount ?? '',
            'warranty' => $warranty ?? '',
            'testDrive' => $testDrive ?? '',
            'maintenance' => $maintenance ?? '',
            'booking' => $booking ?? '',
            'sellerStatus' => $sellerStatus ?? '',
        ];
    }
}
