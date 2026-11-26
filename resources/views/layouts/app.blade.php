@php
    $description = $description ?? 'Temukan mobil bekas berkualitas di Medan dengan harga, lokasi, dan informasi cicilan yang jelas.';
    $favoriteCount = count(session('favorit', []));
    $navigation = [
        ['label' => 'Beranda', 'route' => 'beranda', 'active' => 'beranda'],
        ['label' => 'Katalog', 'route' => 'mobil.index', 'active' => 'mobil.*'],
    ];
@endphp
<!DOCTYPE html>
<html lang="id" class="h-full scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', config('semantic.name'))</title>
    <meta name="description" content="@yield('meta-description', $description)">
    <meta name="theme-color" content="#1e3a8a">

    @foreach (($jsonLd ?? []) as $graph)
        <script type="application/ld+json">@json($graph, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
    @endforeach

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="flex min-h-full flex-col bg-slate-50 font-sans text-slate-900 antialiased">
    <a href="#konten" class="sr-only focus:not-sr-only focus:absolute focus:top-3 focus:left-3 focus:z-50 focus:rounded-xl focus:bg-white focus:px-4 focus:py-2 focus:shadow-lg">
        Lewati ke konten utama
    </a>

    <header class="sticky top-0 z-40 border-b border-brand-800/50 bg-brand-950 text-white shadow-lg shadow-brand-950/10">
        <div class="mx-auto max-w-7xl px-4 sm:px-6">
            <div class="flex flex-wrap items-center gap-x-6 gap-y-3 py-3">
                <a href="{{ route('beranda') }}" class="group flex items-center gap-3">
                    <span class="grid h-10 w-10 place-items-center rounded-2xl bg-brand-500 text-base font-black tracking-tight shadow-inner" aria-hidden="true">MB</span>
                    <span class="leading-tight">
                        <span class="block text-base font-bold tracking-tight">{{ config('semantic.name') }}</span>
                        <span class="block text-[11px] font-medium text-brand-300">Mobil bekas pilihan di Medan</span>
                    </span>
                </a>

                <nav aria-label="Navigasi utama" class="order-3 w-full sm:order-none sm:w-auto sm:flex-1">
                    <ul class="flex flex-wrap items-center gap-1 text-sm font-medium">
                        @foreach ($navigation as $item)
                            <li>
                                <a href="{{ route($item['route']) }}"
                                    @class([
                                        'rounded-xl px-3 py-2 transition',
                                        'bg-brand-800 text-white shadow-sm' => request()->routeIs($item['active']),
                                        'text-brand-100 hover:bg-brand-900 hover:text-white' => ! request()->routeIs($item['active']),
                                    ])>{{ $item['label'] }}</a>
                            </li>
                        @endforeach
                        <li>
                            <a href="{{ route('favorit.index') }}"
                                @class([
                                    'flex items-center gap-2 rounded-xl px-3 py-2 transition',
                                    'bg-brand-800 text-white shadow-sm' => request()->routeIs('favorit.*'),
                                    'text-brand-100 hover:bg-brand-900 hover:text-white' => ! request()->routeIs('favorit.*'),
                                ])>
                                Favorit
                                @if ($favoriteCount > 0)
                                    <span class="rounded-full bg-brand-500 px-2 py-0.5 text-[11px] font-bold text-white">{{ $favoriteCount }}</span>
                                @endif
                            </a>
                        </li>
                        <li>
                            <a href="{{ route('semantik.index') }}"
                                @class([
                                    'ml-1 inline-flex items-center gap-1.5 rounded-xl px-3 py-1.5 text-sm font-semibold transition',
                                    'bg-brand-700 text-white ring-1 ring-brand-400' => request()->routeIs('semantik.*'),
                                    'bg-brand-800 text-brand-50 ring-1 ring-brand-700 hover:bg-brand-700' => ! request()->routeIs('semantik.*'),
                                ])
                                @if (request()->routeIs('semantik.*')) aria-current="page" @endif>
                                <span aria-hidden="true">◈</span>
                                Web Semantik
                            </a>
                        </li>
                    </ul>
                </nav>

                <form method="GET" action="{{ route('mobil.index') }}" role="search" class="ml-auto flex w-full items-center gap-2 sm:w-auto">
                    <label for="global-q" class="sr-only">Cari mobil</label>
                    <input id="global-q" name="q" type="search" value="{{ request()->routeIs('mobil.index') ? request('q') : '' }}"
                        placeholder="Cari Avanza, Toyota, Medan…"
                        class="field min-w-0 flex-1 border-white/10 bg-brand-900/70 text-white placeholder:text-brand-300 focus:border-brand-400 focus:ring-brand-500/40 sm:w-72 sm:flex-none">
                    <button type="submit" class="btn shrink-0 bg-brand-500 px-4 text-white hover:bg-brand-400">Cari</button>
                </form>
            </div>
        </div>
    </header>

    <main id="konten" class="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6">
        @include('partials.flash')

        @yield('konten')
    </main>

    <footer class="mt-10 border-t border-slate-200 bg-white">
        <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:px-6 md:grid-cols-3">
            <div class="md:col-span-2">
                <div class="flex items-center gap-2">
                    <span class="grid h-8 w-8 place-items-center rounded-xl bg-brand-600 text-xs font-black text-white" aria-hidden="true">MB</span>
                    <p class="text-base font-bold text-brand-950">{{ config('semantic.name') }}</p>
                </div>
                <p class="prose-note mt-3 max-w-md">{{ $description }}</p>
                <p class="mt-4 text-xs text-slate-500">
                    Harga dan ketersediaan dapat berubah. Konfirmasikan detail unit langsung kepada penjual sebelum membeli.
                </p>
            </div>

            <div>
                <h2 class="text-sm font-semibold text-slate-900">Jelajahi</h2>
                <ul class="mt-3 space-y-2 text-sm">
                    <li><a class="text-brand-700 hover:underline" href="{{ route('mobil.index') }}">Semua mobil</a></li>
                    <li><a class="text-brand-700 hover:underline" href="{{ route('mobil.index', ['with_financing' => 1]) }}">Bisa dicicil</a></li>
                    <li><a class="text-brand-700 hover:underline" href="{{ route('mobil.index', ['sort' => 'harga_terendah']) }}">Harga terendah</a></li>
                    <li><a class="text-brand-700 hover:underline" href="{{ route('mobil.index', ['sort' => 'tahun_terbaru']) }}">Tahun terbaru</a></li>
                    <li><a class="text-brand-700 hover:underline" href="{{ route('favorit.index') }}">Favorit Anda</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-slate-100">
            <p class="mx-auto max-w-7xl px-4 py-4 text-xs text-slate-500 sm:px-6">
                &copy; {{ date('Y') }} {{ config('semantic.name') }}. Semua hak dilindungi.
            </p>
        </div>
    </footer>

    @include('partials.chatbot')
    @stack('scripts')
</body>
</html>
