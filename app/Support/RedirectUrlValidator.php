<?php

declare(strict_types=1);

namespace Omnify\Core\Support;

/**
 * Validates redirect URLs to prevent Open Redirect vulnerabilities.
 *
 * This class ensures that redirect URLs only point to allowed domains,
 * preventing attackers from redirecting users to malicious sites.
 */
class RedirectUrlValidator
{
    /**
     * Allowed hosts for redirect URLs.
     *
     * @var array<string>
     */
    private array $allowedHosts = [];

    /**
     * Whether to allow relative URLs.
     */
    private bool $allowRelative = true;

    public function __construct()
    {
        // Load allowed hosts from config
        $this->allowedHosts = config('omnify-auth.security.allowed_redirect_hosts', []);

        // Always allow the app's own host
        $appUrl = config('app.url');
        if ($appUrl) {
            $parsed = parse_url($appUrl);
            if (isset($parsed['host'])) {
                $this->allowedHosts[] = $parsed['host'];
            }
        }

        // Allow frontend URL if configured
        $frontendUrl = config('app.frontend_url');
        if ($frontendUrl) {
            $parsed = parse_url($frontendUrl);
            if (isset($parsed['host'])) {
                $this->allowedHosts[] = $parsed['host'];
            }
        }

        // Remove duplicates
        $this->allowedHosts = array_unique(array_filter($this->allowedHosts));
    }

    /**
     * Validate a redirect URL.
     *
     * @param  string|null  $url  The URL to validate
     * @param  string  $default  Default URL if validation fails
     * @return string The validated URL or default
     */
    public function validate(?string $url, string $default = '/'): string
    {
        if (empty($url)) {
            return $default;
        }

        // Check URL length
        $maxLength = config('omnify-auth.security.max_redirect_url_length', 2048);
        if (strlen($url) > $maxLength) {
            return $default;
        }

        // Check for dangerous protocols
        if ($this->hasDangerousProtocol($url)) {
            return $default;
        }

        // Allow relative URLs (starting with /)
        if ($this->allowRelative && $this->isRelativeUrl($url)) {
            // Ensure it's truly relative (prevent //evil.com)
            if ($this->isSafeRelativeUrl($url)) {
                return $url;
            }

            return $default;
        }

        // Parse the URL
        $parsed = parse_url($url);

        // Must have a valid host
        if (! isset($parsed['host'])) {
            return $default;
        }

        // Check if host is allowed
        if (! $this->isHostAllowed($parsed['host'])) {
            return $default;
        }

        // Only allow http and https schemes
        if (isset($parsed['scheme']) && ! in_array(strtolower($parsed['scheme']), ['http', 'https'], true)) {
            return $default;
        }

        return $url;
    }

    /**
     * Check if a URL has a dangerous protocol.
     */
    private function hasDangerousProtocol(string $url): bool
    {
        $dangerousProtocols = [
            'javascript:',
            'vbscript:',
            'data:',
            'file:',
            'ftp:',
        ];

        $lowerUrl = strtolower(trim($url));

        foreach ($dangerousProtocols as $protocol) {
            if (str_starts_with($lowerUrl, $protocol)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if URL is relative (starts with /).
     */
    private function isRelativeUrl(string $url): bool
    {
        return str_starts_with($url, '/');
    }

    /**
     * Check if a relative URL is safe (not protocol-relative like //evil.com).
     */
    private function isSafeRelativeUrl(string $url): bool
    {
        // Protocol-relative URLs like //evil.com should be rejected
        if (str_starts_with($url, '//')) {
            return false;
        }

        // URLs with backslash can be dangerous (//\ or /\/)
        if (str_contains($url, '\\')) {
            return false;
        }

        // Reject URLs with encoded characters that could bypass checks
        // %2f = /, %5c = \, %00 = null
        $decoded = urldecode($url);
        if ($decoded !== $url && str_starts_with($decoded, '//')) {
            return false;
        }

        return true;
    }

    /**
     * Check if a host is in the allowed list.
     */
    private function isHostAllowed(string $host): bool
    {
        $host = strtolower($host);

        foreach ($this->allowedHosts as $allowedHost) {
            $allowedHost = strtolower($allowedHost);

            // Exact match
            if ($host === $allowedHost) {
                return true;
            }

            // Subdomain match (e.g., *.example.com)
            if (str_starts_with($allowedHost, '*.')) {
                $baseDomain = substr($allowedHost, 2);
                if ($host === $baseDomain || str_ends_with($host, '.'.$baseDomain)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Add an allowed host.
     */
    public function addAllowedHost(string $host): self
    {
        $this->allowedHosts[] = $host;
        $this->allowedHosts = array_unique($this->allowedHosts);

        return $this;
    }

    /**
     * Get allowed hosts.
     *
     * @return array<string>
     */
    public function getAllowedHosts(): array
    {
        return $this->allowedHosts;
    }

    /**
     * Set whether relative URLs are allowed.
     */
    public function setAllowRelative(bool $allow): self
    {
        $this->allowRelative = $allow;

        return $this;
    }
}
