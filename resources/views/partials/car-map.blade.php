@pushOnce('styles')
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
          integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
@endpushOnce

<section class="card mt-6 p-6" aria-labelledby="lokasi-peta-heading">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 id="lokasi-peta-heading" class="text-lg font-bold tracking-tight text-slate-900">Lokasi unit</h2>
            <p class="prose-note mt-1">{{ $car->location }} · titik kecamatan, bukan alamat pasti.</p>
        </div>
        <a class="text-sm font-semibold text-brand-700 hover:underline"
           href="https://www.openstreetmap.org/search?query={{ rawurlencode($car->location.config('maps.search_suffix')) }}"
           target="_blank" rel="noopener noreferrer">Buka di OpenStreetMap →</a>
    </div>

    <div id="car-map" class="mt-4 h-72 overflow-hidden rounded-xl border border-slate-200 bg-slate-100"
         role="region" aria-label="Peta lokasi {{ $car->location }}"
         data-location="{{ $car->location }}"
         data-search-suffix="{{ config('maps.search_suffix') }}"
         data-fallback-lat="{{ config('maps.fallback.lat') }}"
         data-fallback-lng="{{ config('maps.fallback.lng') }}"
         data-zoom="{{ config('maps.zoom') }}"></div>

    <p id="car-map-status" class="mt-2 text-xs text-slate-500">Menentukan titik lokasi…</p>
    <noscript>
        <p class="mt-2 text-xs text-slate-500">Peta memerlukan JavaScript. Lokasi unit: {{ $car->location }}.</p>
    </noscript>
</section>

@pushOnce('scripts')
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
            integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
        (() => {
            const element = document.getElementById('car-map');

            if (!element || typeof L === 'undefined') {
                return;
            }

            const fallback = [
                Number(element.dataset.fallbackLat),
                Number(element.dataset.fallbackLng),
            ];
            const zoom = Number(element.dataset.zoom);
            const locationName = element.dataset.location || '';
            const status = document.getElementById('car-map-status');

            const map = L.map(element).setView(fallback, zoom);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                    + ' · geocoding <a href="https://nominatim.openstreetmap.org/">Nominatim</a>',
            }).addTo(map);

            const popup = document.createElement('p');
            popup.textContent = locationName;

            const marker = L.marker(fallback).addTo(map).bindPopup(popup);

            const place = (coordinates, accurate) => {
                map.setView(coordinates, accurate ? 14 : zoom);
                marker.setLatLng(coordinates);
                status.textContent = accurate
                    ? 'Titik perkiraan kecamatan. Konfirmasi alamat pasti ke penjual.'
                    : 'Lokasi belum berhasil ditentukan; peta menampilkan pusat Medan.';
            };

            const query = locationName + (element.dataset.searchSuffix || '');

            fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q=' + encodeURIComponent(query))
                .then((response) => {
                    if (!response.ok) {
                        throw new Error('Geocoding tidak tersedia');
                    }

                    return response.json();
                })
                .then((results) => {
                    const hit = Array.isArray(results) ? results[0] : null;
                    const latitude = Number(hit?.lat);
                    const longitude = Number(hit?.lon);

                    if (hit && Number.isFinite(latitude) && Number.isFinite(longitude)) {
                        place([latitude, longitude], true);
                    } else {
                        place(fallback, false);
                    }
                })
                .catch(() => place(fallback, false));
        })();
    </script>
@endpushOnce
