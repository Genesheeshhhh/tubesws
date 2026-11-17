<?php

namespace Tests\Unit;

use App\Enums\CarCondition;
use App\Enums\CarStatus;
use App\Enums\Drivetrain;
use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Models\Car;
use App\Models\User;
use App\Services\Semantic\CarGraphBuilder;
use App\Services\Semantic\Vocabulary;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

/**
 * The graph shape is checked without touching the database: statementsFor() is
 * the pure part of publishing, and the write itself is covered by the feature
 * tests.
 */
class CarGraphBuilderTest extends TestCase
{
    private CarGraphBuilder $builder;

    private Vocabulary $vocabulary;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vocabulary = app(Vocabulary::class);
        $this->builder = new CarGraphBuilder($this->vocabulary);
    }

    private function expanded(string $prefixed): string
    {
        return $this->vocabulary->expand($prefixed);
    }

    public function test_a_listing_is_described_as_a_schema_car(): void
    {
        $statements = $this->statementsFor(['model' => 'Avanza']);

        $this->assertContains(
            [$this->vocabulary->carIri('avanza-1'), $this->expanded(Vocabulary::RDF_TYPE), $this->expanded(Vocabulary::CAR), true, null],
            $statements,
        );
    }

    public function test_the_offer_is_a_separate_node_the_car_points_at(): void
    {
        $statements = $this->statementsFor();

        $this->assertContains(
            [$this->vocabulary->carIri('avanza-1'), $this->expanded('schema:offers'), $this->vocabulary->offerIri('avanza-1'), true, null],
            $statements,
        );

        $this->assertContains(
            [$this->vocabulary->offerIri('avanza-1'), $this->expanded(Vocabulary::RDF_TYPE), $this->expanded(Vocabulary::OFFER), true, null],
            $statements,
        );
    }

    public function test_predicates_and_iri_objects_are_stored_as_absolute_iris(): void
    {
        $statements = $this->statementsFor();

        foreach ($statements as [$subject, $predicate, , $isIri]) {
            $this->assertStringStartsWith('http', $predicate, 'Predicate '.$predicate.' is not absolute.');
            $this->assertIsBool($isIri);

            if ($isIri) {
                $this->assertStringStartsWith('http', $subject);
            }
        }
    }

    public function test_the_price_is_a_typed_integer_literal_on_the_offer(): void
    {
        $price = $this->statementsFor(['price' => 150_000_000]);

        $this->assertContains(
            [
                $this->vocabulary->offerIri('avanza-1'),
                $this->expanded('schema:price'),
                '150000000',
                false,
                'http://www.w3.org/2001/XMLSchema#integer',
            ],
            $price,
        );
    }

    public function test_the_seller_boolean_flags_are_always_published(): void
    {
        $statements = $this->statementsFor();
        $booleans = array_column($statements, 2, 1);

        foreach ([
            Vocabulary::VERIFIED_SELLER,
            Vocabulary::WARRANTY,
            Vocabulary::BOOKING_IS_SAFE,
            Vocabulary::HOME_TEST_DRIVE,
            Vocabulary::FREE_MAINTENANCE,
            Vocabulary::FREE_INSURANCE,
        ] as $predicate) {
            $this->assertSame('false', $booleans[$this->expanded($predicate)]);
        }
    }

    public function test_a_verified_listing_publishes_true_for_that_flag(): void
    {
        $booleans = array_column($this->statementsFor(['is_verified' => true]), 2, 1);

        $this->assertSame('true', $booleans[$this->expanded(Vocabulary::VERIFIED_SELLER)]);
    }

    public function test_financing_publishes_its_own_price_specification_node(): void
    {
        $statements = $this->statementsFor([
            'down_payment' => 20_000_000,
            'installment_monthly' => 3_500_000,
            'installment_term' => 36,
        ]);

        $priceSpecificationIri = $this->vocabulary->priceSpecificationIri('avanza-1');

        $this->assertContains(
            [$this->vocabulary->offerIri('avanza-1'), $this->expanded('schema:priceSpecification'), $priceSpecificationIri, true, null],
            $statements,
        );

        $this->assertContains(
            [$priceSpecificationIri, $this->expanded('schema:installmentAmount'), '3500000', false, 'http://www.w3.org/2001/XMLSchema#integer'],
            $statements,
        );
    }

    public function test_financing_fields_are_omitted_when_there_is_no_credit_offer(): void
    {
        $statements = $this->statementsFor();

        $this->assertNotContains($this->vocabulary->priceSpecificationIri('avanza-1'), array_column($statements, 0));
        $this->assertNotContains(Vocabulary::DOWN_PAYMENT, array_column($statements, 1));
    }

    public function test_the_odometer_is_published_as_a_quantitative_value_in_kilometres(): void
    {
        $statements = $this->statementsFor(['mileage_min' => 20_000, 'mileage_max' => 90_000]);
        $odometerIri = $this->vocabulary->odometerIri('avanza-1');

        $this->assertContains(
            [$odometerIri, $this->expanded(Vocabulary::RDF_TYPE), $this->expanded(Vocabulary::QUANTITATIVE_VALUE), true, null],
            $statements,
        );

        $this->assertContains([$odometerIri, $this->expanded('schema:value'), '55000', false, 'http://www.w3.org/2001/XMLSchema#integer'], $statements);
        $this->assertContains([$odometerIri, $this->expanded('schema:unitCode'), 'KMT', false, 'http://www.w3.org/2001/XMLSchema#string'], $statements);
    }

    public function test_a_listing_without_mileage_publishes_no_odometer(): void
    {
        $statements = $this->statementsFor(['mileage_min' => null, 'mileage_max' => null]);

        $this->assertNotContains($this->vocabulary->odometerIri('avanza-1'), array_column($statements, 0));
    }

    public function test_optional_attributes_are_omitted_rather_than_published_empty(): void
    {
        $statements = $this->statementsFor([
            'transmission' => null,
            'drivetrain' => null,
            'fuel' => null,
            'color' => null,
            'body_type' => null,
            'variant' => null,
            'description' => null,
        ]);

        $predicates = array_map(fn (string $predicate): string => $this->vocabulary->expand($predicate), array_column($statements, 1));

        $this->assertNotContains($this->vocabulary->expand('schema:vehicleTransmission'), $predicates);
        $this->assertNotContains($this->vocabulary->expand('schema:driveWheelConfiguration'), $predicates);
        $this->assertNotContains($this->vocabulary->expand('schema:fuelType'), $predicates);
        $this->assertNotContains($this->vocabulary->expand('schema:color'), $predicates);
        $this->assertNotContains($this->vocabulary->expand('schema:bodyType'), $predicates);
        $this->assertNotContains($this->vocabulary->expand('schema:description'), $predicates);
        $this->assertNotContains($this->vocabulary->expand('dcterms:hasPart'), $predicates);
    }

    public function test_optional_attributes_are_published_when_present(): void
    {
        $statements = $this->statementsFor([
            'transmission' => Transmission::Automatic->value,
            'drivetrain' => Drivetrain::AllWheelDrive->value,
            'fuel' => FuelType::Bensin->value,
            'color' => 'Hitam',
            'body_type' => 'MPV',
            'variant' => '1.3 G',
        ]);

        $objects = array_column($statements, 2, 1);

        $this->assertSame(Transmission::Automatic->value, $objects[$this->expanded('schema:vehicleTransmission')]);
        $this->assertSame(
            Drivetrain::AllWheelDrive->schemaUrl(),
            $objects[$this->expanded('schema:driveWheelConfiguration')],
        );
        $this->assertSame(FuelType::Bensin->value, $objects[$this->expanded('schema:fuelType')]);
        $this->assertSame('Hitam', $objects[$this->expanded('schema:color')]);
        $this->assertSame('MPV', $objects[$this->expanded('schema:bodyType')]);
        $this->assertSame('1.3 G', $objects[$this->expanded('dcterms:hasPart')]);
    }

    public function test_the_availability_iri_follows_the_listing_status(): void
    {
        $availability = function (CarStatus $status): string {
            $statements = $this->statementsFor(['status' => $status->value]);
            $objects = array_column($statements, 2, 1);

            return $objects[$this->expanded('schema:availability')];
        };

        $this->assertSame($this->vocabulary->expand(CarStatus::Available->schemaUrl()), $availability(CarStatus::Available));
        $this->assertSame($this->vocabulary->expand(CarStatus::Sold->schemaUrl()), $availability(CarStatus::Sold));
    }

    public function test_the_production_year_is_a_g_year_literal(): void
    {
        $statements = $this->statementsFor(['year' => 2015]);

        $this->assertContains(
            [$this->vocabulary->carIri('avanza-1'), $this->expanded('schema:productionDate'), '2015', false, 'http://www.w3.org/2001/XMLSchema#gYear'],
            $statements,
        );
    }

    public function test_the_price_validity_window_is_thirty_days_after_posting(): void
    {
        $statements = $this->statementsFor(['posted_at' => '2026-01-15 09:00:00']);
        $objects = array_column($statements, 2, 1);

        $this->assertSame('2026-02-14', $objects[$this->expanded('schema:priceValidUntil')]);
    }

    public function test_the_statement_list_has_no_empty_slots(): void
    {
        foreach ($this->statementsFor() as $index => $statement) {
            $this->assertCount(5, $statement, 'Statement '.$index.' is malformed.');
            $this->assertNotSame('', (string) $statement[1]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<array{0: string, 1: string, 2: string, 3: bool, 4: ?string}>
     */
    private function statementsFor(array $attributes = []): array
    {
        $car = new Car(array_merge([
            'slug' => 'avanza-1',
            'brand' => 'Toyota',
            'model' => 'Avanza',
            'variant' => null,
            'year' => 2015,
            'price' => 150_000_000,
            'mileage_min' => 20_000,
            'mileage_max' => 90_000,
            'transmission' => null,
            'fuel' => null,
            'condition' => CarCondition::Used->value,
            'color' => null,
            'body_type' => null,
            'location' => 'Jl. Merdeka 1',
            'description' => null,
            'status' => CarStatus::Available->value,
            'posted_at' => '2026-01-15 09:00:00',
            'city' => 'Bandung',
            'is_verified' => false,
            'has_warranty' => false,
            'booking_is_safe' => false,
            'home_test_drive' => false,
            'free_maintenance' => false,
            'free_insurance' => false,
            'down_payment' => null,
            'installment_monthly' => null,
            'installment_term' => null,
            'discount_amount' => null,
        ], $attributes));

        $seller = new User(['name' => 'Toko Motor']);
        $seller->id = 7;

        $car->id = 1;
        $car->setRelation('seller', $seller);
        $car->setRelation('images', new Collection);

        return $this->builder->statementsFor($car);
    }
}
