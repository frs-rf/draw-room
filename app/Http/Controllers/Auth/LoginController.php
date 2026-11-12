<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Login khusus panitia. Tidak ada registrasi publik: akun dibuat lewat
 * `php artisan panitia:create`. Semua user di tabel `users` = panitia.
 */
class LoginController extends Controller
{
    public function show(): View|RedirectResponse
    {
        return Auth::check() ? redirect()->route('panitia.console') : view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt(['username' => strtolower($credentials['username']), 'password' => $credentials['password']], $request->boolean('remember'))) {
            return back()
                ->withErrors(['username' => 'Username atau password salah.'])
                ->onlyInput('username');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('panitia.console'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('draw.viewer');
    }
}
