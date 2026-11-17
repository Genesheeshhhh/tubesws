<?php

namespace App\Http\Controllers;

use App\Services\Catalog\CarCatalog;
use App\Services\Catalog\CarListing;
use App\Services\Semantic\OntologyCatalogue;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CarController extends Controller
{
    public function index(Request $request, CarCatalog $catalog): View
    {
        $filters = $catalog->filtersFrom($request->query());
        $search = $catalog->resolveSearch($filters['q']);

        if ($search['corrected'] && $search['resolved'] !== null) {
            $filters['q'] = $search['resolved'];
        }

        $cars = $catalog->paginate($filters);
        $facets = $catalog->facets($filters);

        return view('cars.index', [
            'cars' => $cars,
            'facets' => $facets,
            'filters' => $filters,
            'search' => $search,
            'jsonLd' => [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Beranda', 'item' => route('beranda')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Katalog mobil', 'item' => route('mobil.index')],
                    ],
                ],
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'ItemList',
                    'name' => 'Katalog mobil bekas Medan',
                    'numberOfItems' => $cars->total(),
                    'itemListElement' => $cars->getCollection()->values()->map(fn (CarListing $car, int $index): array => [
                        '@type' => 'ListItem',
                        'position' => $cars->firstItem() + $index,
                        'url' => route('mobil.show', ['car' => $car->id]),
                        'name' => $car->label,
                    ])->all(),
                ],
            ],
        ]);
    }

    public function show(string $car, CarCatalog $catalog): View
    {
        $listing = $catalog->find($car);

        abort_if($listing === null, 404);

        return view('cars.show', [
            'car' => $listing,
            'related' => $catalog->related($listing),
            'jsonLd' => [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Beranda', 'item' => route('beranda')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Katalog mobil', 'item' => route('mobil.index')],
                        ['@type' => 'ListItem', 'position' => 3, 'name' => $listing->label, 'item' => route('mobil.show', ['car' => $listing->id])],
                    ],
                ],
                $listing->toJsonLd(),
            ],
        ]);
    }

    public function turtle(string $car, CarCatalog $catalog, OntologyCatalogue $ontology): Response
    {
        $listing = $catalog->find($car);

        abort_if($listing === null, 404);

        return response($ontology->subjectTurtle($listing->uri), 200, [
            'Content-Type' => 'text/turtle; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.$listing->id.'.ttl"',
        ]);
    }
}
