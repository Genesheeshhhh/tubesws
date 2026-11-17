@extends('layouts.app')

@section('title', 'Favorit — '.config('semantic.name'))
@section('meta-description', 'Daftar mobil bekas Medan yang Anda simpan di sesi ini.')

@section('konten')
    <nav aria-label="Breadcrumb" class="text-sm text-slate-500">
        <ol class="flex flex-wrap items-center gap-2">
            <li><a href="{{ route('beranda') }}" class="hover:text-brand-700">Beranda</a></li>
            <li aria-hidden="true">/</li>
            <li aria-current="page" class="font-medium text-slate-700">Favorit</li>
        </ol>
    </nav>

    <div class="mt-4 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black tracking-tight text-slate-900 sm:text-3xl">Favorit Anda</h1>
            <p class="prose-note mt-1">
                {{ number_format($cars->count()) }} mobil tersimpan di peramban ini. Daftar favorit tersimpan selama sesi Anda masih aktif.
            </p>
        </div>
        <a href="{{ route('mobil.index') }}" class="btn-secondary">Lanjut jelajah katalog</a>
    </div>

    @if ($cars->isEmpty())
        <div class="card mt-6 p-10 text-center">
            <p class="text-4xl" aria-hidden="true">♡</p>
            <h2 class="mt-3 text-lg font-bold text-slate-900">Belum ada mobil favorit</h2>
            <p class="prose-note mx-auto mt-2 max-w-md">Tekan tombol hati pada kartu mobil atau halaman detail untuk menyimpannya di sini.</p>
            <a href="{{ route('mobil.index') }}" class="btn-primary mt-5">Cari mobil sekarang</a>
        </div>
    @else
        <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($cars as $car)
                <x-car-card :car="$car" />
            @endforeach
        </div>
    @endif
@endsection
