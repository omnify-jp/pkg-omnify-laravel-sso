<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class ConsoleTokenService
{
    private const REFRESH_THRESHOLD_MINUTES = 5;

    public function __construct(
        private readonly ConsoleApiService $consoleApi
    ) {}

    /**
     * Get access token for user, refreshing if needed.
     */
    public function getAccessToken(Model $user): ?string
    {
        $this->refreshIfNeeded($user);

        return $user->getConsoleAccessToken();
    }

    /**
     * Refresh tokens if they are about to expire.
     */
    public function refreshIfNeeded(Model $user): bool
    {
        $expiresAt = $user->console_token_expires_at;

        if (! $expiresAt) {
            return false;
        }

        $expiresAt = Carbon::parse($expiresAt);

        // Check if token expires within threshold
        if ($expiresAt->subMinutes(self::REFRESH_THRESHOLD_MINUTES)->isFuture()) {
            return false; // Token still valid
        }

        return $this->refresh($user);
    }

    /**
     * Force refresh tokens.
     */
    public function refresh(Model $user): bool
    {
        $refreshToken = $user->getConsoleRefreshToken();

        if (! $refreshToken) {
            return false;
        }

        $tokens = $this->consoleApi->refreshToken($refreshToken);

        if (! $tokens) {
            return false;
        }

        $user->setConsoleTokens(
            $tokens['access_token'],
            $tokens['refresh_token'],
            Carbon::now()->addSeconds($tokens['expires_in'])
        );

        $user->save();

        return true;
    }

    /**
     * Revoke tokens on logout.
     */
    public function revokeTokens(Model $user): bool
    {
        $refreshToken = $user->getConsoleRefreshToken();

        if ($refreshToken) {
            $this->consoleApi->revokeToken($refreshToken);
        }

        // Clear tokens from user
        $user->setConsoleTokens(null, null, null);
        $user->save();

        return true;
    }

    /**
     * Store new tokens for user.
     *
     * @param  array{access_token: string, refresh_token: string, expires_in: int}  $tokens
     */
    public function storeTokens(Model $user, array $tokens): void
    {
        $user->setConsoleTokens(
            $tokens['access_token'],
            $tokens['refresh_token'],
            Carbon::now()->addSeconds($tokens['expires_in'])
        );

        $user->save();
    }
}
