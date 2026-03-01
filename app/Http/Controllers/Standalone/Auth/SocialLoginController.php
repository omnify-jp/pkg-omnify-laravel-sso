<?php

declare(strict_types=1);

namespace Omnify\Core\Http\Controllers\Standalone\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Omnify\Core\Models\SocialAccount;
use Omnify\Core\Models\User;

class SocialLoginController extends Controller
{
    /**
     * Redirect to the OAuth provider.
     */
    public function redirect(string $provider): RedirectResponse|\Symfony\Component\HttpFoundation\Response
    {
        $this->validateProvider($provider);

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Handle the callback from the OAuth provider.
     */
    public function callback(string $provider): RedirectResponse
    {
        $this->validateProvider($provider);

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect()->route('login')
                ->with('error', __('Social login failed. Please try again.'));
        }

        $user = DB::transaction(function () use ($provider, $socialUser) {
            // 1. Check if social account already linked
            $socialAccount = SocialAccount::where('provider', $provider)
                ->where('provider_id', $socialUser->getId())
                ->first();

            if ($socialAccount) {
                $this->updateSocialTokens($socialAccount, $socialUser);

                return $socialAccount->user;
            }

            // 2. Find existing user by email
            $userModel = config('omnify-auth.user_model', User::class);
            $user = $userModel::where('email', $socialUser->getEmail())->first();

            // 3. Create new user if not found
            if (! $user) {
                $user = $userModel::create([
                    'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
                    'email' => $socialUser->getEmail(),
                    'password' => bcrypt(Str::random(32)),
                    'is_default_password' => true,
                    'email_verified_at' => now(),
                    'avatar_url' => $socialUser->getAvatar(),
                ]);
            }

            // 4. Link social account to user
            $this->createSocialAccount($user, $provider, $socialUser);

            return $user;
        });

        Auth::login($user, remember: true);

        $redirect = config('omnify-auth.standalone.redirect_after_login', 'dashboard');

        return redirect()->intended($redirect);
    }

    /**
     * Validate that the provider is configured and enabled.
     */
    protected function validateProvider(string $provider): void
    {
        $providers = config('omnify-auth.socialite.providers', []);

        if (! array_key_exists($provider, $providers)) {
            abort(404, "Social login provider [{$provider}] is not configured.");
        }
    }

    /**
     * Create a social account record for the user.
     */
    protected function createSocialAccount(User $user, string $provider, $socialUser): SocialAccount
    {
        return SocialAccount::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_id' => $socialUser->getId(),
            'provider_email' => $socialUser->getEmail(),
            'provider_avatar' => $socialUser->getAvatar(),
            'access_token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken ?? null,
            'token_expires_at' => $socialUser->expiresIn
                ? now()->addSeconds($socialUser->expiresIn)
                : null,
        ]);
    }

    /**
     * Update tokens on an existing social account.
     */
    protected function updateSocialTokens(SocialAccount $socialAccount, $socialUser): void
    {
        $socialAccount->update([
            'provider_email' => $socialUser->getEmail(),
            'provider_avatar' => $socialUser->getAvatar(),
            'access_token' => $socialUser->token,
            'refresh_token' => $socialUser->refreshToken ?? $socialAccount->refresh_token,
            'token_expires_at' => $socialUser->expiresIn
                ? now()->addSeconds($socialUser->expiresIn)
                : null,
        ]);
    }
}
