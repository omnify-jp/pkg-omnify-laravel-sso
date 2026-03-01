<?php

declare(strict_types=1);

namespace Omnify\Core\Models\Traits;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Omnify\Core\Models\SocialAccount;

trait HasSocialAccounts
{
    /**
     * @return HasMany<SocialAccount, $this>
     */
    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    /**
     * Get the social account for a specific provider.
     */
    public function socialAccount(string $provider): ?SocialAccount
    {
        return $this->socialAccounts()->where('provider', $provider)->first();
    }

    /**
     * Check if the user has a linked social account for a provider.
     */
    public function hasSocialAccount(string $provider): bool
    {
        return $this->socialAccounts()->where('provider', $provider)->exists();
    }
}
