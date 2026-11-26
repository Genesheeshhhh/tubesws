@extends('layouts.app')

@section('title', 'Laman RDFa — '.$car->label)
@section('meta-description', 'Contoh anotasi RDFa 1.1 untuk '.$car->label.' dari dataset TUBESWS di Apache Jena Fuseki.')

@section('konten')
    <nav aria-label="Breadcrumb" class="text-sm text-slate-500">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route('beranda') }}" class="hover:text-brand-700">Beranda</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ route('semantik.index') }}" class="hover:text-brand-700">Web semantik</a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page" class="truncate font-medium text-slate-700">RDFa</li>
        </ol>
    </nav>

    <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-[11px] font-semibold tracking-wide text-brand-700 uppercase">RDFa 1.1 Lite</p>
            <h1 class="mt-1 text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Laman RDFa</h1>
            <p class="prose-note mt-1 max-w-3xl">
                Properti schema.org ditanam langsung di HTML lewat atribut <span class="font-mono text-xs">vocab</span>,
                <span class="font-mono text-xs">typeof</span>, <span class="font-mono text-xs">property</span>, dan
                <span class="font-mono text-xs">content</span>. Mesin dapat membaca fakta yang sama tanpa mengunduh file RDF.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('mobil.show', ['car' => $car->id]) }}" class="btn-secondary">Halaman iklan</a>
            <a href="{{ route('semantik.show', ['car' => $car->id]) }}" class="btn-secondary">Triple RDF</a>
            <a href="{{ route('mobil.turtle', ['car' => $car->id]) }}" class="btn-primary">Unduh Turtle</a>
        </div>
    </div>

    <div vocab="https://schema.org/"
         prefix="xsd: http://www.w3.org/2001/XMLSchema# mb: {{ config('semantic.namespaces.mb') }}"
         typeof="Vehicle"
         resource="{{ $car->uri }}"
         class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">
        @include('partials.rdfa-facts', ['car' => $car])

        <section class="card p-6" aria-labelledby="rdfa-contoh-heading">
            <p class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase">Contoh hasil anotasi</p>
            <h2 id="rdfa-contoh-heading" property="name" class="mt-1 text-xl font-bold tracking-tight text-slate-900">{{ $car->label }}</h2>
            <p class="mt-1 text-xs break-all text-slate-500">Subjek: <span class="font-mono">{{ $car->uri }}</span></p>

            <dl class="mt-5 grid gap-x-8 gap-y-0 sm:grid-cols-2">
                @foreach ([
                    'Merek' => $car->brand,
                    'Model' => $car->model,
                    'Tahun' => $car->yearLabel(),
                    'Body type' => $car->bodyType,
                    'Transmisi' => $car->transmission,
                    'Bahan bakar' => $car->fuel,
                    'Harga' => $car->priceLabel(),
                    'Lokasi' => $car->location,
                ] as $label => $value)
                    <div class="flex items-baseline justify-between gap-4 border-b border-slate-100 py-2.5 text-sm">
                        <dt class="text-slate-500">{{ $label }}</dt>
                        <dd class="text-right font-medium text-slate-900">{{ $value }}</dd>
                    </div>
                @endforeach
            </dl>
        </section>

        <div class="space-y-5">
            <section class="card p-5" aria-labelledby="rdfa-cara-heading">
                <h2 id="rdfa-cara-heading" class="text-base font-bold text-slate-900">Yang terbaca mesin</h2>
                <ul class="mt-3 space-y-2 text-xs">
                    <li class="flex gap-2">
                        <span class="w-28 shrink-0 font-mono font-semibold text-brand-700">rdf:type</span>
                        <span class="break-all text-slate-600">schema:Vehicle</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="w-28 shrink-0 font-mono font-semibold text-brand-700">schema:name</span>
                        <span class="break-all text-slate-600">{{ $car->label }}</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="w-28 shrink-0 font-mono font-semibold text-brand-700">schema:offers</span>
                        <span class="break-all text-slate-600">Offer · {{ $car->priceLabel() }} {{ config('semantic.currency') }}</span>
                    </li>
                    <li class="flex gap-2">
                        <span class="w-28 shrink-0 font-mono font-semibold text-brand-700">schema:model</span>
                        <span class="break-all text-slate-600">{{ $car->model }}</span>
                    </li>
                </ul>
                <p class="prose-note mt-4">
                    Prefiks <span class="font-mono text-xs">mb:</span> menunjukkan IRI lokal dari graf, sedangkan
                    <span class="font-mono text-xs">xsd:</span> dipakai untuk tipe data numerik.
                </p>
            </section>

            <section class="card overflow-hidden" aria-labelledby="rdfa-sumber-heading">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 id="rdfa-sumber-heading" class="text-base font-bold text-slate-900">Cuplikan markup</h2>
                    <p class="prose-note mt-1">Pola yang sama dipakai di halaman detail iklan dan halaman triple.</p>
                </div>
                <pre class="overflow-x-auto p-5 font-mono text-[11px] leading-relaxed text-slate-700"><code>@verbatim
&lt;div vocab="https://schema.org/"
     prefix="xsd: http://www.w3.org/2001/XMLSchema# mb: ..."
     typeof="Vehicle"
     resource="...#Mobil_5"&gt;
  &lt;meta property="name" content="BMW 318i (1996)"&gt;
  &lt;meta property="model" content="318i"&gt;
  &lt;div property="offers" typeof="Offer"&gt;
    &lt;meta property="price" content="85000000" datatype="xsd:integer"&gt;
    &lt;meta property="priceCurrency" content="IDR"&gt;
  &lt;/div&gt;
&lt;/div&gt;
@endverbatim</code></pre>
            </section>
        </div>
    </div>
@endsection
