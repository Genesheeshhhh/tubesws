@props(['car', 'compact' => false])

@php
    $isFavorite = in_array($car->id, session('favorit', []), true);
    $placeholder = asset('images/car-placeholder.svg');
    $image = filled($car->imageUrl) ? $car->imageUrl : $placeholder;
@endphp

<article class="card group flex h-full flex-col overflow-hidden transition hover:-translate-y-0.5 hover:border-brand-300 hover:shadow-md">
    <a href="{{ route('mobil.show', ['car' => $car->id]) }}" class="relative block aspect-[4/3] overflow-hidden bg-slate-100">
        <img src="{{ $image }}" alt="Foto {{ $car->label }}" loading="lazy" decoding="async" referrerpolicy="no-referrer"
            class="h-full w-full object-cover transition duration-500 group-hover:scale-105"
            onerror="this.onerror=null;this.src='{{ $placeholder }}'">

        <span class="absolute top-3 left-3 rounded-full bg-brand-950/85 px-2.5 py-1 text-[11px] font-semibold text-white backdrop-blur">
            {{ $car->brand }}
        </span>

        @if ($car->discountLabel())
            <span class="absolute top-3 right-3 rounded-full bg-rose-600 px-2.5 py-1 text-[11px] font-bold text-white shadow">
                {{ $car->discountLabel() }}
            </span>
        @endif

        @if ($car->hasFinancing())
            <span class="absolute bottom-3 left-3 chip bg-white/95 text-brand-800 shadow">
                Cicilan {{ $car->installmentLabel() }}
            </span>
        @endif
    </a>

    <div class="flex flex-1 flex-col gap-3 p-4">
        <div>
            <h3 class="text-base leading-snug font-semibold text-slate-900">
                <a href="{{ route('mobil.show', ['car' => $car->id]) }}" class="hover:text-brand-700">
                    {{ $car->label }}
                </a>
            </h3>
            <p class="mt-1 text-sm text-slate-500">{{ $car->summary() }}</p>
        </div>

        <div class="flex flex-wrap gap-1.5">
            <span class="chip-brand">{{ $car->bodyType }}</span>
            <span class="chip-neutral">{{ $car->transmission }}</span>
            <span class="chip-neutral">{{ $car->fuel }}</span>
        </div>

        <div class="mt-auto flex items-end justify-between gap-3 border-t border-slate-100 pt-3">
            <div>
                <p class="text-lg font-bold tracking-tight text-brand-800">{{ $car->priceLabel() }}</p>
                <p class="text-xs text-slate-500">{{ $car->location }} · {{ $car->postedDiff() }}</p>
            </div>

            <form method="POST" action="{{ $isFavorite ? route('favorit.destroy', ['car' => $car->id]) : route('favorit.store', ['car' => $car->id]) }}">
                @csrf
                @if ($isFavorite)
                    @method('DELETE')
                @endif
                <button type="submit"
                    class="grid h-10 w-10 place-items-center rounded-full border text-lg transition {{ $isFavorite ? 'border-rose-300 bg-rose-50 text-rose-600' : 'border-slate-200 bg-white text-slate-400 hover:border-rose-300 hover:text-rose-500' }}"
                    aria-label="{{ $isFavorite ? 'Hapus '.$car->label.' dari favorit' : 'Simpan '.$car->label.' ke favorit' }}"
                    title="{{ $isFavorite ? 'Hapus dari favorit' : 'Simpan ke favorit' }}">
                    <span aria-hidden="true">{{ $isFavorite ? '♥' : '♡' }}</span>
                </button>
            </form>
        </div>
    </div>
</article>
