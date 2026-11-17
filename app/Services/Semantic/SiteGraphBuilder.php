<?php

namespace App\Services\Semantic;

use App\Models\Car;
use App\Models\Triple;
use App\Models\User;

/**
 * Maintains the statements that describe the site itself rather than any single
 * listing: the publisher, the brand vocabulary, the places, the sellers, the
 * catalogue statistics, and the local ontology terms.
 */
class SiteGraphBuilder
{
    public function __construct(private readonly Vocabulary $vocabulary) {}

    public function syncAll(): void
    {
        $this->syncSite();
        $this->syncBrands();
        $this->syncPlaces();
        $this->syncSellers();
        $this->syncStatistics();
        $this->syncOntology();
    }

    public function syncSite(): void
    {
        $site = $this->vocabulary->siteIri();
        $action = $site.'#pencarian';

        $this->replaceGraph('site', [
            $this->iri($site, Vocabulary::RDF_TYPE, Vocabulary::ORGANIZATION),
            $this->iri($site, Vocabulary::RDF_TYPE, 'schema:WebSite'),
            $this->literal($site, 'schema:name', (string) config('semantic.name')),
            $this->literal($site, 'schema:url', $site),
            $this->literal($site, 'schema:description', (string) config('semantic.description')),
            $this->literal($site, 'schema:inLanguage', (string) config('semantic.language')),
            $this->iri($site, 'schema:potentialAction', $action),
            $this->iri($action, Vocabulary::RDF_TYPE, 'schema:SearchAction'),
            $this->literal($action, 'schema:target', $this->vocabulary->absoluteUrl('mobil').'?q={search_term_string}'),
            $this->literal($action, 'schema:query-input', 'required name=search_term_string'),
        ]);
    }

    public function syncBrands(): void
    {
        $used = Car::query()->distinct()->pluck('brand');

        foreach ($used as $brand) {
            $iri = $this->vocabulary->brandIri($brand);

            $this->replaceSubject($iri, [
                $this->iri($iri, Vocabulary::RDF_TYPE, Vocabulary::BRAND),
                $this->literal($iri, 'schema:name', (string) $brand),
                $this->literal($iri, 'rdfs:label', (string) $brand),
            ]);
        }
    }

    public function syncPlaces(): void
    {
        $cities = Car::query()->distinct()->pluck('city');

        foreach ($cities as $city) {
            $iri = $this->vocabulary->placeIri((string) $city);

            $this->replaceSubject($iri, [
                $this->iri($iri, Vocabulary::RDF_TYPE, Vocabulary::PLACE),
                $this->literal($iri, 'schema:name', (string) $city),
                $this->literal($iri, 'schema:address', (string) $city),
            ]);
        }
    }

    public function syncSellers(): void
    {
        $sellers = User::query()->whereHas('listings')->get();

        foreach ($sellers as $seller) {
            $iri = $this->vocabulary->sellerIri($seller->id);

            $this->replaceSubject($iri, [
                $this->iri($iri, Vocabulary::RDF_TYPE, Vocabulary::ORGANIZATION),
                $this->literal($iri, 'schema:name', $seller->name),
                $this->literal($iri, 'schema:url', $this->vocabulary->absoluteUrl('penjual/'.$seller->id)),
                $this->literal($iri, 'app:verifiedSeller', $seller->listings()->where('is_verified', true)->exists() ? 'true' : 'false', Vocabulary::XSD_BOOLEAN),
            ]);
        }
    }

    public function syncStatistics(): void
    {
        $stats = Car::query()->visibleToPublic();

        $iri = $this->vocabulary->statisticsIri();

        $this->replaceSubject($iri, [
            $this->iri($iri, Vocabulary::RDF_TYPE, 'app:CatalogueStatistics'),
            $this->literal($iri, Vocabulary::TOTAL_LISTINGS, (string) (clone $stats)->count(), Vocabulary::XSD_INTEGER),
            $this->literal($iri, 'app:availableListings', (string) (clone $stats)->available()->count(), Vocabulary::XSD_INTEGER),
            $this->literal($iri, Vocabulary::LOWEST_PRICE, (string) ((clone $stats)->min('price') ?? 0), Vocabulary::XSD_INTEGER),
            $this->literal($iri, Vocabulary::HIGHEST_PRICE, (string) ((clone $stats)->max('price') ?? 0), Vocabulary::XSD_INTEGER),
            $this->literal($iri, 'app:distinctBrands', (string) (clone $stats)->distinct()->count('brand'), Vocabulary::XSD_INTEGER),
            $this->literal($iri, 'app:tripleCount', (string) Triple::query()->count(), Vocabulary::XSD_INTEGER),
        ]);
    }

    /**
     * Documents the local namespace, which is what makes the graph an
     * extension of schema.org rather than an undocumented private vocabulary.
     */
    public function syncOntology(): void
    {
        $namespace = config('semantic.namespaces.app');

        $classes = [
            'CarListing' => ['A vehicle offered for sale on this marketplace, alongside the schema:Car it describes.', 'schema:Product'],
            'CatalogueStatistics' => ['Aggregate measurements over the public catalogue.', null],
        ];

        $properties = [
            'downPayment' => ['The amount paid up front in a financed purchase.', 'schema:priceSpecification'],
            'discountAmount' => ['A price reduction advertised on the offer.', null],
            'bookingIsSafe' => ['The listing states that the reservation process is guaranteed.', null],
            'homeTestDrive' => ['The seller offers a test drive delivered to the buyer address.', null],
            'freeMaintenance' => ['The listing includes complimentary servicing.', null],
            'freeInsurance' => ['The listing includes complimentary insurance.', null],
            'verifiedSeller' => ['The seller identity has been checked by the marketplace.', null],
            'hasWarranty' => ['The vehicle is offered with a warranty.', null],
            'sourceReference' => ['The identifier this listing was imported under.', null],
            'totalListings' => ['The number of listings in the public catalogue.', null],
            'availableListings' => ['The number of listings still available in the public catalogue.', null],
            'distinctBrands' => ['The number of distinct brands in the public catalogue.', null],
            'tripleCount' => ['The number of statements the catalogue currently publishes.', null],
            'lowestPrice' => ['The lowest advertised price in the catalogue.', null],
            'highestPrice' => ['The highest advertised price in the catalogue.', null],
        ];

        $statements = [
            $this->iri($namespace, Vocabulary::RDF_TYPE, 'owl:Ontology'),
            $this->literal($namespace, 'rdfs:label', 'Vocabulary marketplace mobil bekas'),
            $this->literal($namespace, 'rdfs:comment', 'Istilah lokal yang melengkapi schema.org untuk kebutuhan jual beli mobil bekas.'),
        ];

        foreach ($classes as $term => [$label, $superClass]) {
            $iri = $namespace.$term;

            $statements[] = $this->iri($iri, Vocabulary::RDF_TYPE, 'owl:Class');
            $statements[] = $this->literal($iri, 'rdfs:label', $label);
            $statements[] = $this->literal($iri, 'rdfs:comment', $label);
            $statements[] = $this->iri($iri, 'rdfs:isDefinedBy', $namespace);

            if ($superClass !== null) {
                $statements[] = $this->iri($iri, 'rdfs:subClassOf', $superClass);
            }
        }

        foreach ($properties as $term => [$label, $superProperty]) {
            $iri = $namespace.$term;

            $statements[] = $this->iri($iri, Vocabulary::RDF_TYPE, 'owl:DatatypeProperty');
            $statements[] = $this->literal($iri, 'rdfs:label', $label);
            $statements[] = $this->literal($iri, 'rdfs:comment', $label);
            $statements[] = $this->iri($iri, 'rdfs:isDefinedBy', $namespace);

            if ($superProperty !== null) {
                $statements[] = $this->iri($iri, 'rdfs:subPropertyOf', $superProperty);
            }
        }

        $this->replaceGraph('ontology', $statements);
    }

    /**
     * The classes actually asserted in the graph, with the number of instances
     * of each, so the ontology page reflects the data rather than a wish list.
     *
     * @return list<array{class: string, instances: int, local: bool}>
     */
    public function classCatalogue(): array
    {
        // Objects are stored as absolute IRIs, so locality has to be decided
        // against the namespace IRIs rather than against prefix names.
        $namespaces = array_values(config('semantic.namespaces'));

        return Triple::query()
            ->where('predicate', Vocabulary::RDF_TYPE)
            ->where('object_is_iri', true)
            ->selectRaw('object as class, COUNT(DISTINCT subject) as instances')
            ->groupBy('object')
            ->orderByDesc('instances')
            ->get()
            ->map(fn (Triple $row): array => [
                'class' => (string) $row->object,
                'instances' => (int) $row->instances,
                'local' => (bool) array_filter(
                    $namespaces,
                    fn (string $namespace): bool => str_starts_with((string) $row->object, $namespace),
                ),
            ])
            ->all();
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string, 3: bool, 4: ?string}>  $statements
     */
    private function replaceSubject(string $subject, array $statements, string $graph = 'site'): void
    {
        Triple::where('subject', $subject)->delete();

        $this->insert($statements, $graph);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string, 3: bool, 4: ?string}>  $statements
     */
    private function replaceGraph(string $graph, array $statements): void
    {
        Triple::where('graph', $graph)->delete();

        $this->insert($statements, $graph);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string, 3: bool, 4: ?string}>  $statements
     */
    private function insert(array $statements, string $graph): void
    {
        $rows = array_map(
            fn (array $statement): array => [
                'subject' => $statement[0],
                'predicate' => $statement[1],
                'object' => $statement[2],
                'object_is_iri' => $statement[3],
                'datatype' => $statement[4],
                'graph' => $graph,
            ],
            $statements,
        );

        foreach (array_chunk($rows, 500) as $chunk) {
            Triple::insert($chunk);
        }
    }

    /**
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
