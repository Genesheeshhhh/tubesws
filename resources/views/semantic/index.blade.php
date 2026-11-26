@extends('layouts.app')

@section('title', 'Web semantik — dataset '.$dataset['name'])
@section('meta-description', 'Jelajahi ontologi dan triple RDF katalog mobil bekas Medan di dataset '.$dataset['name'].' pada Apache Jena Fuseki.')

@section('konten')
    <nav aria-label="Breadcrumb" class="text-sm text-slate-500">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route('beranda') }}" class="hover:text-brand-700">Beranda</a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page" class="font-medium text-slate-700">Web semantik</li>
        </ol>
    </nav>

    <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Struktur dataset</h1>
            <p class="prose-note mt-1 max-w-3xl">
                Semua angka di halaman ini dihitung dengan query SPARQL ke <span class="font-mono text-xs">dataset {{ $dataset['name'] }}</span>.
                Tidak ada salinan skema di PostgreSQL; ontologi dan data hidup di Fuseki.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('semantik.graph') }}" class="btn-secondary">Unduh graf (.ttl)</a>
            <a href="{{ route('semantik.rdfa') }}" class="btn-secondary">Laman RDFa</a>
            <a href="{{ route('semantik.sparql') }}" class="btn-primary">Konsol SPARQL</a>
        </div>
    </div>

    <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-5">
        @foreach ([
            ['label' => 'Triple', 'value' => $statistics['triples'], 'note' => 'total pernyataan'],
            ['label' => 'Subjek', 'value' => $statistics['subjects'], 'note' => 'individu unik'],
            ['label' => 'Predikat', 'value' => $statistics['predicates'], 'note' => 'relasi & properti'],
            ['label' => 'Objek IRI', 'value' => $statistics['iriObjects'], 'note' => 'tautan antar individu'],
            ['label' => 'Objek literal', 'value' => $statistics['literalObjects'], 'note' => 'teks, angka, tanggal'],
        ] as $stat)
            <div class="card p-4">
                <p class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase">{{ $stat['label'] }}</p>
                <p class="mt-1 text-2xl font-black tracking-tight text-slate-900">{{ number_format($stat['value']) }}</p>
                <p class="mt-0.5 text-xs text-slate-500">{{ $stat['note'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div class="space-y-6">
            <section class="card overflow-hidden" aria-labelledby="kelas-heading">
                <div class="flex flex-wrap items-end justify-between gap-2 border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 id="kelas-heading" class="text-lg font-bold tracking-tight text-slate-900">Kelas OWL</h2>
                        <p class="prose-note mt-1">Jumlah instans dihitung dari triple <span class="font-mono text-xs">rdf:type</span>.</p>
                    </div>
                    <span class="chip-neutral">{{ count($classes) }} kelas</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <caption class="sr-only">Kelas OWL di dataset</caption>
                        <thead class="bg-slate-50 text-[11px] tracking-wide text-slate-500 uppercase">
                            <tr>
                                <th scope="col" class="px-5 py-2.5 font-semibold">Kelas</th>
                                <th scope="col" class="px-5 py-2.5 font-semibold">Prefiks</th>
                                <th scope="col" class="px-5 py-2.5 text-right font-semibold">Instans</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($classes as $class)
                                <tr class="hover:bg-slate-50/60">
                                    <td class="px-5 py-2.5">
                                        <span class="font-medium text-slate-800">{{ $class['label'] }}</span>
                                        <span class="mt-0.5 block max-w-md truncate font-mono text-[10px] text-slate-400" title="{{ $class['iri'] }}">{{ $class['iri'] }}</span>
                                    </td>
                                    <td class="px-5 py-2.5">
                                        <code class="rounded bg-brand-50 px-1.5 py-0.5 font-mono text-xs text-brand-800">{{ $class['prefixed'] }}</code>
                                        @unless ($class['local'])
                                            <span class="ml-1 text-[10px] text-slate-400">eksternal</span>
                                        @endunless
                                    </td>
                                    <td class="px-5 py-2.5 text-right font-semibold text-slate-700">{{ number_format($class['instances']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card overflow-hidden" aria-labelledby="predikat-heading">
                <div class="flex flex-wrap items-end justify-between gap-2 border-b border-slate-100 px-5 py-4">
                    <div>
                        <h2 id="predikat-heading" class="text-lg font-bold tracking-tight text-slate-900">Properti</h2>
                        <p class="prose-note mt-1">Object property menautkan individu; datatype property menyimpan nilai literal.</p>
                    </div>
                    <span class="chip-neutral">{{ count($predicates) }} properti</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <caption class="sr-only">Properti RDF di dataset</caption>
                        <thead class="bg-slate-50 text-[11px] tracking-wide text-slate-500 uppercase">
                            <tr>
                                <th scope="col" class="px-5 py-2.5 font-semibold">Properti</th>
                                <th scope="col" class="px-5 py-2.5 font-semibold">Tipe</th>
                                <th scope="col" class="px-5 py-2.5 text-right font-semibold">Dipakai</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($predicates as $predicate)
                                <tr class="hover:bg-slate-50/60">
                                    <td class="px-5 py-2.5">
                                        <span class="font-medium text-slate-800">{{ $predicate['label'] }}</span>
                                        <span class="mt-0.5 block font-mono text-[10px] text-brand-700">{{ $predicate['prefixed'] }}</span>
                                    </td>
                                    <td class="px-5 py-2.5">
                                        <span class="{{ str_ends_with($predicate['type'], 'ObjectProperty') ? 'chip-brand' : 'chip-neutral' }}">{{ $predicate['typeLabel'] }}</span>
                                    </td>
                                    <td class="px-5 py-2.5 text-right font-semibold text-slate-700">{{ number_format($predicate['uses']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="space-y-5">
            <section class="card p-5" aria-labelledby="dataset-heading">
                <div class="flex items-center justify-between gap-2">
                    <h2 id="dataset-heading" class="text-base font-bold text-slate-900">Dataset</h2>
                    <span class="{{ $dataset['available'] ? 'chip-verified' : 'chip-sold' }}">{{ $dataset['available'] ? 'Online' : 'Offline' }}</span>
                </div>

                <dl class="mt-4 space-y-3 text-sm">
                    <div>
                        <dt class="text-xs text-slate-500">Nama</dt>
                        <dd class="font-mono text-xs break-all text-slate-800">{{ $dataset['name'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Endpoint query</dt>
                        <dd class="font-mono text-xs break-all text-slate-800">{{ $dataset['query'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Endpoint update</dt>
                        <dd class="font-mono text-xs break-all text-slate-800">{{ $dataset['update'] }}</dd>
                    </div>
                </dl>

                @if ($dataset['available'])
                    <p class="mt-3 text-xs text-emerald-700">Fuseki merespons. Data siap dibaca.</p>
                @else
                    <p class="mt-3 text-xs text-rose-700">Fuseki tidak merespons. Jalankan Fuseki lalu muat ulang.</p>
                @endif

                <a href="{{ route('semantik.ontology') }}" class="btn-secondary mt-4 w-full">Lihat ontologi sumber</a>
            </section>

            <section class="card p-5" aria-labelledby="katalog-heading">
                <h2 id="katalog-heading" class="text-base font-bold text-slate-900">Ringkasan katalog</h2>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-xl bg-slate-50 p-3">
                        <dt class="text-xs text-slate-500">Iklan</dt>
                        <dd class="text-lg font-bold text-slate-900">{{ number_format($catalogue['total']) }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <dt class="text-xs text-slate-500">Merek</dt>
                        <dd class="text-lg font-bold text-slate-900">{{ number_format($catalogue['brands']) }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <dt class="text-xs text-slate-500">Lokasi</dt>
                        <dd class="text-lg font-bold text-slate-900">{{ number_format($catalogue['locations']) }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <dt class="text-xs text-slate-500">Dengan foto</dt>
                        <dd class="text-lg font-bold text-slate-900">{{ number_format($catalogue['with_image']) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="card p-5" aria-labelledby="prefix-heading">
                <h2 id="prefix-heading" class="text-base font-bold text-slate-900">Prefiks</h2>
                <dl class="mt-3 space-y-2 text-xs">
                    @foreach ($namespaces as $prefix => $iri)
                        <div class="flex gap-2">
                            <dt class="w-14 shrink-0 font-mono font-semibold text-brand-700">{{ $prefix }}:</dt>
                            <dd class="min-w-0 break-all font-mono text-slate-500">{{ $iri }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>

            @if ($sample)
                <section class="card overflow-hidden" aria-labelledby="contoh-heading">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h2 id="contoh-heading" class="text-base font-bold text-slate-900">Contoh individu</h2>
                    </div>
                    <a href="{{ route('semantik.show', ['car' => $sample->id]) }}" class="block p-5 transition hover:bg-slate-50">
                        <p class="text-sm font-semibold text-slate-900">{{ $sample->label }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $sample->summary() }}</p>
                        <p class="mt-2 font-mono text-[10px] break-all text-brand-700">{{ $sample->uri }}</p>
                        <span class="mt-3 inline-block text-xs font-semibold text-brand-700">Buka triple RDF →</span>
                    </a>
                </section>
            @endif
        </div>
    </div>
@endsection
