@extends('layouts.app')

@section('title', 'Ontologi sumber — '.$fileName)
@section('meta-description', 'Ontologi '.$fileName.' yang dimuat ke dataset '.$datasetName.' di Apache Jena Fuseki.')

@section('konten')
    <nav aria-label="Breadcrumb" class="text-sm text-slate-500">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route('beranda') }}" class="hover:text-brand-700">Beranda</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ route('semantik.index') }}" class="hover:text-brand-700">Web semantik</a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page" class="font-medium text-slate-700">Ontologi</li>
        </ol>
    </nav>

    <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Ontologi sumber</h1>
            <p class="prose-note mt-1 max-w-3xl">
                Berkas RDF/XML yang dimuat ke dataset <span class="font-mono text-xs">{{ $datasetName }}</span> dengan
                <span class="font-mono text-xs">php artisan fuseki:load</span>. Unduhan di bawah adalah berkas asli, bukan hasil serialisasi ulang.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($fileExists)
                <a href="{{ route('semantik.ontology.download') }}" class="btn-primary">Unduh {{ $fileName }}</a>
            @endif
            <a href="{{ route('semantik.index') }}" class="btn-secondary">Kembali ke dataset</a>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div class="space-y-6">
            <section class="card p-5" aria-labelledby="berkas-heading">
                <h2 id="berkas-heading" class="text-lg font-bold tracking-tight text-slate-900">Berkas</h2>
                <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs text-slate-500">Nama berkas</dt>
                        <dd class="font-mono text-xs break-all text-slate-800">{{ $fileName }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Ukuran</dt>
                        <dd class="font-mono text-xs text-slate-800">{{ $fileSize > 0 ? number_format($fileSize / 1_048_576, 2, ',', '.').' MB' : 'Tidak ditemukan' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">IRI ontologi</dt>
                        <dd class="font-mono text-xs break-all text-slate-800">{{ $ontologyUrl }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs text-slate-500">Dataset tujuan</dt>
                        <dd class="font-mono text-xs break-all text-slate-800">{{ $datasetUrl }}</dd>
                    </div>
                </dl>

                @unless ($fileExists)
                    <p class="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        Berkas ontologi tidak ditemukan di jalur yang dikonfigurasi. Periksa <span class="font-mono">FUSEKI_ONTOLOGY</span> pada <span class="font-mono">.env</span>.
                    </p>
                @endunless
            </section>

            <section class="card overflow-hidden" aria-labelledby="kelas-heading">
                <div class="flex items-end justify-between gap-2 border-b border-slate-100 px-5 py-4">
                    <h2 id="kelas-heading" class="text-lg font-bold tracking-tight text-slate-900">Kelas</h2>
                    <span class="chip-neutral">{{ count($classes) }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <caption class="sr-only">Kelas pada ontologi</caption>
                        <thead class="bg-slate-50 text-[11px] tracking-wide text-slate-500 uppercase">
                            <tr>
                                <th scope="col" class="px-5 py-2.5 font-semibold">Prefiks</th>
                                <th scope="col" class="px-5 py-2.5 font-semibold">Label</th>
                                <th scope="col" class="px-5 py-2.5 text-right font-semibold">Instans</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($classes as $class)
                                <tr class="hover:bg-slate-50/60">
                                    <td class="px-5 py-2.5 font-mono text-xs text-brand-700">{{ $class['prefixed'] }}</td>
                                    <td class="px-5 py-2.5 text-slate-800">{{ $class['label'] }}</td>
                                    <td class="px-5 py-2.5 text-right font-semibold text-slate-700">{{ number_format($class['instances']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="card overflow-hidden" aria-labelledby="properti-heading">
                <div class="flex items-end justify-between gap-2 border-b border-slate-100 px-5 py-4">
                    <h2 id="properti-heading" class="text-lg font-bold tracking-tight text-slate-900">Properti</h2>
                    <span class="chip-neutral">{{ count($predicates) }}</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-left text-sm">
                        <caption class="sr-only">Properti pada ontologi</caption>
                        <thead class="bg-slate-50 text-[11px] tracking-wide text-slate-500 uppercase">
                            <tr>
                                <th scope="col" class="px-5 py-2.5 font-semibold">Prefiks</th>
                                <th scope="col" class="px-5 py-2.5 font-semibold">Label</th>
                                <th scope="col" class="px-5 py-2.5 font-semibold">Tipe</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($predicates as $predicate)
                                <tr class="hover:bg-slate-50/60">
                                    <td class="px-5 py-2.5 font-mono text-xs text-brand-700">{{ $predicate['prefixed'] }}</td>
                                    <td class="px-5 py-2.5 text-slate-800">{{ $predicate['label'] }}</td>
                                    <td class="px-5 py-2.5">
                                        <span class="{{ str_ends_with($predicate['type'], 'ObjectProperty') ? 'chip-brand' : 'chip-neutral' }}">{{ $predicate['typeLabel'] }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="space-y-5">
            <section class="card p-5" aria-labelledby="muat-heading">
                <h2 id="muat-heading" class="text-base font-bold text-slate-900">Muat ulang data</h2>
                <p class="prose-note mt-2">Setelah berkas XML berubah, unggah ulang ke Fuseki dari terminal proyek:</p>
                <pre class="mt-3 overflow-x-auto rounded-xl bg-slate-900 p-3 text-xs text-slate-100"><code>php artisan fuseki:load --replace</code></pre>
                <p class="mt-2 text-xs text-slate-500">Perintah ini mengosongkan graf default lalu memuat ulang berkas dan membersihkan cache katalog.</p>
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
        </div>
    </div>
@endsection
