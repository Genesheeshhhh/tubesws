<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Services\Semantic\SiteGraphBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request, SiteGraphBuilder $site): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $site->syncSellers();

        return redirect()->intended(route('iklan.saya'))
            ->with('status', 'Selamat datang kembali, '.$request->user()->name.'.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('beranda')->with('status', 'Anda sudah keluar.');
    }
}
