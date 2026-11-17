@extends('layouts.app')

@section('title', 'Pasang iklan baru — '.config('semantic.name'))

@section('konten')
    <div class="mx-auto max-w-4xl">
        <nav aria-label="Remah roti" class="mb-4 text-sm text-slate-500">
            <ol class="flex flex-wrap items-center gap-1.5">
                <li><a href="{{ route('iklan.saya') }}" class="hover:text-brand-700 hover:underline">Iklan saya</a></li>
                <li aria-hidden="true">/</li>
                <li class="font-medium text-slate-700" aria-current="page">Pasang iklan</li>
            </ol>
        </nav>

        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold tracking-tight">Pasang iklan baru</h1>
                <p class="prose-note mt-1">Setelah disimpan, iklan langsung tampil di katalog.</p>
            </div>
        </div>

        @if ($errors->any())
            <div role="alert" class="mt-6 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
                <p class="font-semibold">Periksa kembali isian berikut:</p>
                <ul class="mt-2 list-disc space-y-0.5 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form id="form-iklan" method="POST" action="{{ route('iklan.store') }}" enctype="multipart/form-data" class="mt-6 space-y-6">
            @csrf

            @include('listings._form', ['car' => new App\Models\Car, 'cities' => $cities])

            <div class="card p-6 sm:p-8">
                <h2 class="text-sm font-bold text-slate-900">Foto</h2>
                <p class="prose-note mt-1">Maksimal 8 foto, 4 MB per foto, format JPG, PNG, atau WebP. Minimal satu foto wajib ada.</p>

                <div class="mt-4">
                    <label for="field-photos" class="sr-only">Pilih foto</label>
                    <input id="field-photos" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple
                        class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-600 file:px-3.5 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-brand-700">
                    <p class="mt-2 text-xs text-slate-500">Sistem membuat placeholder otomatis bila Anda memilih tidak memasang foto.</p>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('iklan.saya') }}" class="btn-secondary">Batal</a>
                <button type="submit" class="btn-primary">Terbitkan iklan</button>
            </div>
        </form>
    </div>
@endsection
