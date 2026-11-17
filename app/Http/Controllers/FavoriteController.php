<?php

namespace App\Http\Controllers;

use App\Services\Catalog\CarCatalog;
use App\Services\Catalog\CarListing;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Favourites live in the session instead of a table, so the app no longer needs
 * PostgreSQL for browsing. The saved ids are resolved against the live Fuseki
 * catalogue on every visit.
 */
class FavoriteController extends Controller
{
    public function index(Request $request, CarCatalog $catalog): View
    {
        $ids = $this->ids($request);

        $cars = collect($ids)
            ->map(fn (string $id): ?CarListing => $catalog->find($id))
            ->filter()
            ->values();

        return view('favorites.index', [
            'cars' => $cars,
        ]);
    }

    public function store(Request $request, string $car, CarCatalog $catalog): RedirectResponse
    {
        $listing = $catalog->find($car);

        abort_if($listing === null, 404);

        $ids = $this->ids($request);

        if (! in_array($listing->id, $ids, true)) {
            $ids[] = $listing->id;
        }

        $request->session()->put('favorit', $ids);

        return back()->with('status', $listing->label.' disimpan ke favorit.');
    }

    public function destroy(Request $request, string $car, CarCatalog $catalog): RedirectResponse
    {
        $listing = $catalog->find($car);

        abort_if($listing === null, 404);

        $ids = array_values(array_filter(
            $this->ids($request),
            fn (string $id): bool => $id !== $listing->id,
        ));

        $request->session()->put('favorit', $ids);

        return back()->with('status', $listing->label.' dihapus dari favorit.');
    }

    /**
     * @return list<string>
     */
    private function ids(Request $request): array
    {
        /** @var Collection<int, string> $ids */
        $ids = collect($request->session()->get('favorit', []))
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->values();

        return $ids->all();
    }
}
