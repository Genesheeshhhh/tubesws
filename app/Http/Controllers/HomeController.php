<?php

namespace App\Http\Controllers;

use App\Services\Catalog\CarCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;

class HomeController extends Controller
{
    public function index(CarCatalog $catalog): View
    {
        $cars = $catalog->all();

        $latest = $cars->sortByDesc('postedAt')->take(8)->values();

        $featured = $cars
            ->filter(fn ($car): bool => filled($car->imageUrl) && $car->hasFinancing())
            ->sortByDesc('postedAt')
            ->take(4)
            ->values();

        $financing = $cars
            ->filter(fn ($car): bool => $car->hasFinancing())
            ->sortByDesc('postedAt')
            ->take(4)
            ->values();

        return view('home', [
            'latest' => $latest,
            'featured' => $featured,
            'financing' => $financing,
            'statistics' => $catalog->statistics(),
            'brandFacets' => $catalog->brandFacets(12),
            'locationFacets' => $catalog->locationFacets(10),
            'jsonLd' => [$this->websiteGraph()],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function websiteGraph(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => config('semantic.name'),
            'url' => url('/'),
            'description' => config('semantic.description'),
            'inLanguage' => 'id-ID',
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => route('mobil.index').'?q={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => config('semantic.name'),
                'url' => url('/'),
                'description' => Str::limit((string) config('semantic.description'), 160),
            ],
        ];
    }
}
