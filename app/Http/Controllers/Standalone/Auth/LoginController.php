<?php

declare(strict_types=1);

namespace Omnify\Core\Http\Controllers\Standalone\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function showLogin(): Response
    {
        $pagePath = config('omnify-auth.standalone.pages.login', 'auth/login');

        $socialiteProviders = [];
        if (config('omnify-auth.socialite.enabled', false)) {
            $socialiteProviders = array_keys(config('omnify-auth.socialite.providers', []));
        }

        return Inertia::render($pagePath, [
            'socialite_providers' => $socialiteProviders,
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => __('auth.failed'),
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        $redirectRoute = config('omnify-auth.standalone.redirect_after_login', 'dashboard');

        return redirect()->intended(route($redirectRoute));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $redirectRoute = config('omnify-auth.standalone.redirect_after_logout', 'login');

        return redirect()->route($redirectRoute);
    }
}
