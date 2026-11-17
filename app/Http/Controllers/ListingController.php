<?php

namespace App\Http\Controllers;

use App\Enums\CarCondition;
use App\Enums\CarStatus;
use App\Http\Requests\StoreListingRequest;
use App\Http\Requests\UpdateListingRequest;
use App\Models\Car;
use App\Models\CarInquiry;
use App\Models\User;
use App\Services\Catalog\ListingPhotoStore;
use App\Services\Semantic\CarGraphBuilder;
use App\Services\Semantic\SiteGraphBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ListingController extends Controller
{
    public function index(Request $request): View
    {
        $requested = CarStatus::tryFrom((string) $request->query('status', 'all'));

        $query = Car::query()
            ->whereBelongsTo($request->user(), 'seller')
            ->with('images')
            ->withCount('inquiries')
            ->when($requested, fn ($builder) => $builder->where('status', $requested->value))
            ->latest('posted_at');

        $counts = $this->statusCounts($request->user());

        return view('listings.index', [
            'cars' => $query->paginate(15)->withQueryString(),
            'statusFilter' => $requested?->value ?? 'all',
            'statusTabs' => $this->statusTabs($counts),
            'total' => array_sum($counts),
            'summary' => $this->summaryFor($request->user()),
        ]);
    }

    public function create(Request $request): View
    {
        return view('listings.create', [
            'car' => new Car(['condition' => CarCondition::Used, 'status' => CarStatus::Available]),
            'cities' => $this->cities(),
        ]);
    }

    public function store(StoreListingRequest $request, ListingPhotoStore $photos, CarGraphBuilder $graph, SiteGraphBuilder $site): RedirectResponse
    {
        $car = DB::transaction(function () use ($request, $photos, $graph): Car {
            $car = $request->user()->listings()->create([
                ...$request->listingAttributes(),
                'slug' => $this->uniqueSlug($request),
                'source' => 'manual',
                'posted_at' => now(),
            ]);

            $photos->store($car, $request->file('photos', []));
            $photos->ensureArtwork($car);

            $graph->publish($car->refresh()->load(['images', 'seller']));

            return $car;
        });

        $site->syncAll();

        return redirect()
            ->route('mobil.show', $car)
            ->with('status', 'Iklan Anda sudah tayang. Data structured data juga sudah diperbarui.');
    }

    public function edit(Request $request, Car $car): View
    {
        $this->authorize('update', $car);

        return view('listings.edit', [
            'car' => $car,
            'cities' => $this->cities(),
        ]);
    }

    public function update(UpdateListingRequest $request, Car $car, ListingPhotoStore $photos, CarGraphBuilder $graph, SiteGraphBuilder $site): RedirectResponse
    {
        $attributes = $request->listingAttributes();

        // Renaming the car or changing its year changes the public identity, so a
        // new slug is issued and the graph is rewritten against it.
        $renamed = $attributes['brand'] !== $car->brand
            || $attributes['model'] !== $car->model
            || (int) $attributes['year'] !== $car->year;

        if ($renamed) {
            $attributes['slug'] = $this->uniqueSlug($request);
        }

        DB::transaction(function () use ($request, $car, $attributes, $photos, $graph, $renamed): void {
            if ($renamed) {
                // Triples are keyed by the old identity, so they have to be
                // removed before the slug on the model changes.
                $graph->remove($car);
            }

            $car->update($attributes);

            $photos->store($car, $request->file('photos', []));
            $photos->ensureArtwork($car);

            $graph->publish($car->refresh()->load(['images', 'seller']));
        });

        $site->syncAll();

        return redirect()
            ->route('mobil.show', $car)
            ->with('status', 'Perubahan iklan sudah disimpan.');
    }

    public function destroy(Car $car, ListingPhotoStore $photos, CarGraphBuilder $graph, SiteGraphBuilder $site): RedirectResponse
    {
        $this->authorize('delete', $car);

        $slug = $car->slug;

        DB::transaction(function () use ($car, $photos, $graph): void {
            $graph->remove($car);

            foreach ($car->images as $image) {
                $photos->delete($image);
            }

            $car->favoritedBy()->detach();
            $car->delete();
        });

        $site->syncAll();

        return redirect()
            ->route('iklan.saya')
            ->with('status', 'Iklan '.$slug.' sudah dihapus beserta data semantic-nya.');
    }

    /**
     * Number of the seller's listings per status, plus an "all" bucket.
     *
     * @return array<string, int>
     */
    private function statusCounts(User $user): array
    {
        $counts = ['all' => 0];

        $tallies = Car::query()
            ->whereBelongsTo($user, 'seller')
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        foreach (CarStatus::cases() as $status) {
            $counts[$status->value] = (int) $tallies->get($status->value, 0);
            $counts['all'] += $counts[$status->value];
        }

        return $counts;
    }

    /**
     * Status filter tabs, ordered with "all" first.
     *
     * @param  array<string, int>  $counts
     * @return list<array{value: string, label: string, count: int}>
     */
    private function statusTabs(array $counts): array
    {
        $tabs = [['value' => 'all', 'label' => 'Semua', 'count' => $counts['all']]];

        foreach (CarStatus::cases() as $status) {
            $tabs[] = ['value' => $status->value, 'label' => $status->label(), 'count' => $counts[$status->value]];
        }

        return $tabs;
    }

    /**
     * @return array{active: int, sold: int, drafts: int, views: int, inquiries: int}
     */
    private function summaryFor(User $user): array
    {
        $base = Car::query()->whereBelongsTo($user, 'seller');

        return [
            'active' => (clone $base)->where('status', CarStatus::Available->value)->count(),
            'sold' => (clone $base)->where('status', CarStatus::Sold->value)->count(),
            'drafts' => (clone $base)->where('status', CarStatus::Draft->value)->count(),
            'views' => (int) (clone $base)->sum('view_count'),
            'inquiries' => CarInquiry::query()
                ->whereIn('car_id', $base->clone()->select('cars.id'))
                ->count(),
        ];
    }

    /**
     * @return list<string>
     */
    private function cities(): array
    {
        return Car::query()
            ->visibleToPublic()
            ->whereNotNull('city')
            ->distinct()
            ->orderBy('city')
            ->pluck('city')
            ->all();
    }

    private function uniqueSlug(StoreListingRequest|UpdateListingRequest $request): string
    {
        $base = Str::slug($request->string('brand')->append('-')->append($request->string('model'))->toString());

        $slug = $base === '' ? 'mobil' : $base;
        $candidate = $slug;
        $suffix = 1;

        while (Car::where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.(++$suffix);
        }

        return $candidate;
    }
}
