@extends('layouts.app')

@section('title', 'RDF '.$car->label.' — dataset TUBESWS')
@section('meta-description', 'Representasi RDF untuk '.$car->label.' dari dataset TUBESWS di Apache Jena Fuseki.')

@section('konten')
    @php
        $local = function (string $iri): string {
            if ($iri === '') {
                return '';
            }

            return str_contains($iri, '#') ? substr($iri, strrpos($iri, '#') + 1) : basename($iri);
        };

        $grouped = collect($statements)->groupBy(fn (array $statement): string => $statement['predicateLabel']);
    @endphp

    <nav aria-label="Breadcrumb" class="text-sm text-slate-500">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route('beranda') }}" class="hover:text-brand-700">Beranda</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ route('semantik.index') }}" class="hover:text-brand-700">Web semantik</a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page" class="truncate font-medium text-slate-700">{{ $car->id }}</li>
        </ol>
    </nav>

    <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-[11px] font-semibold tracking-wide text-brand-700 uppercase">Individu MobilBekas</p>
            <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">{{ $car->label }}</h1>
            <p class="mt-1 font-mono text-xs break-all text-slate-500">{{ $car->uri }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('mobil.show', ['car' => $car->id]) }}" class="btn-secondary">Halaman iklan</a>
            <a href="{{ route('mobil.turtle', ['car' => $car->id]) }}" class="btn-primary">Unduh Turtle</a>
        </div>
    </div>

    <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card p-4">
            <p class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase">Harga</p>
            <p class="mt-1 text-lg font-bold text-slate-900">{{ $car->priceLabel() }}</p>
        </div>
        <div class="card p-4">
            <p class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase">Merek</p>
            <p class="mt-1 text-lg font-bold text-slate-900">{{ $car->brand }}</p>
        </div>
        <div class="card p-4">
            <p class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase">Lokasi</p>
            <p class="mt-1 text-lg font-bold text-slate-900">{{ $car->location }}</p>
        </div>
        <div class="card p-4">
            <p class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase">Triple</p>
            <p class="mt-1 text-lg font-bold text-slate-900">{{ number_format(count($statements)) }}</p>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        <section class="card overflow-hidden" aria-labelledby="triple-heading">
            <div class="flex flex-wrap items-end justify-between gap-2 border-b border-slate-100 px-5 py-4">
                <div>
                    <h2 id="triple-heading" class="text-lg font-bold tracking-tight text-slate-900">Triple subjek</h2>
                    <p class="prose-note mt-1">Setiap baris adalah satu pernyataan <span class="font-mono text-xs">subjek predikat objek</span>.</p>
                </div>
                <span class="chip-neutral">{{ number_format(count($statements)) }} baris</span>
            </div>

            @if ($statements === [])
                <p class="p-5 text-sm text-slate-600">Fuseki tidak mengembalikan triple untuk subjek ini.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <caption class="sr-only">Triple RDF untuk {{ $car->label }}</caption>
                        <thead class="bg-slate-50 text-[11px] tracking-wide text-slate-500 uppercase">
                            <tr>
                                <th scope="col" class="px-5 py-2.5 font-semibold">Predikat</th>
                                <th scope="col" class="px-5 py-2.5 font-semibold">Objek</th>
                                <th scope="col" class="px-5 py-2.5 font-semibold">Jenis</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($statements as $statement)
                                <tr class="align-top hover:bg-slate-50/60">
                                    <td class="px-5 py-3">
                                        <span class="font-medium text-slate-800">{{ $statement['predicateLabel'] }}</span>
                                        <span class="mt-0.5 block font-mono text-[10px] text-brand-700">{{ $local($statement['predicate']) }}</span>
                                    </td>
                                    <td class="px-5 py-3">
                                        @if ($statement['objectIsIri'])
                                            <span class="font-medium text-slate-700">{{ $statement['objectLabel'] ?? $local($statement['object']) }}</span>
                                            <span class="mt-0.5 block max-w-md truncate font-mono text-[10px] text-slate-400" title="{{ $statement['object'] }}">{{ $statement['object'] }}</span>
                                        @else
                                            <span class="break-words text-slate-700">{{ \Illuminate\Support\Str::limit($statement['object'], 220) }}</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="{{ $statement['objectIsIri'] ? 'chip-brand' : 'chip-neutral' }}">{{ $statement['objectIsIri'] ? 'IRI' : 'Literal' }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <div class="space-y-5">
            <section class="card p-5" aria-labelledby="ringkas-heading">
                <h2 id="ringkas-heading" class="text-base font-bold text-slate-900">Ringkasan properti</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    @foreach ($grouped as $predicate => $rows)
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-1.5">
                            <dt class="truncate text-slate-600">{{ $predicate }}</dt>
                            <dd class="text-xs font-semibold text-slate-800">{{ $rows->count() }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            <section class="card p-5" aria-labelledby="unduh-heading">
                <h2 id="unduh-heading" class="text-base font-bold text-slate-900">Format unduhan</h2>
                <p class="prose-note mt-2">Ambil subjek ini saja sebagai Turtle, atau seluruh graf lewat halaman struktur dataset.</p>
                <div class="mt-3 space-y-2">
                    <a href="{{ route('mobil.turtle', ['car' => $car->id]) }}" class="btn-secondary w-full">Turtle subjek</a>
                    <a href="{{ route('semantik.graph') }}" class="btn-secondary w-full">Turtle seluruh graf</a>
                    <a href="{{ route('semantik.sparql', ['query' => 'DESCRIBE <'.$car->uri.'>']) }}" class="btn-ghost w-full">Jalankan DESCRIBE</a>
                </div>
            </section>
        </div>
    </div>
@endsection
