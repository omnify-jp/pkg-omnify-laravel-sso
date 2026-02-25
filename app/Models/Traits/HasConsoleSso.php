<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Models\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Crypt;

/**
 * Trait to be added to User model for SSO functionality.
 *
 * Required columns in users table:
 * - console_user_id (bigint, unique)
 * - console_access_token (text, nullable)
 * - console_refresh_token (text, nullable)
 * - console_token_expires_at (timestamp, nullable)
 */
trait HasConsoleSso
{
    /**
     * Initialize the trait.
     */
    public function initializeHasConsoleSso(): void
    {
        // Add to hidden attributes
        $this->hidden = array_merge($this->hidden, [
            'console_access_token',
            'console_refresh_token',
        ]);
    }

    /**
     * Scope to find user by Console user ID.
     */
    public function scopeByConsoleUserId(Builder $query, int $consoleUserId): Builder
    {
        return $query->where('console_user_id', $consoleUserId);
    }

    /**
     * Get decrypted Console access token.
     */
    public function getConsoleAccessToken(): ?string
    {
        return $this->decryptToken($this->console_access_token);
    }

    /**
     * Get decrypted Console refresh token.
     */
    public function getConsoleRefreshToken(): ?string
    {
        return $this->decryptToken($this->console_refresh_token);
    }

    /**
     * Set Console tokens (encrypted).
     */
    public function setConsoleTokens(?string $accessToken, ?string $refreshToken, ?\DateTimeInterface $expiresAt): void
    {
        $this->console_access_token = $accessToken ? Crypt::encryptString($accessToken) : null;
        $this->console_refresh_token = $refreshToken ? Crypt::encryptString($refreshToken) : null;
        $this->console_token_expires_at = $expiresAt;
    }

    /**
     * Check if Console tokens are valid.
     */
    public function hasValidConsoleTokens(): bool
    {
        if (empty($this->console_access_token) || empty($this->console_refresh_token)) {
            return false;
        }

        if (empty($this->console_token_expires_at)) {
            return false;
        }

        return $this->console_token_expires_at > now();
    }

    /**
     * Decrypt an encrypted token value, returning null on failure.
     */
    private function decryptToken(?string $encrypted): ?string
    {
        if (empty($encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($encrypted);
        } catch (\Throwable) {
            return null;
        }
    }
}
