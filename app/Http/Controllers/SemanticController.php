<?php

namespace App\Http\Controllers;

use App\Services\Catalog\CarCatalog;
use App\Services\Catalog\CarListing;
use App\Services\Semantic\OntologyCatalogue;
use Illuminate\Contracts\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class SemanticController extends Controller
{
    public function index(CarCatalog $catalog, OntologyCatalogue $ontology): View
    {
        $sample = $catalog->all()
            ->filter(fn (CarListing $car): bool => filled($car->imageUrl))
            ->sortByDesc('postedAt')
            ->first();

        return view('semantic.index', [
            'statistics' => $ontology->statistics(),
            'classes' => $ontology->classes(),
            'predicates' => $ontology->predicates(),
            'namespaces' => $ontology->namespaces(),
            'sample' => $sample,
            'dataset' => [
                'name' => $ontology->datasetName(),
                'url' => $ontology->datasetUrl(),
                'query' => $ontology->datasetUrl().'/query',
                'update' => $ontology->datasetUrl().'/update',
                'available' => $ontology->isAvailable(),
            ],
            'catalogue' => $catalog->statistics(),
        ]);
    }

    public function show(string $car, CarCatalog $catalog, OntologyCatalogue $ontology): View
    {
        $listing = $catalog->find($car);

        abort_if($listing === null, 404);

        return view('semantic.show', [
            'car' => $listing,
            'statements' => $ontology->statementsFor($listing->uri),
        ]);
    }

    public function graph(OntologyCatalogue $ontology): Response
    {
        return response($ontology->graphTurtle(), 200, [
            'Content-Type' => 'text/turtle; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="katalog-mobil-medan.ttl"',
        ]);
    }

    public function ontology(OntologyCatalogue $ontology): View
    {
        return view('semantic.ontology', [
            'classes' => $ontology->classes(),
            'predicates' => $ontology->predicates(),
            'namespaces' => $ontology->namespaces(),
            'ontologyUrl' => $ontology->ontologyUrl(),
            'fileExists' => is_file($ontology->ontologyPath()),
            'fileName' => basename($ontology->ontologyPath()),
            'fileSize' => is_file($ontology->ontologyPath()) ? filesize($ontology->ontologyPath()) : 0,
            'datasetName' => $ontology->datasetName(),
            'datasetUrl' => $ontology->datasetUrl(),
        ]);
    }

    public function ontologyDownload(OntologyCatalogue $ontology): BinaryFileResponse
    {
        $path = $ontology->ontologyPath();

        abort_unless(is_file($path), 404);

        return response()->download($path, 'mobil-bekas-medan-owl.xml', [
            'Content-Type' => 'application/rdf+xml',
        ]);
    }
}
