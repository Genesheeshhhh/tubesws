@php
    $chatSuggestions = [
        'Mobil di bawah 150 juta',
        'SUV matic bisa dicicil',
        'Mobil termurah',
        'Toyota Avanza',
        'Regex: Toyota|Brio',
        'Ada berapa mobil?',
    ];
@endphp

{{-- Leaflet panes use z-index 400-800, so the assistant must sit above them. --}}
<div data-chatbot data-placeholder="{{ asset('images/car-placeholder.svg') }}"
    data-chatbot-car="{{ request()->route('car') ?? '' }}"
    class="fixed inset-x-4 bottom-4 z-[1000] flex flex-col items-end gap-3 sm:inset-x-auto sm:right-6 sm:bottom-6 sm:w-[26rem]">
    <section id="asisten-panel" data-chatbot-panel aria-labelledby="asisten-title" hidden
        class="card flex max-h-[min(80vh,40rem)] w-full flex-col overflow-hidden shadow-2xl shadow-brand-950/20">
        <header class="flex items-start justify-between gap-3 border-b border-brand-800 bg-brand-950 px-4 py-3 text-white">
            <div>
                <h2 id="asisten-title" class="text-sm font-bold tracking-tight">Asisten mobil</h2>
                <p class="mt-0.5 text-xs text-brand-300">
                    Offline · menjawab dari katalog lokal
                </p>
            </div>
            <button type="button" data-chatbot-close
                class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-lg text-brand-200 transition hover:bg-white/10 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-400"
                aria-label="Tutup asisten mobil">
                <span aria-hidden="true">&times;</span>
            </button>
        </header>

        <div data-chatbot-messages role="log" aria-live="polite" aria-relevant="additions"
            class="chat-scroll min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-4">
            <div class="max-w-[85%] rounded-2xl rounded-bl-sm bg-slate-100 px-3.5 py-2.5 text-sm leading-relaxed text-slate-700">
                Halo! Saya asisten katalog mobil bekas Medan. Tanyakan budget, merek, transmisi, atau minta rekomendasi.
            </div>
        </div>

        <div data-chatbot-suggestions class="flex flex-wrap gap-1.5 px-4 pb-1">
            @foreach ($chatSuggestions as $suggestion)
                <button type="button" data-chatbot-suggestion="{{ $suggestion }}" class="chip-brand transition hover:bg-brand-100">
                    {{ $suggestion }}
                </button>
            @endforeach
        </div>

        <form data-chatbot-form action="{{ route('chatbot.reply') }}" method="POST"
            class="flex items-center gap-2 border-t border-slate-200 px-3 py-3">
            @csrf
            <label for="asisten-message" class="sr-only">Tulis pertanyaan untuk asisten</label>
            <input id="asisten-message" name="message" data-chatbot-input type="text" required maxlength="500"
                autocomplete="off" placeholder="Contoh: SUV matic di bawah 200 juta"
                class="field min-w-0 flex-1 py-2">
            <button type="submit" data-chatbot-send class="btn-primary shrink-0 px-3">Kirim</button>
        </form>
    </section>

    <button type="button" data-chatbot-toggle aria-expanded="false" aria-controls="asisten-panel"
        class="btn h-14 w-14 rounded-full bg-brand-600 p-0 text-white shadow-xl shadow-brand-900/30 hover:bg-brand-700">
        <span class="sr-only">Buka asisten mobil</span>
        <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
            <path d="M21 12a8.5 8.5 0 0 1-8.5 8.5H7l-4 2 1.2-4.1A8.5 8.5 0 1 1 21 12Z" stroke-linejoin="round"></path>
            <path d="M8 11h8M8 15h5" stroke-linecap="round"></path>
        </svg>
    </button>
</div>
