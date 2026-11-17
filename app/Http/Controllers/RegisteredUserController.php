<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Services\Semantic\SiteGraphBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(RegisterRequest $request, SiteGraphBuilder $site): RedirectResponse
    {
        $request->createUser();

        $site->syncSellers();

        return redirect()->route('iklan.create')
            ->with('status', 'Akun Anda siap. Lengkapi dulu profil toko, lalu pasang iklan pertama.');
    }
}
