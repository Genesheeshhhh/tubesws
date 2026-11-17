<?php

namespace App\Http\Controllers;

use App\Enums\CarStatus;
use App\Http\Requests\StoreInquiryRequest;
use App\Models\Car;
use App\Models\CarInquiry;
use Illuminate\Http\RedirectResponse;

class CarInquiryController extends Controller
{
    /**
     * The car is injected rather than pulled off the request so that route model
     * binding resolves the slug to a listing, and the request only has to worry
     * about validating the message itself.
     */
    public function store(StoreInquiryRequest $request, Car $car): RedirectResponse
    {
        if ($car->status === CarStatus::Sold) {
            return back()->with('error', 'Mobil ini sudah terjual, sehingga pesan belum dapat dikirim.');
        }

        // A seller messaging their own listing only fills their own inbox, so
        // the message is refused rather than quietly stored.
        if ($car->seller !== null && $request->user()?->is($car->seller)) {
            return back()->with('error', 'Ini iklan Anda sendiri, jadi pesan tidak perlu dikirim.');
        }

        CarInquiry::create($request->inquiryAttributes($car));

        return back()->with('status', 'Pesan Anda sudah terkirim ke penjual. Seller biasanya membalas dalam 1x24 jam.');
    }
}
