<?php

namespace App\Services\Semantic;

use App\Enums\CarStatus;
use App\Models\Car;
use App\Models\Triple;

/**
 * Writes the RDF statements that describe a listing.
 *
 * Each listing is published twice over: once as a schema:Car describing what the
 * vehicle is, and once as a schema:Offer describing the commercial terms. That
 * split is what lets a crawler reason about price and availability without
 * re-reading the page, and it is the same shape a dealer feed would use.
 */
class CarGraphBuilder
{
    public function __construct(private readonly Vocabulary $vocabulary) {}

    /**
     * Write a listing's statements, or withdraw them when the listing is a draft.
     *
     * Drafts are private to their owner, so they are kept out of the public graph
     * entirely rather than being published and later filtered. Routing every
     * write through here is what guarantees no caller forgets that rule.
     */
    public function publish(Car $car): void
    {
        if ($car->status === CarStatus::Draft) {
            $this->remove($car);

            return;
        }

        $this->sync($car);
    }

    /**
     * Every node this listing owns, including the blank nodes it anchors.
     * Deleting all of them together is what keeps a re-sync from leaving
     * orphaned statements behind.
     *
     * @return list<string>
     */
    private function subjectsFor(Car $car): array
    {
        $subjects = [
            $car->uri(),
            $this->vocabulary->offerIri($car->slug),
            $this->vocabulary->priceSpecificationIri($car->slug),
        ];

        if ($car->mileageKm !== null) {
            $subjects[] = $this->vocabulary->odometerIri($car->slug);
        }

        return $subjects;
    }

    /**
     * Replace every statement about a car with the current state of the model.
     */
    public function sync(Car $car): void
    {
        Triple::whereIn('subject', $this->subjectsFor($car))->delete();

        $statements = $this->statementsFor($car);

        $rows = [];

        foreach ($statements as [$subject, $predicate, $object, $isIri, $datatype]) {
            $rows[] = [
                'subject' => $subject,
                'predicate' => $predicate,
                'object' => $object,
                'object_is_iri' => $isIri,
                'datatype' => $datatype,
                'graph' => 'catalog',
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            Triple::insert($chunk);
        }
    }

    public function remove(Car $car): void
    {
        Triple::whereIn('subject', $this->subjectsFor($car))->delete();
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: bool, 4: ?string}>
     */
    public function statementsFor(Car $car): array
    {
        $vocabulary = $this->vocabulary;

        $carIri = $car->uri();
        $offerIri = $vocabulary->offerIri($car->slug);
        $brandIri = $vocabulary->brandIri($car->brand);
        $placeIri = $vocabulary->placeIri($car->city);
        $priceSpecificationIri = $vocabulary->priceSpecificationIri($car->slug);

        $statements = [
            $this->iri($carIri, Vocabulary::RDF_TYPE, Vocabulary::CAR),
            $this->iri($carIri, 'rdf:type', Vocabulary::LISTING),
            $this->literal($carIri, 'schema:name', $car->display_name),
            $this->literal($carIri, 'schema:url', $car->offerUri()),
            $this->iri($carIri, 'schema:brand', $brandIri),
            $this->literal($carIri, 'schema:model', $car->model),
            $this->literal($carIri, 'schema:productionDate', (string) $car->year, Vocabulary::XSD_G_YEAR),
            $this->iri($carIri, 'schema:itemCondition', $car->condition->schemaUrl()),
            $this->iri($carIri, 'schema:offers', $offerIri),
            $this->iri($carIri, 'schema:availableAtOrFrom', $placeIri),
            $this->literal($carIri, 'schema:address', $car->location),
        ];

        if ($car->variant !== null) {
            $statements[] = $this->literal($carIri, 'dcterms:hasPart', $car->variant);
        }

        if ($car->transmission !== null) {
            $statements[] = $this->literal($carIri, 'schema:vehicleTransmission', $car->transmission->value);
        }

        if ($car->drivetrain !== null) {
            $statements[] = $this->iri($carIri, 'schema:driveWheelConfiguration', $car->drivetrain->schemaUrl());
        }

        if ($car->fuel !== null) {
            $statements[] = $this->literal($carIri, 'schema:fuelType', $car->fuel->value);
        }

        if ($car->color !== null) {
            $statements[] = $this->literal($carIri, 'schema:color', $car->color);
        }

        if ($car->body_type !== null) {
            $statements[] = $this->literal($carIri, 'schema:bodyType', $car->body_type);
        }

        $statements = array_values(array_filter($statements));

        foreach ($car->images as $image) {
            $statements[] = $this->iri($carIri, 'schema:image', $image->url());
        }

        $statements = array_merge($statements, $this->mileageStatements($car, $carIri));

        if ($car->description !== null) {
            $statements[] = $this->literal($carIri, 'schema:description', $car->description);
        }

        $statements = array_merge($statements, $this->offerStatements($car, $offerIri, $priceSpecificationIri));

        return array_values(array_filter($statements));
    }

    /**
     * @return list<array{0: string, 1: string, 2: string, 3: bool, 4: ?string}>
     */
    private function offerStatements(Car $car, string $offerIri, string $priceSpecificationIri): array
    {
        $statements = [
            $this->iri($offerIri, Vocabulary::RDF_TYPE, Vocabulary::OFFER),
            $this->literal($offerIri, 'schema:price', (string) $car->price, Vocabulary::XSD_INTEGER),
            $this->literal($offerIri, 'schema:priceCurrency', (string) config('semantic.currency')),
            $this->iri($offerIri, 'schema:availability', $car->status->schemaUrl()),
            $this->literal($offerIri, 'schema:url', $car->offerUri()),
            $this->literal($offerIri, 'schema:datePosted', $car->posted_at->toIso8601String(), Vocabulary::XSD_DATE_TIME),
            $this->literal($offerIri, 'schema:priceValidUntil', $car->posted_at->clone()->addDays(30)->toDateString(), Vocabulary::XSD_DATE),
            $this->iri($offerIri, 'schema:itemCondition', $car->condition->schemaUrl()),
        ];

        if ($car->discount_amount !== null) {
            $statements[] = $this->literal($offerIri, Vocabulary::DISCOUNT_AMOUNT, (string) $car->discount_amount, Vocabulary::XSD_INTEGER);
        }

        foreach ([
            Vocabulary::VERIFIED_SELLER => $car->is_verified,
            Vocabulary::WARRANTY => $car->has_warranty,
            Vocabulary::BOOKING_IS_SAFE => $car->booking_is_safe,
            Vocabulary::HOME_TEST_DRIVE => $car->home_test_drive,
            Vocabulary::FREE_MAINTENANCE => $car->free_maintenance,
            Vocabulary::FREE_INSURANCE => $car->free_insurance,
        ] as $predicate => $flag) {
            $statements[] = $this->literal($offerIri, $predicate, $flag ? 'true' : 'false', Vocabulary::XSD_BOOLEAN);
        }

        if ($car->seller !== null) {
            $statements[] = $this->iri($offerIri, 'schema:seller', $this->vocabulary->sellerIri($car->seller->id));
        }

        $hasFinancing = $car->installment_monthly !== null;

        if ($hasFinancing) {
            $statements[] = $this->iri($offerIri, 'schema:priceSpecification', $priceSpecificationIri);
            $statements[] = $this->iri($priceSpecificationIri, Vocabulary::RDF_TYPE, Vocabulary::UNIT_PRICE_SPECIFICATION);
            $statements[] = $this->literal(
                $priceSpecificationIri,
                'schema:installmentAmount',
                (string) $car->installment_monthly,
                Vocabulary::XSD_INTEGER,
            );

            if ($car->installment_term !== null) {
                $statements[] = $this->literal(
                    $priceSpecificationIri,
                    'schema:installmentNumber',
                    (string) $car->installment_term,
                    Vocabulary::XSD_INTEGER,
                );
            }
        }

        if ($car->down_payment !== null) {
            $statements[] = $this->literal($offerIri, Vocabulary::DOWN_PAYMENT, (string) $car->down_payment, Vocabulary::XSD_INTEGER);
        }

        return array_values(array_filter($statements));
    }

    /**
     * schema.org models the odometer as a QuantitativeValue measured in
     * kilometres, so the range collapses to a single representative reading
     * that is published as its own node.
     *
     * @return list<array{0: string, 1: string, 2: string, 3: bool, 4: ?string}>
     */
    private function mileageStatements(Car $car, string $carIri): array
    {
        if ($car->mileageKm === null) {
            return [];
        }

        $valueIri = $this->vocabulary->odometerIri($car->slug);

        return [
            $this->iri($carIri, 'schema:mileageFromOdometer', $valueIri),
            $this->iri($valueIri, Vocabulary::RDF_TYPE, Vocabulary::QUANTITATIVE_VALUE),
            $this->literal($valueIri, 'schema:value', (string) $car->mileageKm, Vocabulary::XSD_INTEGER),
            $this->literal($valueIri, 'schema:unitCode', 'KMT'),
        ];
    }

    /**
     * Predicates and IRI objects are stored as absolute IRIs rather than the
     * prefixed names the builders are written in, because that is what makes a
     * SPARQL query written against schema.org match. The Turtle serializer
     * abbreviates them again for readers.
     *
     * @return array{0: string, 1: string, 2: string, 3: bool, 4: ?string}
     */
    private function iri(string $subject, string $predicate, string $object): array
    {
        return [$subject, $this->vocabulary->expand($predicate), $this->vocabulary->expand($object), true, null];
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: bool, 4: ?string}
     */
    private function literal(string $subject, string $predicate, string $object, string $datatype = Vocabulary::XSD_STRING): array
    {
        return [$subject, $this->vocabulary->expand($predicate), $object, false, $this->vocabulary->datatypeFor($datatype)];
    }
}
