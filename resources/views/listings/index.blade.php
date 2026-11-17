@extends('layouts.app')

@section('title', 'Iklan saya — '.config('semantic.name'))

@section('konten')
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-3xl font-bold tracking-tight">Iklan saya</h1>
            <p class="prose-note mt-1">Kelola {{ number_format($total, 0, ',', '.') }} iklan milik Anda.</p>
        </div>

        <a href="{{ route('iklan.create') }}" class="btn-primary">Pasang iklan baru</a>
    </div>

    <div class="mt-6 flex flex-wrap items-center gap-2">
        @foreach ($statusTabs as $tab)
            @php $isActive = $tab['value'] === $statusFilter; @endphp
            <a href="{{ route('iklan.saya', ['status' => $tab['value']]) }}"
                @class([
                    'chip',
                    'chip-active' => $isActive,
                    'text-slate-600 hover:border-brand-300' => ! $isActive,
                ])>
                {{ $tab['label'] }}
                <span class="ml-1 text-xs opacity-70">{{ number_format($tab['count'], 0, ',', '.') }}</span>
            </a>
        @endforeach
    </div>

    @if ($cars->isEmpty())
        <div class="card mt-6 p-10 text-center">
            <p class="text-sm font-semibold text-slate-800">
                {{ $statusFilter === 'all' ? 'Belum ada iklan.' : 'Tidak ada iklan pada filter ini.' }}
            </p>
            <p class="prose-note mt-1">Pasang iklan pertama Anda dan iklan itu langsung tampil di katalog.</p>
            <a href="{{ route('iklan.create') }}" class="btn-primary mt-6">Pasang iklan</a>
        </div>
    @else
        <div class="mt-6 space-y-4">
            @foreach ($cars as $car)
                @php
                    $primary = $car->images->first();
                    $statusTone = match ($car->status->value) {
                        'sold' => 'bg-rose-100 text-rose-700',
                        'draft' => 'bg-amber-100 text-amber-800',
                        default => 'bg-emerald-100 text-emerald-700',
                    };
                @endphp

                <article class="card flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex min-w-0 flex-1 items-center gap-4">
                        <img src="{{ $primary?->url() }}" alt="{{ $primary?->alt_text ?? $car->display_name }}" loading="lazy"
                            class="size-20 shrink-0 rounded-xl object-cover ring-1 ring-slate-200">

                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <h2 class="truncate text-base font-bold text-slate-900">
                                    <a href="{{ route('mobil.show', $car) }}" class="hover:text-brand-700 hover:underline">{{ $car->display_name }}</a>
                                </h2>
                                <span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $statusTone }}">{{ $car->status->label() }}</span>
                                @if ($car->is_verified)
                                    <span class="rounded-full bg-brand-100 px-2.5 py-1 text-xs font-semibold text-brand-700">Terverifikasi</span>
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-slate-600">
                                {{ $car->city }}
                                @if ($car->year)
                                    <span aria-hidden="true">&middot;</span> {{ $car->year }}
                                @endif
                                <span aria-hidden="true">&middot;</span> Rp{{ number_format($car->price, 0, ',', '.') }}
                            </p>
                            <p class="mt-1 text-xs text-slate-400">
                                <span aria-hidden="true">&middot;</span> {{ number_format($car->view_count, 0, ',', '.') }} kali dilihat
                                <span aria-hidden="true">&middot;</span> {{ number_format($car->favoritedBy()->count(), 0, ',', '.') }} favorit
                            </p>
                        </div>
                    </div>

                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                        <a href="{{ route('iklan.edit', $car) }}" class="btn-secondary">Ubah</a>

                        <form method="POST" action="{{ route('iklan.status', $car) }}" class="inline">
                            @csrf
                            @method('PATCH')
                            @if ($car->status->value === 'sold')
                                <input type="hidden" name="status" value="available">
                                <button type="submit" class="btn-secondary">Tandai tersedia lagi</button>
                            @else
                                <input type="hidden" name="status" value="sold">
                                <button type="submit" class="btn-secondary">Tandai terjual</button>
                            @endif
                        </form>

                        <a href="{{ route('mobil.show', $car) }}" class="btn-ghost">Lihat halaman</a>
                    </div>
                </article>
            @endforeach
        </div>

        <div class="mt-8">
            {{ $cars->links() }}
        </div>
    @endif
@endsection
