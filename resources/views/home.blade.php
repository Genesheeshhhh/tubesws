@extends('layouts.app')

@section('title', config('semantic.name').' — Katalog mobil bekas Medan')
@section('meta-description', 'Jelajahi '.number_format($statistics['total']).' mobil bekas di Medan. Bandingkan merek, lokasi, harga, dan skema cicilan untuk menemukan mobil yang tepat.')
@section('og-title', 'Mobil Bekas Medan — Katalog Mobil Bekas')
@section('og-description', 'Temukan dan bandingkan mobil bekas di Medan berdasarkan harga, merek, lokasi, dan spesifikasi.')

@section('konten')
    <div vocab="https://schema.org/" typeof="WebSite">
        <meta property="name" content="{{ config('semantic.name') }}">
        <meta property="description" content="Katalog mobil bekas di Medan dengan informasi harga, lokasi, merek, dan cicilan.">
        <meta property="inLanguage" content="id">

        {{-- Seluruh konten homepage yang sudah ada tetap di sini --}}
    @php
        $highest = (int) $statistics['highest'];
        $highestLabel = $highest >= 1_000_000_000
            ? 'Rp '.number_format($highest / 1_000_000_000, 1, ',', '.').' M'
            : 'Rp '.number_format($highest / 1_000_000, 0, ',', '.').' jt';
        $lowest = (int) $statistics['lowest'];
    @endphp

    <section class="relative overflow-hidden rounded-3xl bg-brand-950 px-6 py-10 text-white shadow-xl shadow-brand-950/20 sm:px-10 sm:py-14">
        <div class="pointer-events-none absolute -top-24 -right-16 h-72 w-72 rounded-full bg-brand-500/30 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-28 -left-10 h-64 w-64 rounded-full bg-brand-400/20 blur-3xl" aria-hidden="true"></div>

        <div class="relative grid gap-10 lg:grid-cols-[1.15fr_0.85fr] lg:items-end">
            <div>
                <span class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-xs font-semibold tracking-wide text-brand-100 ring-1 ring-white/15">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                    {{ number_format($statistics['total']) }} iklan aktif di Medan
                </span>

                <h1 class="mt-4 max-w-2xl text-3xl leading-tight font-black tracking-tight sm:text-5xl">
                    Mobil bekas Medan,<br>
                    <span class="text-brand-300">lebih mudah ditemukan.</span>
                </h1>

                <p class="mt-4 max-w-xl text-sm leading-relaxed text-brand-100/90 sm:text-base">
                    Bandingkan merek, lokasi, harga, dan skema cicilan dalam satu tempat. Simpan mobil favorit, lalu hubungi penjual saat sudah menemukan pilihan yang tepat.
                </p>

                <div class="mt-6 flex flex-wrap gap-2 text-xs">
                    <a class="rounded-full bg-white/10 px-3 py-1.5 text-brand-100 ring-1 ring-white/15 transition hover:bg-white/20" href="{{ route('mobil.index', ['with_financing' => 1]) }}">Bisa dicicil</a>
                    <a class="rounded-full bg-white/10 px-3 py-1.5 text-brand-100 ring-1 ring-white/15 transition hover:bg-white/20" href="{{ route('mobil.index', ['sort' => 'harga_terendah']) }}">Termurah</a>
                    <a class="rounded-full bg-white/10 px-3 py-1.5 text-brand-100 ring-1 ring-white/15 transition hover:bg-white/20" href="{{ route('mobil.index', ['sort' => 'tahun_terbaru']) }}">Tahun terbaru</a>
                    <a class="rounded-full bg-white/10 px-3 py-1.5 text-brand-100 ring-1 ring-white/15 transition hover:bg-white/20" href="{{ route('mobil.index', ['with_image' => 1]) }}">Ada foto</a>
                </div>
            </div>

            <dl class="grid grid-cols-2 gap-3 text-brand-950">
                <div class="rounded-2xl bg-white p-4">
                    <dt class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase">Iklan aktif</dt>
                    <dd class="mt-1 text-3xl font-black">{{ number_format($statistics['total']) }}</dd>
                </div>
                <div class="rounded-2xl bg-white p-4">
                    <dt class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase">Merek</dt>
                    <dd class="mt-1 text-3xl font-black">{{ number_format($statistics['brands']) }}</dd>
                </div>
                <div class="rounded-2xl bg-white p-4">
                    <dt class="text-[11px] font-semibold tracking-wide text-slate-500 uppercase">Lokasi</dt>
                    <dd class="mt-1 text-3xl font-black">{{ number_format($statistics['locations']) }}</dd>
                </div>
                <div class="rounded-2xl bg-brand-500 p-4 text-white">
                    <dt class="text-[11px] font-semibold tracking-wide text-brand-50 uppercase">Harga tertinggi</dt>
                    <dd class="mt-1 text-2xl font-black">{{ $highestLabel }}</dd>
                </div>
            </dl>
        </div>
    </section>

    <section class="mt-10" aria-labelledby="merek-heading">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 id="merek-heading" class="text-xl font-bold tracking-tight text-slate-900">Merek paling banyak</h2>
                <p class="prose-note mt-1">Pilih merek favorit Anda untuk melihat iklan yang tersedia.</p>
            </div>
            <a href="{{ route('mobil.index') }}" class="text-sm font-semibold text-brand-700 hover:underline">Lihat semua mobil →</a>
        </div>

        
    <div class="mt-4 flex flex-wrap gap-2">
        @foreach ($brandFacets as $facet)
            <a href="{{ route('mobil.index', ['brand' => [$facet['value']]]) }}"
                class="inline-flex items-center rounded-full border border-slate-200 bg-white px-4 py-2 text-sm font-medium text-slate-700 shadow-sm transition hover:border-brand-300 hover:text-brand-700">
                {{ $facet['label'] }}
            </a>
        @endforeach
    </div>

    </section>

    @if ($featured->isNotEmpty())
        <section class="mt-12" aria-labelledby="unggulan-heading">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="unggulan-heading" class="text-xl font-bold tracking-tight text-slate-900">Pilihan dengan foto &amp; cicilan</h2>
                    <p class="prose-note mt-1">Iklan terbaru yang punya tautan foto sekaligus skema pembiayaan.</p>
                </div>
                <a href="{{ route('mobil.index', ['with_financing' => 1, 'with_image' => 1]) }}" class="text-sm font-semibold text-brand-700 hover:underline">Semua yang bisa dicicil →</a>
            </div>

            <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($featured as $car)
                    <x-car-card :car="$car" />
                @endforeach
            </div>
        </section>
    @endif

    <section class="mt-12" aria-labelledby="terbaru-heading">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                    <h2 id="terbaru-heading" class="text-xl font-bold tracking-tight text-slate-900">Iklan terbaru</h2>
                    <p class="prose-note mt-1">Iklan yang baru ditambahkan, diurutkan dari yang paling baru.</p>
            </div>
            <a href="{{ route('mobil.index', ['sort' => 'terbaru']) }}" class="text-sm font-semibold text-brand-700 hover:underline">Lihat semua {{ number_format($statistics['total']) }} iklan →</a>
        </div>

        <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($latest as $car)
                <x-car-card :car="$car" />
            @endforeach
        </div>
    </section>

    <section class="mt-12 grid gap-6 lg:grid-cols-[1.2fr_0.8fr]" aria-labelledby="lokasi-heading">
        <div class="card p-6">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="lokasi-heading" class="text-lg font-bold tracking-tight text-slate-900">Lokasi di sekitar Medan</h2>
                    <p class="prose-note mt-1">Pilih kecamatan yang paling dekat dengan Anda.</p>
                </div>
            </div>

            <ul class="mt-4 grid gap-2 sm:grid-cols-2">
                @foreach ($locationFacets as $facet)
                    <li>
                        <a href="{{ route('mobil.index', ['location' => [$facet['value']]]) }}"
                            class="flex items-center justify-between gap-3 rounded-xl border border-slate-200 px-3.5 py-2.5 text-sm transition hover:border-brand-300 hover:bg-brand-50/40">
                            <span class="font-medium text-slate-700">{{ $facet['label'] }}</span>
                            <span class="text-xs font-semibold text-slate-500">{{ $facet['count'] }} iklan</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="card overflow-hidden">
            <div class="bg-brand-900 p-6 text-white">
                <p class="text-[11px] font-semibold tracking-wide text-brand-200 uppercase">Siap dipakai</p>
                <p class="mt-2 text-4xl font-black">{{ number_format($statistics['with_image']) }}</p>
                <p class="text-sm text-brand-100">iklan sudah dilengkapi foto</p>
            </div>
            <div class="space-y-4 p-6">
                <dl class="grid grid-cols-2 gap-3 text-sm">
                    <div class="rounded-xl bg-slate-50 p-3">
                        <dt class="text-xs text-slate-500">Bisa dicicil</dt>
                        <dd class="text-lg font-bold text-slate-900">{{ number_format($statistics['with_financing']) }}</dd>
                    </div>
                    <div class="rounded-xl bg-slate-50 p-3">
                        <dt class="text-xs text-slate-500">Merek</dt>
                        <dd class="text-lg font-bold text-slate-900">{{ number_format($statistics['brands']) }}</dd>
                    </div>
                </dl>
                <p class="prose-note">Gunakan pencarian dan filter untuk mempersempit pilihan. Tekan tombol hati pada iklan untuk menyimpannya ke favorit.</p>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('mobil.index') }}" class="btn-primary">Mulai cari mobil</a>
                    <a href="{{ route('favorit.index') }}" class="btn-secondary">Lihat favorit</a>
                </div>
            </div>
        </div>
    </section>

    @if ($financing->isNotEmpty())
        <section class="mt-12" aria-labelledby="cicilan-heading">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 id="cicilan-heading" class="text-xl font-bold tracking-tight text-slate-900">Siap dicicil</h2>
                    <p class="prose-note mt-1">Lihat perkiraan angsuran, uang muka, dan tenor untuk membantu menyusun anggaran.</p>
                </div>
            </div>
            <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($financing as $car)
                    <x-car-card :car="$car" />
                @endforeach
            </div>
        </section>
    @endif
    </div>
@endsection
