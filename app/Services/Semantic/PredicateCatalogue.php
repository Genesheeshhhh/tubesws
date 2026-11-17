<?php

namespace App\Services\Semantic;

/**
 * Human labels and descriptions for the predicates this marketplace publishes.
 *
 * A graph browser is only useful to a person if it can say what a predicate
 * means in Indonesian rather than showing a bare IRI, and the same table is
 * reused by the ontology page so the two never drift apart.
 */
class PredicateCatalogue
{
    /**
     * @return array<string, array{label: string, description: string, domain: string}>
     */
    public function all(): array
    {
        return [
            'rdf:type' => [
                'label' => 'tipe',
                'description' => 'Kelas dari sumber daya ini.',
                'domain' => 'owl:Thing',
            ],
            'rdfs:label' => [
                'label' => 'nama',
                'description' => 'Nama yang mudah dibaca manusia.',
                'domain' => 'rdfs:Resource',
            ],
            'schema:name' => [
                'label' => 'nama',
                'description' => 'Nama listing, misalnya "Toyota Avanza 1.3 G".',
                'domain' => 'schema:Thing',
            ],
            'schema:url' => [
                'label' => 'tautan',
                'description' => 'Alamat kanonis sumber daya.',
                'domain' => 'schema:Thing',
            ],
            'schema:description' => [
                'label' => 'deskripsi',
                'description' => 'Keterangan bebas dari penjual.',
                'domain' => 'schema:Thing',
            ],
            'schema:brand' => [
                'label' => 'merek',
                'description' => 'Merek kendaraan, tertaut ke node schema:Brand.',
                'domain' => 'schema:Vehicle',
            ],
            'schema:model' => [
                'label' => 'tipe',
                'description' => 'Nama model kendaraan, misalnya Avanza atau Brio.',
                'domain' => 'schema:Vehicle',
            ],
            'schema:productionDate' => [
                'label' => 'tahun produksi',
                'description' => 'Tahun pembuatan kendaraan.',
                'domain' => 'schema:Vehicle',
            ],
            'schema:vehicleTransmission' => [
                'label' => 'transmisi',
                'description' => 'Jenis transmisi kendaraan.',
                'domain' => 'schema:Vehicle',
            ],
            'schema:driveWheelConfiguration' => [
                'label' => 'sistem penggerak',
                'description' => 'Konfigurasi roda penggerak kendaraan, seperti AWD, FWD, RWD, atau 4WD.',
                'domain' => 'schema:Vehicle',
            ],
            'schema:fuelType' => [
                'label' => 'bahan bakar',
                'description' => 'Jenis bahan bakar kendaraan.',
                'domain' => 'schema:Vehicle',
            ],
            'schema:color' => [
                'label' => 'warna',
                'description' => 'Warna bodi kendaraan.',
                'domain' => 'schema:Vehicle',
            ],
            'schema:bodyType' => [
                'label' => 'tipe bodi',
                'description' => 'Bentuk bodi, misalnya SUV atau MPV.',
                'domain' => 'schema:Vehicle',
            ],
            'schema:mileageFromOdometer' => [
                'label' => 'kilometer',
                'description' => 'Pembacaan odometer sebagai schema:QuantitativeValue.',
                'domain' => 'schema:Vehicle',
            ],
            'schema:value' => [
                'label' => 'nilai',
                'description' => 'Nilai terukur dari properti kuantitatif.',
                'domain' => 'schema:QuantitativeValue',
            ],
            'schema:unitCode' => [
                'label' => 'satuan',
                'description' => 'Kode satuan UN/CEFACT, KMT untuk kilometer.',
                'domain' => 'schema:QuantitativeValue',
            ],
            'schema:offers' => [
                'label' => 'penawaran',
                'description' => 'Node schema:Offer yang memuat terms komersial.',
                'domain' => 'schema:Product',
            ],
            'schema:itemCondition' => [
                'label' => 'kondisi',
                'description' => 'Kondisi barang, tertaut ke schema:ItemCondition.',
                'domain' => 'schema:Offer',
            ],
            'schema:price' => [
                'label' => 'harga',
                'description' => 'Harga penawaran dalam satuan mata uang.',
                'domain' => 'schema:Offer',
            ],
            'schema:priceCurrency' => [
                'label' => 'mata uang',
                'description' => 'Kode mata uang ISO 4217.',
                'domain' => 'schema:Offer',
            ],
            'schema:availability' => [
                'label' => 'ketersediaan',
                'description' => 'Status stok, misalnya InStock atau SoldOut.',
                'domain' => 'schema:Offer',
            ],
            'schema:datePosted' => [
                'label' => 'tanggal pasang',
                'description' => 'Waktu iklan dipublikasikan.',
                'domain' => 'schema:Offer',
            ],
            'schema:priceValidUntil' => [
                'label' => 'berlaku sampai',
                'description' => 'Tanggal berakhirnya penawaran.',
                'domain' => 'schema:Offer',
            ],
            'schema:seller' => [
                'label' => 'penjual',
                'description' => 'Akun yang memasang iklan.',
                'domain' => 'schema:Offer',
            ],
            'schema:priceSpecification' => [
                'label' => 'skema harga',
                'description' => 'Rincian cicilan untuk skema kredit.',
                'domain' => 'schema:Offer',
            ],
            'schema:installmentAmount' => [
                'label' => 'cicilan per bulan',
                'description' => 'Nilai angsuran bulanan.',
                'domain' => 'schema:UnitPriceSpecification',
            ],
            'schema:installmentNumber' => [
                'label' => 'jumlah angsuran',
                'description' => 'Tenor dalam bulan.',
                'domain' => 'schema:UnitPriceSpecification',
            ],
            'schema:availableAtOrFrom' => [
                'label' => 'lokasi',
                'description' => 'Kota pengambilan unit, tertaut ke schema:Place.',
                'domain' => 'schema:Offer',
            ],
            'schema:address' => [
                'label' => 'alamat',
                'description' => 'Alamat lengkap unit.',
                'domain' => 'schema:Place',
            ],
            'schema:image' => [
                'label' => 'foto',
                'description' => 'Tautan ke berkas foto pada disk lokal.',
                'domain' => 'schema:Thing',
            ],
            'app:downPayment' => [
                'label' => 'uang muka',
                'description' => 'Uang muka skema kredit.',
                'domain' => 'app:CarListing',
            ],
            'app:discountAmount' => [
                'label' => 'diskon',
                'description' => 'Potongan harga yang ditawarkan penjual.',
                'domain' => 'app:CarListing',
            ],
            'app:hasWarranty' => [
                'label' => 'garansi',
                'description' => 'Apakah penjual menyertakan garansi.',
                'domain' => 'app:CarListing',
            ],
            'app:verifiedSeller' => [
                'label' => 'penjual terverifikasi',
                'description' => 'Apakah identitas penjual sudah diverifikasi.',
                'domain' => 'app:CarListing',
            ],
            'app:bookingIsSafe' => [
                'label' => 'booking aman',
                'description' => 'Apakah tersedia layanan booking aman.',
                'domain' => 'app:CarListing',
            ],
            'app:homeTestDrive' => [
                'label' => 'test drive ke rumah',
                'description' => 'Apakah penjual menawarkan test drive di kediaman.',
                'domain' => 'app:CarListing',
            ],
            'app:freeMaintenance' => [
                'label' => 'servis gratis',
                'description' => 'Apakah termasuk paket servis gratis.',
                'domain' => 'app:CarListing',
            ],
            'app:freeInsurance' => [
                'label' => 'asuransi gratis',
                'description' => 'Apakah termasuk asuransi gratis.',
                'domain' => 'app:CarListing',
            ],
            'app:sourceReference' => [
                'label' => 'referensi sumber',
                'description' => 'Kode iklan asal pada penyedia data.',
                'domain' => 'app:CarListing',
            ],
        ];
    }

    /**
     * @return array{label: string, description: string, domain: string}|null
     */
    public function describe(string $predicate): ?array
    {
        return $this->all()[$predicate] ?? null;
    }
}
