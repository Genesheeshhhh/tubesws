<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\CarImage;
use App\Services\Catalog\ListingPhotoStore;
use App\Services\Semantic\CarGraphBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ListingPhotoController extends Controller
{
    public function store(Request $request, Car $car, ListingPhotoStore $photos, CarGraphBuilder $graph): RedirectResponse
    {
        $this->authorize('update', $car);

        $validated = $request->validate([
            'photos' => ['required', 'array', 'max:8'],
            'photos.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ], [
            'photos.required' => 'Pilih minimal satu foto untuk diunggah.',
            'photos.*.image' => 'Setiap berkas harus berupa gambar.',
            'photos.*.max' => 'Ukuran maksimal tiap foto adalah 4 MB.',
        ]);

        $stored = $photos->store($car, $validated['photos'] ?? []);

        $graph->publish($car->refresh()->load(['images', 'seller']));

        return back()->with('status', count($stored).' foto berhasil diunggah.');
    }

    public function destroy(Car $car, CarImage $photo, ListingPhotoStore $photos, CarGraphBuilder $graph): RedirectResponse
    {
        $this->authorize('update', $car);

        abort_unless($photo->car_id === $car->id, 404);

        $photos->delete($photo);
        $photos->ensureArtwork($car);

        $graph->publish($car->refresh()->load(['images', 'seller']));

        return back()->with('status', 'Foto dihapus dari iklan.');
    }
}
