@extends('layouts.app')

@section('title', 'Katalog mobil bekas Medan — '.number_format($cars->total()).' iklan')
@section('meta-description', 'Filter '.number_format($cars->total()).' mobil bekas Medan berdasarkan merek, jenis, transmisi, bahan bakar, lokasi, harga, tahun, dan ketersediaan cicilan.')

{{-- Open Graph untuk halaman katalog --}}
@section('og-title', 'Katalog mobil bekas Medan — '.number_format($cars->total()).' iklan')
@section('og-description', 'Temukan dan bandingkan mobil bekas di Medan berdasarkan merek, jenis, transmisi, bahan bakar, lokasi, harga, tahun, dan cicilan.')
@section('og-url', url()->current())

@section('konten')

    @php
        $selected = [
            'brand' => $filters['brand'] ?? [],
            'body_type' => $filters['body_type'] ?? [],
            'transmission' => $filters['transmission'] ?? [],
            'drivetrain' => $filters['drivetrain'] ?? [],
            'fuel' => $filters['fuel'] ?? [],
            'location' => $filters['location'] ?? [],
        ];
        $activeCount = collect($selected)->flatten()->count()
            + (filled($filters['q'] ?? null) ? 1 : 0)
            + (($filters['min_price'] ?? null) !== null ? 1 : 0)
            + (($filters['max_price'] ?? null) !== null ? 1 : 0)
            + (($filters['min_year'] ?? null) !== null ? 1 : 0)
            + (($filters['max_mileage'] ?? null) !== null ? 1 : 0)
            + (($filters['with_financing'] ?? false) ? 1 : 0)
            + (($filters['with_image'] ?? false) ? 1 : 0)
            + ((($filters['regex'] ?? false) && filled($filters['q'] ?? null)) ? 1 : 0);

        $sortLabels = [
            'terbaru' => 'Terbaru',
            'harga_terendah' => 'Harga terendah',
            'harga_tertinggi' => 'Harga tertinggi',
            'tahun_terbaru' => 'Tahun terbaru',
            'kilometer_terendah' => 'Kilometer terendah',
        ];

        $removeUrl = function (string $key, ?string $value = null): string {
            $query = request()->query();

            if ($value === null) {
                unset($query[$key]);
            } else {
                $list = array_values(array_diff((array) ($query[$key] ?? []), [$value]));
                if ($list === []) {
                    unset($query[$key]);
                } else {
                    $query[$key] = $list;
                }
            }

            unset($query['page']);

            return route('mobil.index', $query);
        };

        $facetGroups = [
            'brand' => ['title' => 'Merek', 'open' => true],
            'body_type' => ['title' => 'Jenis mobil', 'open' => false],
            'transmission' => ['title' => 'Transmisi', 'open' => false],
            'drivetrain' => ['title' => 'Penggerak', 'open' => false],
            'fuel' => ['title' => 'Bahan bakar', 'open' => false],
            'location' => ['title' => 'Lokasi', 'open' => false],
        ];
    @endphp

    <nav aria-label="Breadcrumb" class="text-sm text-slate-500">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route('beranda') }}" class="hover:text-brand-700">Beranda</a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page" class="font-medium text-slate-700">Katalog mobil</li>
        </ol>
    </nav>

    <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Katalog mobil bekas Medan</h1>
            <p class="prose-note mt-1">
                @if ($cars->total() > 0)
                    Menampilkan <strong class="text-slate-800">{{ number_format($cars->firstItem()) }}–{{ number_format($cars->lastItem()) }}</strong>
                    dari <strong class="text-slate-800">{{ number_format($cars->total()) }}</strong> iklan
                @else
                    Tidak ada iklan yang cocok dengan filter saat ini
                @endif
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if ($activeCount > 0)
                <a href="{{ route('mobil.index') }}" class="btn-ghost">Reset filter ({{ $activeCount }})</a>
            @endif

            <form method="GET" action="{{ route('mobil.index') }}" class="flex items-center gap-2">
                @foreach (request()->except(['sort', 'page']) as $key => $value)
                    @if (is_array($value))
                        @foreach ($value as $item)
                            <input type="hidden" name="{{ $key }}[]" value="{{ $item }}">
                        @endforeach
                    @else
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endif
                @endforeach
                <label for="sort" class="text-sm font-medium text-slate-600">Urutkan</label>
                <select id="sort" name="sort" class="field w-52 py-2" data-autosubmit>
                    @foreach ($sortLabels as $value => $label)
                        <option value="{{ $value }}" @selected(($filters['sort'] ?? 'terbaru') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
                <noscript><button type="submit" class="btn-secondary">Terapkan</button></noscript>
            </form>
        </div>
    </div>

    @if (($search['corrected'] ?? false) && filled($search['original'] ?? null))
        <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-2 text-sm text-amber-800">
            Tidak menemukan “{{ $search['original'] }}”. Menampilkan hasil untuk
            <strong>{{ $search['resolved'] }}</strong>.
        </p>
    @endif

    @if ($filters['regex_error'] ?? false)
        <p class="mt-3 rounded-xl border border-amber-200 bg-amber-50 px-3.5 py-2 text-sm text-amber-800">
            Pola regex tidak valid. Pencarian kata kunci dilakukan tanpa regex.
        </p>
    @endif

    @if ($activeCount > 0)
        <div class="mt-4 flex flex-wrap items-center gap-2">
            @if (filled($filters['q'] ?? null))
                <a href="{{ $removeUrl('q') }}" class="chip-brand">{{ $filters['q'] }} <span aria-hidden="true">×</span></a>
            @endif

            @if ($filters['regex'] ?? false)
                <a href="{{ $removeUrl('regex') }}" class="chip-brand">Regex <span aria-hidden="true">×</span></a>
            @endif

            @foreach ($selected as $key => $values)
                @foreach ($values as $value)
                    <a href="{{ $removeUrl($key, $value) }}" class="chip-brand">{{ $value }} <span aria-hidden="true">×</span></a>
                @endforeach
            @endforeach

            @foreach (['min_price' => 'Harga min', 'max_price' => 'Harga maks', 'min_year' => 'Tahun min', 'max_mileage' => 'KM maks'] as $key => $label)
                @if (($filters[$key] ?? null) !== null)
                    <a href="{{ $removeUrl($key) }}" class="chip-brand">
                        {{ $label }}:
                        {{ $key === 'min_year' ? $filters[$key] : 'Rp '.number_format((int) $filters[$key], 0, ',', '.') }}
                        <span aria-hidden="true">×</span>
                    </a>
                @endif
            @endforeach

            @if ($filters['with_financing'] ?? false)
                <a href="{{ $removeUrl('with_financing') }}" class="chip-brand">Bisa dicicil <span aria-hidden="true">×</span></a>
            @endif

            @if ($filters['with_image'] ?? false)
                <a href="{{ $removeUrl('with_image') }}" class="chip-brand">Ada foto <span aria-hidden="true">×</span></a>
            @endif
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-[290px_minmax(0,1fr)]">
        <aside aria-label="Filter katalog">
            <form method="GET" action="{{ route('mobil.index') }}" class="card filter-scroll sticky top-24 max-h-[calc(100vh-7rem)] space-y-5 overflow-y-auto p-5">
                <input type="hidden" name="sort" value="{{ $filters['sort'] ?? 'terbaru' }}">

                <div class="flex items-center justify-between">
                    <h2 class="text-base font-bold text-slate-900">Filter</h2>
                    @if ($activeCount > 0)
                        <a href="{{ route('mobil.index', ['sort' => $filters['sort'] ?? 'terbaru']) }}" class="text-xs font-semibold text-brand-700 hover:underline">Bersihkan</a>
                    @endif
                </div>

                <div>
                    <label for="filter-q" class="label">Kata kunci</label>
                    <input id="filter-q" name="q" type="search" value="{{ $filters['q'] ?? '' }}" placeholder="Avanza, Toyota, Petisah…" class="field">
                    <label class="mt-2 flex cursor-pointer items-center gap-2 text-xs text-slate-600">
                        <input id="filter-regex" type="checkbox" name="regex" value="1" @checked(($filters['regex'] ?? false) || ($filters['regex_error'] ?? false)) class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" data-autosubmit>
                        Gunakan regex (pola pencarian)
                    </label>
                    @if ($filters['regex'] ?? false)
                        <p class="mt-1 text-xs text-slate-500">Contoh: <code class="rounded bg-slate-100 px-1">^(Toyota|Honda).*2020</code></p>
                    @endif
                </div>

                <fieldset>
                    <legend class="label">Rentang harga (Rp)</legend>
                    <div class="grid grid-cols-2 gap-2">
                        <input name="min_price" type="number" min="0" step="1000000" value="{{ $filters['min_price'] ?? '' }}" placeholder="Min" class="field" aria-label="Harga minimum">
                        <input name="max_price" type="number" min="0" step="1000000" value="{{ $filters['max_price'] ?? '' }}" placeholder="Maks" class="field" aria-label="Harga maksimum">
                    </div>
                    <p class="mt-1.5 text-xs text-slate-500">Data: Rp {{ number_format($facets['min_price'], 0, ',', '.') }} – Rp {{ number_format($facets['max_price'], 0, ',', '.') }}</p>
                </fieldset>

                <fieldset class="grid grid-cols-2 gap-2">
                    <div>
                        <label for="min-year" class="label">Tahun min.</label>
                        <input id="min-year" name="min_year" type="number" min="1980" max="{{ $facets['max_year'] }}" value="{{ $filters['min_year'] ?? '' }}" placeholder="{{ $facets['min_year'] }}" class="field">
                    </div>
                    <div>
                        <label for="max-mileage" class="label">KM maks.</label>
                        <input id="max-mileage" name="max_mileage" type="number" min="0" step="1000" value="{{ $filters['max_mileage'] ?? '' }}" placeholder="150000" class="field">
                    </div>
                </fieldset>

                <fieldset class="space-y-2">
                    <legend class="label">Kondisi data</legend>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="with_financing" value="1" @checked($filters['with_financing'] ?? false) class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" data-autosubmit>
                        Hanya yang bisa dicicil
                        <span class="ml-auto text-xs text-slate-400">{{ $facets['with_financing'] }}</span>
                    </label>
                    <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="with_image" value="1" @checked($filters['with_image'] ?? false) class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" data-autosubmit>
                        Hanya yang ada foto
                        <span class="ml-auto text-xs text-slate-400">{{ $facets['with_image'] }}</span>
                    </label>
                </fieldset>

                
<div class="space-y-3 border-t border-slate-100 pt-3">
    @foreach ($facetGroups as $key => $group)
        @if (! empty($facets[$key]))
            <details class="group" @if ($group['open'] || $selected[$key] !== []) open @endif>
                <summary class="flex cursor-pointer list-none items-center justify-between gap-2 py-1 text-sm font-semibold text-slate-800">
                    {{ $group['title'] }}
                    <span class="text-xs font-normal text-slate-400 group-open:rotate-180 transition" aria-hidden="true">⌄</span>
                </summary>

                <div class="filter-scroll mt-2 max-h-64 space-y-1.5 overflow-y-auto pr-1">
                    @foreach ($facets[$key] as $facet)
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg px-1 py-1 text-sm text-slate-700 hover:bg-slate-50">
                            <input type="checkbox" name="{{ $key }}[]" value="{{ $facet['value'] }}" @checked($facet['selected']) class="h-4 w-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500" data-autosubmit>

                            <span class="min-w-0 flex-1 truncate">{{ $facet['label'] }}</span>

                            @if ($key !== 'brand')
                                <span class="text-xs text-slate-400">{{ $facet['count'] }}</span>
                            @endif
                        </label>
                    @endforeach
                </div>
            </details>
        @endif
    @endforeach
</div>


                <div class="flex gap-2 border-t border-slate-100 pt-4">
                    <button type="submit" class="btn-primary flex-1">Terapkan</button>
                    <a href="{{ route('mobil.index') }}" class="btn-secondary">Reset</a>
                </div>
                <noscript><p class="text-xs text-amber-700">Tanpa JavaScript, tekan “Terapkan” setelah memilih filter.</p></noscript>
            </form>
        </aside>

        <div>
            @if ($cars->isEmpty())
                <div class="card p-10 text-center">
                    <p class="text-4xl" aria-hidden="true">🔍</p>
                    <h2 class="mt-3 text-lg font-bold text-slate-900">Belum ada mobil yang cocok</h2>
                    <p class="prose-note mx-auto mt-2 max-w-md">Coba longgarkan harga, tahun, atau kilometer. Filter saat ini menghasilkan nol iklan dari {{ number_format($facets['total']) }} data yang tersedia.</p>
                    <a href="{{ route('mobil.index') }}" class="btn-primary mt-5">Reset semua filter</a>
                </div>
            @else
                <div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($cars as $car)
                        <x-car-card :car="$car" />
                    @endforeach
                </div>

                <div class="mt-8">
                    {{ $cars->links() }}
                </div>
            @endif
        </div>
    </div>
@endsection
