@extends('layouts.app')

@section('title', 'Katalog sedang tidak tersedia')
@section('meta-description', 'Data mobil belum dapat dimuat. Silakan coba lagi beberapa saat lagi.')

@section('konten')
    <div class="mx-auto max-w-2xl">
        <div class="card overflow-hidden">
            <div class="bg-rose-600 px-6 py-8 text-white">
                <p class="text-[11px] font-semibold tracking-wide text-rose-100 uppercase">503 · Layanan katalog</p>
                <h1 class="mt-2 text-2xl font-black tracking-tight">Katalog sedang tidak tersedia</h1>
                <p class="mt-2 text-sm text-rose-50">
                    Kami belum bisa memuat daftar mobil saat ini. Coba muat ulang halaman beberapa saat lagi.
                </p>
            </div>

            <div class="space-y-5 p-6">
                <div class="flex flex-wrap gap-2">
                    <a href="{{ url()->current() }}" class="btn-primary">Coba lagi</a>
                    <a href="{{ route('beranda') }}" class="btn-secondary">Kembali ke beranda</a>
                </div>

                <details class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <summary class="cursor-pointer text-sm font-semibold text-slate-900">Detail teknis</summary>

                    <div class="mt-4 space-y-4">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">Pesan dari klien</p>
                            <pre class="mt-2 overflow-x-auto rounded-xl bg-slate-950 p-4 font-mono text-xs whitespace-pre-wrap text-slate-100">{{ $message }}</pre>
                        </div>

                        <div>
                            <p class="text-sm font-semibold text-slate-900">Langkah perbaikan</p>
                            <ol class="mt-2 list-inside list-decimal space-y-1.5 text-sm text-slate-600">
                                <li>Pastikan Fuseki berjalan di <span class="font-mono text-xs">{{ config('fuseki.base_url') }}</span>.</li>
                                <li>Periksa dataset <span class="font-mono text-xs">{{ config('fuseki.dataset') }}</span> lewat panel admin Fuseki.</li>
                                <li>Muat ulang data dengan perintah di bawah bila graf kosong.</li>
                            </ol>
                        </div>

                        <div>
                            <p class="text-sm font-semibold text-slate-900">Perintah terminal</p>
                            <pre class="mt-2 overflow-x-auto rounded-xl bg-slate-950 p-4 font-mono text-xs text-slate-100"><code>php artisan fuseki:load --check
php artisan fuseki:load --replace</code></pre>
                        </div>
                    </div>
                </details>
            </div>
        </div>
    </div>
@endsection
