@extends('layouts.app')

@section('title', 'Ubah '.$car->display_name.' — '.config('semantic.name'))

@section('konten')
    <div class="mx-auto max-w-4xl">
        <nav aria-label="Remah roti" class="mb-4 text-sm text-slate-500">
            <ol class="flex flex-wrap items-center gap-1.5">
                <li><a href="{{ route('iklan.saya') }}" class="hover:text-brand-700 hover:underline">Iklan saya</a></li>
                <li aria-hidden="true">/</li>
                <li><a href="{{ route('mobil.show', $car) }}" class="hover:text-brand-700 hover:underline">{{ $car->display_name }}</a></li>
                <li aria-hidden="true">/</li>
                <li class="font-medium text-slate-700" aria-current="page">Ubah</li>
            </ol>
        </nav>

        <h1 class="text-3xl font-bold tracking-tight">Ubah iklan</h1>
        <p class="prose-note mt-1">Perubahan langsung diterapkan ke katalog publik.</p>

        @if (session('status'))
            <div role="status" class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

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

        <form id="form-iklan" method="POST" action="{{ route('iklan.edit', $car) }}" enctype="multipart/form-data" class="mt-6 space-y-6">
            @csrf
            @method('PUT')

            @include('listings._form', ['car' => $car, 'cities' => $cities])

            <div class="card p-6 sm:p-8">
                <h2 class="text-sm font-bold text-slate-900">Tambah foto</h2>
                <p class="prose-note mt-1">Maksimal 8 foto baru per sekali unggah.</p>

                <div class="mt-4">
                    <label for="field-photos" class="sr-only">Pilih foto</label>
                    <input id="field-photos" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple
                        class="block w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-700 file:mr-4 file:rounded-lg file:border-0 file:bg-brand-600 file:px-3.5 file:py-2 file:text-sm file:font-semibold file:text-white hover:file:bg-brand-700">
                </div>

                @if ($car->images->isNotEmpty())
                    <div class="mt-5">
                        <p class="text-xs font-semibold text-slate-700">Foto saat ini</p>
                        <div class="mt-2 flex flex-wrap gap-3">
                            @foreach ($car->images as $photo)
                                <figure class="w-32">
                                    <img src="{{ $photo->url() }}" alt="Foto {{ $loop->iteration }} dari {{ $car->display_name }}"
                                        class="aspect-[4/3] w-32 rounded-xl object-cover ring-1 ring-slate-200" loading="lazy">
                                    <figcaption class="mt-1.5">
                                        <x-form.delete-button :action="route('iklan.foto.destroy', [$car, $photo])"
                                            label="Hapus foto {{ $loop->iteration }}"
                                            confirm="Hapus foto ini dari iklan?" />
                                    </figcaption>
                                </figure>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex flex-wrap items-center justify-end gap-3">
                <a href="{{ route('mobil.show', $car) }}" class="btn-secondary">Batal</a>
                <button type="submit" class="btn-primary">Simpan perubahan</button>
            </div>
        </form>

        <div class="card mt-6 border-rose-200 bg-rose-50/40 p-6 sm:p-8">
            <h2 class="text-sm font-bold text-rose-900">Hapus iklan</h2>
            <p class="mt-1 text-sm text-slate-600">
                Iklan akan dihapus dari katalog. Tindakan ini tidak dapat dibatalkan.
            </p>
            <div class="mt-4">
                <x-form.delete-button :action="route('iklan.destroy', $car)"
                    label="Hapus iklan ini" confirm="Hapus '{{ $car->display_name }}' secara permanen?"
                    class="btn-danger" />
            </div>
        </div>
    </div>
@endsection
