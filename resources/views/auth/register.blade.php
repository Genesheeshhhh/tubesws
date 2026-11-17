@extends('layouts.app')

@section('title', 'Daftar akun — '.config('semantic.name'))

@section('konten')
    <div class="mx-auto max-w-md">
        <div class="card p-8">
            <h1 class="text-2xl font-bold tracking-tight">Buat akun penjual</h1>
            <p class="prose-note mt-1">Gratis, dan Anda bisa memasang iklan dalam beberapa menit.</p>

            <form method="POST" action="{{ route('daftar') }}" class="mt-6 space-y-4">
                @csrf

                <div>
                    <label for="name" class="label">Nama lengkap atau nama toko</label>
                    <input id="name" name="name" type="text" required autofocus value="{{ old('name') }}"
                        class="field @error('name') border-rose-300 @enderror">
                    @error('name')
                        <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label for="email" class="label">Email</label>
                    <input id="email" name="email" type="email" required autocomplete="email" value="{{ old('email') }}"
                        class="field @error('email') border-rose-300 @enderror">
                    @error('email')
                        <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label for="phone" class="label">Nomor telepon <span class="font-normal text-slate-400">(opsional)</span></label>
                    <input id="phone" name="phone" type="tel" value="{{ old('phone') }}" placeholder="0812-3456-7890"
                        class="field @error('phone') border-rose-300 @enderror">
                    @error('phone')
                        <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label for="city" class="label">Kota</label>
                    <input id="city" name="city" type="text" required list="kota-daftar" value="{{ old('city') }}"
                        class="field @error('city') border-rose-300 @enderror">
                    <datalist id="kota-daftar">
                        @foreach (App\Models\Car::query()->visibleToPublic()->distinct()->orderBy('city')->pluck('city') as $city)
                            <option value="{{ $city }}"></option>
                        @endforeach
                    </datalist>
                    @error('city')
                        <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>
                    @enderror
                </div>

                <div>
                    <label for="password" class="label">Kata sandi</label>
                    <input id="password" name="password" type="password" required autocomplete="new-password"
                        class="field @error('password') border-rose-300 @enderror">
                    @error('password')
                        <span class="mt-1 block text-xs text-rose-600">{{ $message }}</span>
                    @else
                        <span class="mt-1 block text-xs text-slate-500">Minimal 8 karakter.</span>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="label">Ulangi kata sandi</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password" class="field">
                </div>

                <button type="submit" class="btn-primary w-full">Buat akun</button>
            </form>

            <p class="mt-6 text-center text-sm text-slate-600">
                Sudah punya akun?
                <a href="{{ route('masuk') }}" class="font-semibold text-brand-700 hover:underline">Masuk</a>
            </p>
        </div>
    </div>
@endsection
