<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Log;
use Omnify\Core\Support\SsoLogger;

beforeEach(function () {
    config(['omnify-auth.logging.enabled' => true]);
    config(['omnify-auth.logging.channel' => 'sso']);
});

describe('SsoLogger', function () {
    test('logs authentication attempt success', function () {
        Log::shouldReceive('channel')
            ->andReturnSelf();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, '[SSO] Authentication successful')
                    && isset($context['email'])
                    && $context['success'] === true;
            });

        $logger = new SsoLogger;
        $logger->authAttempt('user@example.com', true);
    });

    test('logs authentication attempt failure', function () {
        Log::shouldReceive('channel')
            ->andReturnSelf();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, '[SSO] Authentication failed')
                    && $context['success'] === false
                    && $context['reason'] === 'Invalid credentials';
            });

        $logger = new SsoLogger;
        $logger->authAttempt('user@example.com', false, 'Invalid credentials');
    });

    test('masks email in logs for privacy', function () {
        Log::shouldReceive('channel')
            ->andReturnSelf();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                // Email should be masked: te***@example.com
                return str_contains($context['email'], '***')
                    && str_contains($context['email'], '@example.com')
                    && ! str_contains($context['email'], 'test@');
            });

        $logger = new SsoLogger;
        $logger->authAttempt('test@example.com', true);
    });

    test('logs code exchange', function () {
        Log::shouldReceive('channel')
            ->andReturnSelf();
        Log::shouldReceive('debug')
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, '[SSO] Code exchange successful');
            });

        $logger = new SsoLogger;
        $logger->slugExchange(true);
    });

    test('logs code exchange failure', function () {
        Log::shouldReceive('channel')
            ->andReturnSelf();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, '[SSO] Code exchange failed')
                    && $context['error'] === 'Expired code';
            });

        $logger = new SsoLogger;
        $logger->slugExchange(false, 'Expired code');
    });

    test('logs JWT verification', function () {
        Log::shouldReceive('channel')
            ->andReturnSelf();
        Log::shouldReceive('debug')
            ->once()
            ->withArgs(function ($message) {
                return str_contains($message, '[SSO] JWT verification successful');
            });

        $logger = new SsoLogger;
        $logger->jwtVerification(true);
    });

    test('logs security events', function () {
        Log::shouldReceive('channel')
            ->andReturnSelf();
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, '[SSO] Security event: blocked_redirect')
                    && isset($context['ip']);
            });

        $logger = new SsoLogger;
        $logger->securityEvent('blocked_redirect', ['url' => 'https://evil.com']);
    });

    test('logs logout', function () {
        Log::shouldReceive('channel')
            ->andReturnSelf();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, '[SSO] User logged out')
                    && $context['user_id'] === 123;
            });

        $logger = new SsoLogger;
        $logger->logout(123);
    });

    test('logs API errors', function () {
        Log::shouldReceive('channel')
            ->andReturnSelf();
        Log::shouldReceive('error')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, '[SSO] Console API error')
                    && $context['endpoint'] === '/api/token'
                    && $context['status_code'] === 500;
            });

        $logger = new SsoLogger;
        $logger->apiError('/api/token', 500, 'Internal server error');
    });

    test('respects logging disabled config', function () {
        config(['omnify-auth.logging.enabled' => false]);

        Log::shouldReceive('channel')
            ->andReturnSelf();
        Log::shouldReceive('info')
            ->never();

        $logger = new SsoLogger;
        $logger->authAttempt('user@example.com', true);
    });

    test('logs permission sync', function () {
        Log::shouldReceive('channel')
            ->andReturnSelf();
        Log::shouldReceive('info')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, '[SSO] Permissions synced')
                    && $context['created'] === 5
                    && $context['updated'] === 3
                    && $context['deleted'] === 1;
            });

        $logger = new SsoLogger;
        $logger->permissionSync(5, 3, 1);
    });

    test('logs token refresh', function () {
        Log::shouldReceive('channel')
            ->andReturnSelf();
        Log::shouldReceive('debug')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, '[SSO] Token refresh successful')
                    && $context['user_id'] === 123;
            });

        $logger = new SsoLogger;
        $logger->tokenRefresh(123, true);
    });
});

describe('sso_log helper', function () {
    test('returns SsoLogger instance', function () {
        expect(sso_log())->toBeInstanceOf(SsoLogger::class);
    });

    test('returns same instance (singleton)', function () {
        $logger1 = sso_log();
        $logger2 = sso_log();

        expect($logger1)->toBe($logger2);
    });
});
