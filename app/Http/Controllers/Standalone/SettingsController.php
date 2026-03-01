<?php

declare(strict_types=1);

namespace Omnify\Core\Http\Controllers\Standalone;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    public function index(Request $request): Response
    {
        $extraSections = config('omnify-auth.settings.extra_sections', []);

        return Inertia::render('settings/index', [
            'extraSections' => $extraSections,
        ]);
    }

    public function account(Request $request): Response
    {
        return Inertia::render('settings/account');
    }

    public function security(Request $request): Response
    {
        $user = $request->user();
        $twoFactorEnabled = $user->two_factor_confirmed_at !== null && $user->google2fa_secret !== null;

        return Inertia::render('settings/security', [
            'twoFactorEnabled' => $twoFactorEnabled,
            'recoveryCodes' => session('recoveryCodes'),
        ]);
    }
}
