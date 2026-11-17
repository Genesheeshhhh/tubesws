<?php

namespace App\Services\Semantic;

use Illuminate\Support\Str;

/**
 * The mapping between catalogue columns and the terms the graph publishes.
 *
 * schema.org supplies an upper ontology for vehicles, offers and places, so it
 * carries every property that already exists there. Terms that describe how
 * this marketplace sells a car rather than what the car is live in the local
 * namespace, which is what makes the graph an extension of schema.org instead
 * of a private dialect.
 */
class Vocabulary
{
    public const RDF_TYPE = 'rdf:type';

    public const XSD_STRING = 'xsd:string';

    public const XSD_INTEGER = 'xsd:integer';

    public const XSD_BOOLEAN = 'xsd:boolean';

    public const XSD_DATE = 'xsd:date';

    public const XSD_DATE_TIME = 'xsd:dateTime';

    public const XSD_G_YEAR = 'xsd:gYear';

    public const XSD_DECIMAL = 'xsd:decimal';

    public const CAR = 'schema:Car';

    public const BRAND = 'schema:Brand';

    public const PLACE = 'schema:Place';

    public const OFFER = 'schema:Offer';

    public const UNIT_PRICE_SPECIFICATION = 'schema:UnitPriceSpecification';

    public const ORGANIZATION = 'schema:Organization';

    public const PERSON = 'schema:Person';

    public const QUANTITATIVE_VALUE = 'schema:QuantitativeValue';

    public const POSTAL_ADDRESS = 'schema:PostalAddress';

    /**
     * Local terms for marketplace concepts schema.org leaves undefined.
     */
    public const LISTING = 'app:CarListing';

    public const DOWN_PAYMENT = 'app:downPayment';

    public const DISCOUNT_AMOUNT = 'app:discountAmount';

    public const BOOKING_IS_SAFE = 'app:bookingIsSafe';

    public const HOME_TEST_DRIVE = 'app:homeTestDrive';

    public const FREE_MAINTENANCE = 'app:freeMaintenance';

    public const FREE_INSURANCE = 'app:freeInsurance';

    public const VERIFIED_SELLER = 'app:verifiedSeller';

    public const WARRANTY = 'app:hasWarranty';

    public const SOURCE_REFERENCE = 'app:sourceReference';

    public const TOTAL_LISTINGS = 'app:totalListings';

    public const LOWEST_PRICE = 'app:lowestPrice';

    public const HIGHEST_PRICE = 'app:highestPrice';

    /**
     * The literal form of each datatype, used when writing triples.
     */
    public function datatypeFor(string $prefixed): string
    {
        return match ($prefixed) {
            self::XSD_INTEGER => config('semantic.datatypes.integer'),
            self::XSD_BOOLEAN => config('semantic.datatypes.boolean'),
            self::XSD_DATE => config('semantic.datatypes.date'),
            self::XSD_DATE_TIME => config('semantic.datatypes.dateTime'),
            self::XSD_G_YEAR => config('semantic.datatypes.gYear'),
            self::XSD_DECIMAL => config('semantic.datatypes.decimal'),
            default => config('semantic.datatypes.string'),
        };
    }

    public function expand(string $prefixed): string
    {
        if (str_starts_with($prefixed, '<')) {
            return trim($prefixed, '<>');
        }

        $prefix = strstr($prefixed, ':', true);

        if ($prefix === false) {
            return $prefixed;
        }

        $namespace = config('semantic.namespaces.'.$prefix);

        if ($namespace === null) {
            return $prefixed;
        }

        return $namespace.substr($prefixed, strlen($prefix) + 1);
    }

    public function iri(string $path): string
    {
        return rtrim(config('semantic.url'), '/').'/'.ltrim($path, '/');
    }

    public function carIri(string $slug): string
    {
        return $this->iri('mobil/'.$slug);
    }

    public function offerIri(string $slug): string
    {
        return $this->iri('mobil/'.$slug).'#penawaran';
    }

    public function priceSpecificationIri(string $slug): string
    {
        return $this->iri('mobil/'.$slug).'#skema-harga';
    }

    public function odometerIri(string $slug): string
    {
        return $this->iri('mobil/'.$slug).'#odometer';
    }

    public function brandIri(string $brand): string
    {
        return $this->iri('merek/'.Str::slug($brand) ?: 'lainnya');
    }

    public function placeIri(string $city): string
    {
        return $this->iri('lokasi/'.Str::slug($city) ?: 'lainnya');
    }

    public function sellerIri(int $id): string
    {
        return $this->iri('penjual/'.$id);
    }

    public function siteIri(): string
    {
        return rtrim(config('semantic.url'), '/').'/';
    }

    public function statisticsIri(): string
    {
        return $this->iri('statistik');
    }

    public function absoluteUrl(string $path): string
    {
        return rtrim(config('app.url'), '/').'/'.ltrim($path, '/');
    }
}
