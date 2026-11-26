@extends('layouts.app')

@section('title', $car->label.' — '.$car->priceLabel())
@section('meta-description', $car->summary().'. '.$car->label.' di '.$car->location.' seharga '.$car->priceLabel().'.')

@section('konten')
    @php
        $isFavorite = in_array($car->id, session('favorit', []), true);
        $placeholder = asset('images/car-placeholder.svg');
        $image = filled($car->imageUrl) ? $car->imageUrl : $placeholder;
        $whatsappUrl = $car->whatsappUrl(route('mobil.show', ['car' => $car->id]));
    @endphp

    <div vocab="https://schema.org/"
         prefix="xsd: http://www.w3.org/2001/XMLSchema# mb: {{ config('semantic.namespaces.mb') }}"
         typeof="Vehicle"
         resource="{{ $car->uri }}">
        @include('partials.rdfa-facts', ['car' => $car])

    <nav aria-label="Breadcrumb" class="text-sm text-slate-500">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route('beranda') }}" class="hover:text-brand-700">Beranda</a></li>
            <li aria-hidden="true">/</li>
            <li><a href="{{ route('mobil.index') }}" class="hover:text-brand-700">Katalog mobil</a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page" class="truncate font-medium text-slate-700">{{ $car->label }}</li>
        </ol>
    </nav>

    <div class="mt-4 grid gap-8 lg:grid-cols-[minmax(0,1.05fr)_minmax(0,0.95fr)]">
        <div>
            <figure class="card overflow-hidden">
                <div class="relative aspect-[4/3] bg-slate-100">
                    <img src="{{ $image }}" alt="Foto {{ $car->label }}" class="h-full w-full object-cover" referrerpolicy="no-referrer"
                        onerror="this.onerror=null;this.src='{{ $placeholder }}'">

                    <span class="absolute top-4 left-4 rounded-full bg-brand-950/85 px-3 py-1.5 text-xs font-semibold text-white backdrop-blur">
                        {{ $car->bodyType }}
                    </span>

                    @if ($car->discountLabel())
                        <span class="absolute top-4 right-4 rounded-full bg-rose-600 px-3 py-1.5 text-xs font-bold text-white shadow">
                            {{ $car->discountLabel() }}
                        </span>
                    @endif
                </div>
                <figcaption class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 text-xs text-slate-500">
                    <span>Foto iklan</span>
                    <span>{{ $car->imageUrl ? 'Tautan eksternal' : 'Belum ada foto' }}</span>
                </figcaption>
            </figure>

            <section class="card mt-6 p-6" aria-labelledby="spesifikasi-heading">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <h2 id="spesifikasi-heading" class="text-lg font-bold tracking-tight text-slate-900">Spesifikasi</h2>
                </div>

                <dl class="mt-4 grid gap-x-8 gap-y-0 sm:grid-cols-2">
                    @foreach ($car->specificationRows() as $label => $value)
                        <div class="flex items-baseline justify-between gap-4 border-b border-slate-100 py-2.5 text-sm">
                            <dt class="text-slate-500">{{ $label }}</dt>
                            <dd class="text-right font-medium text-slate-900">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        </div>

        <div>
            <div class="card p-6">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="chip-brand">{{ $car->brand }}</span>
                    <span class="chip-neutral">{{ $car->yearLabel() }}</span>
                    <span class="chip-neutral">{{ $car->transmission }}</span>
                    <span class="chip-neutral">{{ $car->drivetrain }}</span>
                    <span class="chip-neutral">{{ $car->fuel }}</span>
                </div>

                <h1 class="mt-4 text-2xl leading-tight font-black tracking-tight text-slate-900 sm:text-3xl">{{ $car->label }}</h1>
                <p class="mt-2 text-sm text-slate-500">{{ $car->summary() }}</p>

                <div class="mt-5 rounded-2xl bg-brand-50 p-4 ring-1 ring-brand-100">
                    <p class="text-xs font-semibold tracking-wide text-brand-700 uppercase">Harga iklan</p>
                    <p class="mt-1 text-3xl font-black tracking-tight text-brand-900">{{ $car->priceLabel() }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ $car->postedAt ? 'Diposting '.$car->postedLabel().' · '.$car->postedDiff() : 'Tanggal posting tidak tersedia' }}</p>
                </div>

                @if ($car->hasFinancing())
                    <div class="mt-4 rounded-2xl border border-slate-200 p-4">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-bold text-slate-900">Simulasi cicilan</p>
                            <span class="chip-verified">Pembiayaan tersedia</span>
                        </div>
                        <dl class="mt-3 grid gap-3 sm:grid-cols-3">
                            <div>
                                <dt class="text-[11px] tracking-wide text-slate-500 uppercase">Angsuran</dt>
                                <dd class="text-sm font-bold text-slate-900">{{ $car->installmentLabel() ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] tracking-wide text-slate-500 uppercase">Uang muka</dt>
                                <dd class="text-sm font-bold text-slate-900">{{ $car->downPaymentLabel() ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-[11px] tracking-wide text-slate-500 uppercase">Tenor</dt>
                                <dd class="text-sm font-bold text-slate-900">{{ $car->termLabel() ?? '-' }}</dd>
                            </div>
                        </dl>
                        @if ($car->termText && $car->termMonths)
                            <p class="mt-2 text-xs text-slate-500">Teks sumber: <span class="font-mono">{{ $car->termText }}</span></p>
                        @endif
                    </div>
                @endif

                @if ($car->perks() !== [])
                    <div class="mt-4">
                        <p class="text-sm font-bold text-slate-900">Layanan penjual</p>
                        <ul class="mt-2 grid gap-2 sm:grid-cols-2">
                            @foreach ($car->perks() as $perk)
                                <li class="flex items-start gap-2 rounded-xl bg-slate-50 px-3 py-2 text-sm">
                                    <span class="mt-0.5 text-emerald-600" aria-hidden="true">✓</span>
                                    <span><span class="font-medium text-slate-800">{{ $perk['label'] }}</span>: <span class="text-slate-600">{{ $perk['value'] }}</span></span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="mt-6 flex flex-wrap gap-2">
                    <form method="POST" action="{{ $isFavorite ? route('favorit.destroy', ['car' => $car->id]) : route('favorit.store', ['car' => $car->id]) }}">
                        @csrf
                        @if ($isFavorite)
                            @method('DELETE')
                        @endif
                        <button type="submit" class="{{ $isFavorite ? 'btn-danger' : 'btn-primary' }}">
                            <span aria-hidden="true">{{ $isFavorite ? '♥' : '♡' }}</span>
                            {{ $isFavorite ? 'Hapus dari favorit' : 'Simpan ke favorit' }}
                        </button>
                    </form>

                    @if ($whatsappUrl !== null)
                        <a href="{{ $whatsappUrl }}" class="btn bg-emerald-600 text-white hover:bg-emerald-700"
                           target="_blank" rel="noopener noreferrer">
                            <span aria-hidden="true">💬</span>
                            Tanya via WhatsApp
                        </a>
                    @endif
                </div>

                <p class="mt-4 text-xs text-slate-500">
                    Lokasi: <span class="font-medium text-slate-700">{{ $car->location }}</span>
                </p>
            </div>

        </div>
    </div>

    @include('partials.car-map', ['car' => $car])

    @if ($related->isNotEmpty())
        <section class="mt-12" aria-labelledby="terkait-heading">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="terkait-heading" class="text-xl font-bold tracking-tight text-slate-900">Mobil {{ $car->brand }} lainnya</h2>
                    <p class="prose-note mt-1">Iklan lain dengan merek yang sama.</p>
                </div>
                <a href="{{ route('mobil.index', ['brand' => [$car->brand]]) }}" class="text-sm font-semibold text-brand-700 hover:underline">Semua {{ $car->brand }} →</a>
            </div>

            <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($related as $item)
                    <x-car-card :car="$item" />
                @endforeach
            </div>
        </section>
    @endif
    </div>
@endsection
