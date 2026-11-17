@extends('layouts.app')

@section('title', 'Masuk — '.config('semantic.name'))

@section('konten')
    <div class="mx-auto max-w-md">
        <div class="card p-8">
            <h1 class="text-2xl font-bold tracking-tight">Masuk ke akun Anda</h1>
            <p class="prose-note mt-1">Untuk memasang iklan, menghubungi penjual, dan menyimpan favorit.</p>

            <form method="POST" action="{{ route('masuk') }}" class="mt-6 space-y-4">
                @csrf

                <div>
                    <label for="email" class="label">Email</label>
                    <input id="email" name="email" type="email" required autofocus autocomplete="email"
                        value="{{ old('email') }}"
                        class="field @error('email') border-rose-300 @enderror">
                    @error('email')
                        <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label for="password" class="label">Kata sandi</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                        class="field @error('password') border-rose-300 @enderror">
                    @error('password')
                        <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>
                    @enderror
                </div>

                <label class="flex items-center gap-2.5 text-sm text-slate-700">
                    <input type="checkbox" name="remember" value="1" class="size-4 rounded border-slate-300 text-brand-600 focus:ring-brand-500">
                    Ingat saya di perangkat ini
                </label>

                <button type="submit" class="btn-primary w-full">Masuk</button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-600">
                Belum punya akun?
                <a href="{{ route('daftar') }}" class="font-semibold text-brand-700 hover:underline">Daftar gratis</a>
            </p>
        </div>

        <div class="card mt-4 border-dashed bg-brand-50/50 p-5">
            <p class="text-xs font-semibold text-brand-900">Akun demo</p>
            <p class="mt-1 text-xs text-slate-600">
                Email <code class="rounded bg-white px-1 py-0.5">olx-import@localhost</code>,
                kata sandi <code class="rounded bg-white px-1 py-0.5">password</code>.
                Akun ini memegang seluruh 914 iklan impor.
            </p>
        </div>
    </div>
@endsection
