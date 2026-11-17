<?php

namespace App\Http\Controllers;

use App\Enums\CarStatus;
use App\Models\Car;
use App\Services\Semantic\CarGraphBuilder;
use App\Services\Semantic\SiteGraphBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Moving a listing between available, sold and draft changes what a buyer can
 * discover, so every transition rewrites the graph rather than only the row.
 */
class ListingStatusController extends Controller
{
    public function update(Request $request, Car $car, CarGraphBuilder $graph, SiteGraphBuilder $site): RedirectResponse
    {
        $this->authorize('update', $car);

        $validated = $request->validate([
            'status' => ['required', Rule::in(CarStatus::options())],
        ], [
            'status.in' => 'Status iklan tidak dikenali.',
        ]);

        $status = CarStatus::from($validated['status']);

        $car->update([
            'status' => $status,
            'sold_at' => $status === CarStatus::Sold ? now() : null,
        ]);

        $graph->publish($car->refresh()->load(['images', 'seller']));
        $site->syncAll();

        return back()->with('status', 'Status iklan diubah menjadi '.$status->label().'.');
    }
}
