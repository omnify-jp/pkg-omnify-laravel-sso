<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class StandalonePasswordResetController extends Controller
{
    public function create(): Response
    {
        $pagePath = config('omnify-auth.standalone.pages.forgot_password', 'auth/forgot-password');

        return Inertia::render($pagePath, [
            'status' => session('status'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($request->only('email'));

        if ($status === Password::ResetLinkSent) {
            return back()->with('status', __($status));
        }

        return back()->withErrors(['email' => __($status)]);
    }
}
