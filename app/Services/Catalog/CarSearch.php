<?php

namespace App\Services\Catalog;

use App\Enums\CarBrand;
use App\Enums\Drivetrain;
use App\Enums\FuelType;
use App\Enums\Transmission;
use App\Models\Car;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Turns query-string input into a catalogue query plus the facet counts that
 * describe the current result set.
 *
 * Facets are counted against the filtered set so the counts a buyer sees
 * describe what they would actually get, and the selected values for a facet
 * are excluded from its own counts so a single value can still be deselected.
 */
class CarSearch
{
    /**
     * @return array<string, mixed>
     */
    public function filtersFrom(array $input): array
    {
        return [
            'q' => $this->stringOrNull($input['q'] ?? null),
            'brand' => $this->stringList($input['brand'] ?? null),
            'city' => $this->stringList($input['city'] ?? null),
            'transmission' => $this->allowedList($input['transmission'] ?? null, Transmission::options()),
            'drivetrain' => $this->allowedList($input['drivetrain'] ?? null, \App\Enums\Drivetrain::options()),
            'fuel' => $this->allowedList($input['fuel'] ?? null, FuelType::options()),
            'min_price' => $this->intOrNull($input['min_price'] ?? null),
            'max_price' => $this->intOrNull($input['max_price'] ?? null),
            'min_year' => $this->intOrNull($input['min_year'] ?? null),
            'max_mileage' => $this->intOrNull($input['max_mileage'] ?? null),
            'verified_only' => filter_var($input['verified_only'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'with_financing' => filter_var($input['with_financing'] ?? false, FILTER_VALIDATE_BOOLEAN),
            'sort' => $this->sort($input['sort'] ?? null),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function query(array $filters): Builder
    {
        return Car::query()
            ->visibleToPublic()
            ->filtered($filters)
            ->with(['images', 'seller'])
            ->withExists(['favoritedBy as favorited_by_me' => fn ($query) => $query->where('user_id', auth()->id())])
            ->tap(fn (Builder $query) => $this->applySort($query, $filters['sort'] ?? 'terbaru'));
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function paginate(array $filters, int $perPage = 24): LengthAwarePaginator
    {
        return $this->query($filters)->paginate($perPage)->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function facets(array $filters): array
    {
        return [
            'brand' => $this->facetCounts(
                Car::query()->visibleToPublic()->filtered($filters + ['brand' => []])->reorder(),
                'brand',
                $filters['brand'] ?? [],
                14,
            ),
            'city' => $this->facetCounts(
                Car::query()->visibleToPublic()->filtered($filters + ['city' => []])->reorder(),
                'city',
                $filters['city'] ?? [],
                12,
            ),
            'transmission' => $this->facetCounts(
                Car::query()->visibleToPublic()->whereNotNull('transmission')->filtered($filters + ['transmission' => []])->reorder(),
                'transmission',
                $filters['transmission'] ?? [],
                10,
            ),
            'drivetrain' => $this->facetCounts(
                Car::query()->visibleToPublic()->whereNotNull('drivetrain')->filtered($filters + ['drivetrain' => []])->reorder(),
                'drivetrain',
                $filters['drivetrain'] ?? [],
                10,
            ),
            'fuel' => $this->facetCounts(
                Car::query()->visibleToPublic()->whereNotNull('fuel')->filtered($filters + ['fuel' => []])->reorder(),
                'fuel',
                $filters['fuel'] ?? [],
                10,
            ),
            'max_price' => (int) (Car::query()->visibleToPublic()->max('price') ?? 0),
            'min_year' => (int) (Car::query()->visibleToPublic()->min('year') ?? (int) date('Y')),
            'available' => Car::query()->visibleToPublic()->available()->count(),
            'total' => $this->countMatching($filters)->toBase()->getCountForPagination(),
            'verified' => $this->countMatching($filters)->where('is_verified', true)->toBase()->getCountForPagination(),
            'with_financing' => $this->countMatching($filters)->whereNotNull('installment_monthly')->toBase()->getCountForPagination(),
        ];
    }

    /**
     * The number of listings the current filters select, used for the summary
     * chips and for the toggle counts beside each filter group.
     *
     * @param  array<string, mixed>  $filters
     */
    public function countMatching(array $filters): Builder
    {
        return Car::query()
            ->visibleToPublic()
            ->filtered($filters)
            ->reorder();
    }

    /**
     * @param  Builder<Car>  $query
     * @param  list<string>  $selected
     * @return list<array{value: string, label: string, count: int}>
     */
    private function facetCounts(Builder $query, string $column, array $selected, int $limit): array
    {
        return $query
            ->selectRaw("{$column} as value, COUNT(*) as total")
            ->whereNotNull($column)
            ->groupBy($column)
            ->orderByDesc('total')
            ->orderBy($column)
            ->limit($limit)
            ->get()
            ->map(fn (object $row): array => [
                'value' => (string) $row->value,
                'label' => $this->facetLabel($column, (string) $row->value),
                'count' => (int) $row->total,
                'selected' => in_array((string) $row->value, $selected, true),
            ])
            ->values()
            ->all();
    }

    private function facetLabel(string $column, string $value): string
    {
        return match ($column) {
            'brand' => CarBrand::tryFrom($value)?->value ?? $value,
            'transmission' => Transmission::tryFrom($value)?->label() ?? $value,
            'drivetrain' => Drivetrain::tryFrom($value)?->label() ?? $value,
            'fuel' => FuelType::tryFrom($value)?->label() ?? $value,
            default => $value,
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applySort(Builder $query, string $sort): Builder
    {
        return match ($sort) {
            'harga_terendah' => $query->orderBy('price')->orderBy('id'),
            'harga_tertinggi' => $query->orderByDesc('price')->orderBy('id'),
            'tahun_terbaru' => $query->orderByDesc('year')->orderBy('id'),
            'kilometer_terendah' => $query->orderByRaw('COALESCE(mileage_max, 999999999) ASC')->orderBy('id'),
            default => $query->latest('posted_at')->orderByDesc('id'),
        };
    }

    private function stringOrNull(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return $value === '' ? null : $value;
    }

    private function intOrNull(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        $values = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(array_map(
            fn (mixed $item): string => is_string($item) ? trim($item) : '',
            $values,
        ), fn (string $item): bool => $item !== ''));
    }

    /**
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private function allowedList(mixed $value, array $allowed): array
    {
        return array_values(array_intersect($this->stringList($value), $allowed));
    }

    private function sort(mixed $value): string
    {
        $allowed = ['terbaru', 'harga_terendah', 'harga_tertinggi', 'tahun_terbaru', 'kilometer_terendah'];

        return in_array($value, $allowed, true) ? $value : 'terbaru';
    }
}
