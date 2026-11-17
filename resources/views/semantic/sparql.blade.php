@extends('layouts.app')

@section('title', 'SPARQL explorer — dataset '.$dataset['name'])
@section('meta-description', 'Jalankan query SPARQL 1.1 terhadap dataset '.$dataset['name'].' di Apache Jena Fuseki.')

@section('konten')
    @php
        $local = function (string $iri): string {
            if ($iri === '') {
                return '';
            }

            return str_contains($iri, '#') ? substr($iri, strrpos($iri, '#') + 1) : basename($iri);
        };
    @endphp

    <nav aria-label="Breadcrumb" class="text-sm text-slate-500">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route('beranda') }}" class="hover:text-brand-700">Beranda</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ route('semantik.index') }}" class="hover:text-brand-700">Web semantik</a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page" class="font-medium text-slate-700">SPARQL</li>
        </ol>
    </nav>

    <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">SPARQL explorer</h1>
            <p class="prose-note mt-1 max-w-3xl">
                Query dikirim apa adanya ke <span class="font-mono text-xs">{{ $dataset['query'] }}</span>.
                Dukungan SELECT, ASK, CONSTRUCT, dan DESCRIBE mengikuti kemampuan Fuseki.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('semantik.index') }}" class="btn-secondary">Struktur dataset</a>
            <a href="{{ route('semantik.ontology') }}" class="btn-ghost">Ontologi</a>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px]">
        <div class="space-y-6">
            <form method="GET" action="{{ route('semantik.sparql') }}" class="card p-5">
                <div class="flex flex-wrap items-end justify-between gap-2">
                    <label for="query" class="text-sm font-bold text-slate-900">Query</label>
                    <span class="text-xs text-slate-500">Ctrl/Cmd + Enter untuk menjalankan</span>
                </div>
                <textarea id="query" name="query" rows="14" spellcheck="false"
                    class="mt-2 w-full rounded-xl border border-slate-300 bg-slate-950 p-4 font-mono text-xs leading-relaxed text-slate-100 placeholder:text-slate-500 focus:border-brand-500 focus:ring-2 focus:ring-brand-200 focus:outline-none"
                    placeholder="SELECT * WHERE { ?s ?p ?o } LIMIT 10">{{ $query }}</textarea>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <button type="submit" class="btn-primary">Jalankan query</button>
                    <a href="{{ route('semantik.sparql') }}" class="btn-secondary">Kosongkan</a>
                    @if ($query !== '')
                        <a href="{{ route('semantik.sparql.query', ['query' => $query]) }}" class="btn-ghost" target="_blank" rel="noopener">Buka JSON mentah</a>
                    @endif
                </div>
            </form>

            @if ($error)
                <div role="alert" class="rounded-2xl border border-rose-200 bg-rose-50 p-5">
                    <p class="text-sm font-bold text-rose-900">Query gagal</p>
                    <pre class="mt-2 overflow-x-auto rounded-xl bg-white/70 p-3 font-mono text-xs whitespace-pre-wrap text-rose-800">{{ $error }}</pre>
                </div>
            @elseif ($result)
                <section class="card overflow-hidden" aria-labelledby="hasil-heading">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-5 py-4">
                        <div>
                            <h2 id="hasil-heading" class="text-lg font-bold tracking-tight text-slate-900">Hasil</h2>
                            <p class="prose-note mt-1">Bentuk hasil: <span class="font-mono text-xs">{{ strtoupper($result->kind) }}</span></p>
                        </div>
                        @if ($result->kind === 'select')
                            <span class="chip-brand">{{ number_format($result->rowCount()) }} baris</span>
                        @endif
                    </div>

                    @if ($result->kind === 'ask')
                        <div class="p-5">
                            <p class="text-sm text-slate-600">Jawaban Fuseki:</p>
                            <p class="mt-2 inline-flex rounded-xl px-4 py-2 text-lg font-bold {{ $result->boolean ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700' }}">
                                {{ $result->boolean ? 'true' : 'false' }}
                            </p>
                        </div>
                    @elseif ($result->kind === 'construct')
                        <div class="p-5">
                            <p class="prose-note">Graf hasil (Turtle):</p>
                            <pre class="mt-2 max-h-[32rem] overflow-auto rounded-xl bg-slate-950 p-4 font-mono text-xs whitespace-pre text-slate-100">{{ $result->turtle }}</pre>
                        </div>
                    @else
                        <div class="max-h-[36rem] overflow-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                                <caption class="sr-only">Hasil SELECT</caption>
                                <thead class="sticky top-0 bg-slate-50 text-[11px] tracking-wide text-slate-500 uppercase">
                                    <tr>
                                        @foreach ($result->variables as $variable)
                                            <th scope="col" class="px-4 py-2.5 font-semibold">?{{ $variable }}</th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    @forelse ($result->rows as $row)
                                        <tr class="align-top hover:bg-slate-50/60">
                                            @foreach ($result->variables as $variable)
                                                @php $term = $row[$variable] ?? null; @endphp
                                                <td class="px-4 py-2">
                                                    @if ($term === null)
                                                        <span class="text-slate-300">—</span>
                                                    @elseif (($term['type'] ?? '') === 'uri')
                                                        @if (str_starts_with($term['value'], (string) config('fuseki.namespace')) && str_starts_with($local($term['value']), 'Mobil_'))
                                                            <a href="{{ route('semantik.show', ['car' => $local($term['value'])]) }}" class="font-mono text-brand-700 hover:underline">{{ $local($term['value']) }}</a>
                                                        @else
                                                            <span class="font-mono text-slate-700" title="{{ $term['value'] }}">{{ $local($term['value']) }}</span>
                                                        @endif
                                                    @else
                                                        <span class="break-words text-slate-700">{{ $term['value'] }}</span>
                                                        @if (filled($term['datatype'] ?? null))
                                                            <span class="mt-0.5 block font-mono text-[10px] text-slate-400">{{ $local($term['datatype']) }}</span>
                                                        @elseif (filled($term['lang'] ?? null))
                                                            <span class="mt-0.5 block font-mono text-[10px] text-slate-400">lang: {{ $term['lang'] }}</span>
                                                        @endif
                                                    @endif
                                                </td>
                                            @endforeach
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="{{ max(count($result->variables), 1) }}" class="px-4 py-8 text-center text-slate-500">
                                                Query berhasil, tetapi tidak ada baris yang cocok.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endif

            <section class="card p-5" aria-labelledby="contoh-heading">
                <h2 id="contoh-heading" class="text-lg font-bold tracking-tight text-slate-900">Contoh query</h2>
                <p class="prose-note mt-1">Klik “Jalankan” untuk memuat contoh ke editor.</p>

                <div class="mt-4 space-y-2">
                    @foreach ($examples as $example)
                        <details class="group rounded-xl border border-slate-200 bg-white">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3">
                                <span>
                                    <span class="block text-sm font-semibold text-slate-800">{{ $example['title'] }}</span>
                                    <span class="mt-0.5 block text-xs text-slate-500">{{ $example['description'] }}</span>
                                </span>
                                <span class="text-xs text-slate-400 transition group-open:rotate-180" aria-hidden="true">⌄</span>
                            </summary>
                            <div class="border-t border-slate-100 p-4">
                                <pre class="overflow-x-auto rounded-xl bg-slate-950 p-3 font-mono text-[11px] leading-relaxed text-slate-100">{{ $example['query'] }}</pre>
                                <form method="GET" action="{{ route('semantik.sparql') }}" class="mt-3">
                                    <input type="hidden" name="query" value="{{ $example['query'] }}">
                                    <button type="submit" class="btn-secondary">Jalankan contoh ini</button>
                                </form>
                            </div>
                        </details>
                    @endforeach
                </div>
            </section>
        </div>

        <div class="space-y-5">
            <section class="card p-5" aria-labelledby="endpoint-heading">
                <h2 id="endpoint-heading" class="text-base font-bold text-slate-900">Endpoint</h2>
                <dl class="mt-3 space-y-3 text-xs">
                    <div>
                        <dt class="text-slate-500">Dataset</dt>
                        <dd class="font-mono break-all text-slate-800">{{ $dataset['name'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Query (POST)</dt>
                        <dd class="font-mono break-all text-slate-800">{{ $dataset['query'] }}</dd>
                    </div>
                </dl>

                <p class="mt-4 text-xs text-slate-500">Akses langsung dari terminal:</p>
                <pre class="mt-2 overflow-x-auto rounded-xl bg-slate-950 p-3 font-mono text-[10px] text-slate-100"><code>curl -s {{ $dataset['query'] }} \
  --data-urlencode "query=SELECT * WHERE { ?s ?p ?o } LIMIT 5" \
  -H "Accept: application/sparql-results+json"</code></pre>
            </section>

            <section class="card p-5" aria-labelledby="tips-heading">
                <h2 id="tips-heading" class="text-base font-bold text-slate-900">Tips</h2>
                <ul class="mt-3 space-y-2 text-sm text-slate-600">
                    <li>Gunakan prefiks <span class="font-mono text-xs">mb:</span> untuk namespace ontologi.</li>
                    <li>Batasi hasil dengan <span class="font-mono text-xs">LIMIT</span> saat eksplorasi.</li>
                    <li>Query hanya baca; endpoint update tidak diekspos ke halaman ini.</li>
                </ul>
            </section>
        </div>
    </div>
@endsection
