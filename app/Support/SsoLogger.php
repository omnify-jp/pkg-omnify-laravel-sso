<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Support;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

/**
 * SSO Client Logger
 *
 * Provides a dedicated logging channel for SSO-related events.
 * All logs are prefixed with [SSO] for easy filtering.
 */
class SsoLogger
{
    private LoggerInterface $logger;

    private bool $enabled;

    public function __construct()
    {
        $channel = config('omnify-auth.logging.channel', 'sso');
        $this->enabled = config('omnify-auth.logging.enabled', true);

        // Use dedicated channel if configured, otherwise use default
        if (config("logging.channels.{$channel}")) {
            $this->logger = Log::channel($channel);
        } else {
            $this->logger = Log::channel(config('logging.default'));
        }
    }

    /**
     * Log SSO authentication attempt.
     */
    public function authAttempt(string $email, bool $success, ?string $reason = null): void
    {
        if (! $this->enabled) {
            return;
        }

        $context = [
            'email' => $this->maskEmail($email),
            'success' => $success,
            'ip' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ];

        if ($reason) {
            $context['reason'] = $reason;
        }

        if ($success) {
            $this->info('Authentication successful', $context);
        } else {
            $this->warning('Authentication failed', $context);
        }
    }

    /**
     * Log SSO code exchange.
     */
    public function slugExchange(bool $success, ?string $error = null): void
    {
        $this->codeExchange($success, $error);
    }

    /** @alias slugExchange */
    public function codeExchange(bool $success, ?string $error = null): void
    {
        if (! $this->enabled) {
            return;
        }

        $context = [
            'success' => $success,
            'ip' => request()->ip(),
        ];

        if ($error) {
            $context['error'] = $error;
        }

        if ($success) {
            $this->debug('Code exchange successful', $context);
        } else {
            $this->warning('Code exchange failed', $context);
        }
    }

    /**
     * Log JWT verification.
     */
    public function jwtVerification(bool $success, ?string $reason = null): void
    {
        if (! $this->enabled) {
            return;
        }

        $context = [
            'success' => $success,
            'ip' => request()->ip(),
        ];

        if ($reason) {
            $context['reason'] = $reason;
        }

        if ($success) {
            $this->debug('JWT verification successful', $context);
        } else {
            $this->warning('JWT verification failed', $context);
        }
    }

    /**
     * Log token refresh.
     */
    public function tokenRefresh(int|string $userId, bool $success, ?string $error = null): void
    {
        if (! $this->enabled) {
            return;
        }

        $context = [
            'user_id' => $userId,
            'success' => $success,
        ];

        if ($error) {
            $context['error'] = $error;
        }

        if ($success) {
            $this->debug('Token refresh successful', $context);
        } else {
            $this->warning('Token refresh failed', $context);
        }
    }

    /**
     * Log logout event.
     */
    public function logout(int|string $userId, bool $global = false): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->info('User logged out', [
            'user_id' => $userId,
            'global' => $global,
            'ip' => request()->ip(),
        ]);
    }

    /**
     * Log security event (e.g., blocked redirect, invalid token).
     */
    public function securityEvent(string $event, array $context = []): void
    {
        if (! $this->enabled) {
            return;
        }

        $context['ip'] = request()->ip();
        $context['user_agent'] = request()->userAgent();

        $this->warning("Security event: {$event}", $context);
    }

    /**
     * Log Console API error.
     */
    public function apiError(string $endpoint, int $statusCode, ?string $error = null): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->error('Console API error', [
            'endpoint' => $endpoint,
            'status_code' => $statusCode,
            'error' => $error,
        ]);
    }

    /**
     * Log JWKS fetch.
     */
    public function jwksFetch(bool $success, bool $fromCache = false): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->debug('JWKS fetch', [
            'success' => $success,
            'from_cache' => $fromCache,
        ]);
    }

    /**
     * Log permission sync.
     */
    public function permissionSync(int $created, int $updated, int $deleted): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->info('Permissions synced', [
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deleted,
        ]);
    }

    // ==========================================================================
    // Base logging methods
    // ==========================================================================

    public function debug(string $message, array $context = []): void
    {
        $this->log('debug', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->log('info', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->log('warning', $message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->log('error', $message, $context);
    }

    private function log(string $level, string $message, array $context = []): void
    {
        if (! $this->enabled) {
            return;
        }

        $this->logger->{$level}("[SSO] {$message}", $context);
    }

    /**
     * Mask email for privacy in logs.
     */
    private function maskEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) !== 2) {
            return '***@***';
        }

        $local = $parts[0];
        $domain = $parts[1];

        // Show first 2 chars of local part
        $maskedLocal = strlen($local) > 2
            ? substr($local, 0, 2).'***'
            : '***';

        return "{$maskedLocal}@{$domain}";
    }
}
