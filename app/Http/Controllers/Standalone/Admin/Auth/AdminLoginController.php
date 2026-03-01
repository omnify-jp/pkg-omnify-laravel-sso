<?php

declare(strict_types=1);

namespace Omnify\Core\Http\Controllers\Standalone\Admin\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AdminLoginController extends Controller
{
    public function showLogin(): Response
    {
        $pagePath = config('omnify-auth.standalone.pages.admin_login', 'admin/auth/login');

        return Inertia::render($pagePath);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::guard('admin')->attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => __('auth.failed'),
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        $adminPrefix = config('omnify-auth.routes.standalone_admin_prefix', 'admin');

        return redirect()->intended('/'.$adminPrefix);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('admin')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $redirectRoute = config('omnify-auth.standalone.admin_redirect_after_logout', 'admin.login');

        return redirect()->route($redirectRoute);
    }
}
