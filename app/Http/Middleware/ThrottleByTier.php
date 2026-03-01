<?php

declare(strict_types=1);

namespace Omnify\Core\Http\Middleware;

use Closure;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Omnify\Core\Models\Organization;
use Symfony\Component\HttpFoundation\Response;

class ThrottleByTier
{
    public function __construct(
        protected RateLimiter $limiter
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $tier = 'default'): Response
    {
        $user = $request->user();
        $ip = $request->ip();

        $isWhitelisted = $this->isIpWhitelisted($user, $ip);
        $limits = $this->getLimits($user, $isWhitelisted, $tier);

        foreach ($limits as $key => $limit) {
            if ($this->tooManyAttempts($key, $limit['max'])) {
                return $this->buildTooManyAttemptsResponse($key, $limit['max']);
            }

            $this->hit($key, $limit['decay']);
        }

        $response = $next($request);

        if (! empty($limits)) {
            $firstKey = array_key_first($limits);
            $response->headers->add([
                'X-RateLimit-Limit' => $limits[$firstKey]['max'],
                'X-RateLimit-Remaining' => max(0, $limits[$firstKey]['max'] - $this->attempts($firstKey)),
            ]);
        }

        return $response;
    }

    /**
     * Get rate limits based on authentication state and whitelist status.
     *
     * @return array<string, array{max: int, decay: int}>
     */
    protected function getLimits($user, bool $isWhitelisted, string $tier): array
    {
        $config = config('security.rate_limits');
        $ip = request()->ip();
        $limits = [];

        // SSO-specific tiers
        if (str_starts_with($tier, 'sso:')) {
            $ssoEndpoint = str_replace('sso:', '', $tier);

            return $this->getSsoLimits($user, $isWhitelisted, $ssoEndpoint, $ip);
        }

        // Service-to-service (API key auth)
        if ($tier === 'service') {
            $serviceKey = request()->header('X-Service-Key');
            if ($serviceKey) {
                $limits["service:{$serviceKey}"] = [
                    'max' => $config['service']['per_key']['max_attempts'],
                    'decay' => $config['service']['per_key']['decay_minutes'] * 60,
                ];

                return $limits;
            }
        }

        // Whitelisted IP (corporate network)
        if ($isWhitelisted && $user) {
            $limits["user:{$user->id}"] = [
                'max' => $config['whitelisted']['per_user']['max_attempts'],
                'decay' => $config['whitelisted']['per_user']['decay_minutes'] * 60,
            ];

            $orgSlug = request()->header('X-Org-Id');
            if ($orgSlug) {
                $limits["org:{$orgSlug}"] = [
                    'max' => $config['whitelisted']['per_org']['max_attempts'],
                    'decay' => $config['whitelisted']['per_org']['decay_minutes'] * 60,
                ];
            }

            return $limits;
        }

        // Authenticated user (non-whitelisted)
        if ($user) {
            $limits["ip:{$ip}"] = [
                'max' => $config['authenticated']['per_ip']['max_attempts'],
                'decay' => $config['authenticated']['per_ip']['decay_minutes'] * 60,
            ];

            $limits["user:{$user->id}"] = [
                'max' => $config['authenticated']['per_user']['max_attempts'],
                'decay' => $config['authenticated']['per_user']['decay_minutes'] * 60,
            ];

            $orgSlug = request()->header('X-Org-Id');
            if ($orgSlug) {
                $limits["org:{$orgSlug}"] = [
                    'max' => $config['authenticated']['per_org']['max_attempts'],
                    'decay' => $config['authenticated']['per_org']['decay_minutes'] * 60,
                ];
            }

            return $limits;
        }

        // Anonymous (unauthenticated)
        $limits["ip:{$ip}"] = [
            'max' => $config['anonymous']['per_ip']['max_attempts'],
            'decay' => $config['anonymous']['per_ip']['decay_minutes'] * 60,
        ];

        return $limits;
    }

    /**
     * Get SSO-specific rate limits.
     *
     * @return array<string, array{max: int, decay: int}>
     */
    protected function getSsoLimits($user, bool $isWhitelisted, string $endpoint, string $ip): array
    {
        $config = config('security.rate_limits.sso');
        $limits = [];

        switch ($endpoint) {
            case 'authorize':
                if ($user) {
                    $limits["sso:authorize:user:{$user->id}"] = [
                        'max' => $config['authorize']['authenticated'],
                        'decay' => 60,
                    ];
                } else {
                    $limits["sso:authorize:ip:{$ip}"] = [
                        'max' => $config['authorize']['anonymous'],
                        'decay' => 60,
                    ];
                }
                break;

            case 'token':
                if (! $isWhitelisted) {
                    $limits["sso:token:ip:{$ip}"] = [
                        'max' => $config['token']['per_ip'],
                        'decay' => 60,
                    ];
                }
                break;

            case 'refresh':
                $refreshToken = request()->input('refresh_token');
                if ($refreshToken) {
                    $tokenKey = substr(hash('sha256', $refreshToken), 0, 16);
                    $limits["sso:refresh:token:{$tokenKey}"] = [
                        'max' => $config['refresh']['per_token'],
                        'decay' => 60,
                    ];
                }
                break;
        }

        return $limits;
    }

    /**
     * Check if IP is whitelisted for any of user's organizations.
     */
    protected function isIpWhitelisted($user, string $ip): bool
    {
        if (! $user) {
            return false;
        }

        if (! method_exists($user, 'organizations')) {
            return false;
        }

        return $user->organizations()
            ->whereNotNull('allowed_ips')
            ->get()
            ->contains(function (Organization $org) use ($ip) {
                return $this->matchesIpWhitelist($ip, $org->allowed_ips ?? []);
            });
    }

    /**
     * Check if IP matches any pattern in the whitelist.
     */
    protected function matchesIpWhitelist(string $ip, array $whitelist): bool
    {
        foreach ($whitelist as $pattern) {
            if ($ip === $pattern) {
                return true;
            }

            if (str_contains($pattern, '/') && $this->ipInCidr($ip, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if IP is within CIDR range.
     */
    protected function ipInCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr, 2);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $ipLong = ip2long($ip);
            $subnetLong = ip2long($subnet);
            $maskLong = -1 << (32 - (int) $mask);

            return ($ipLong & $maskLong) === ($subnetLong & $maskLong);
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $ipBin = inet_pton($ip);
            $subnetBin = inet_pton($subnet);
            $maskBits = (int) $mask;

            for ($i = 0; $i < $maskBits / 8; $i++) {
                if ($ipBin[$i] !== $subnetBin[$i]) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    protected function tooManyAttempts(string $key, int $maxAttempts): bool
    {
        return $this->limiter->tooManyAttempts($key, $maxAttempts);
    }

    protected function hit(string $key, int $decaySeconds): void
    {
        $this->limiter->hit($key, $decaySeconds);
    }

    protected function attempts(string $key): int
    {
        return $this->limiter->attempts($key);
    }

    protected function buildTooManyAttemptsResponse(string $key, int $maxAttempts): Response
    {
        $retryAfter = $this->limiter->availableIn($key);

        return response()->json([
            'error' => [
                'code' => 'rate_limit_exceeded',
                'message' => 'Too many requests',
                'details' => [
                    'retry_after' => $retryAfter,
                    'limit' => $maxAttempts,
                ],
            ],
        ], 429)->withHeaders([
            'Retry-After' => $retryAfter,
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => 0,
        ]);
    }
}
