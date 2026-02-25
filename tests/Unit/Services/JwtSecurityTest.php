<?php

declare(strict_types=1);

use Omnify\SsoClient\Services\JwksService;
use Omnify\SsoClient\Services\JwtVerifier;

// =============================================================================
// JWT Security Tests
// =============================================================================

describe('JWT Security', function () {
    test('rejects token without signature', function () {
        $jwksService = \Mockery::mock(JwksService::class);
        $verifier = new JwtVerifier($jwksService);

        // Token without signature (only header.payload)
        $tokenWithoutSig = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.
            base64_encode(json_encode(['sub' => 123]));

        $result = $verifier->verify($tokenWithoutSig);

        expect($result)->toBeNull();
    });

    test('rejects malformed token', function () {
        $jwksService = \Mockery::mock(JwksService::class);
        $verifier = new JwtVerifier($jwksService);

        $result = $verifier->verify('not-a-jwt');

        expect($result)->toBeNull();
    });

    test('rejects token with none algorithm', function () {
        $jwksService = \Mockery::mock(JwksService::class);
        $verifier = new JwtVerifier($jwksService);

        // Token with "none" algorithm (security vulnerability if accepted)
        $header = base64_encode(json_encode(['alg' => 'none', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode(['sub' => 123, 'email' => 'hacker@evil.com']));
        $tokenWithNone = $header.'.'.$payload.'.';

        $result = $verifier->verify($tokenWithNone);

        expect($result)->toBeNull();
    });

    test('rejects token with HS256 when RS256 expected', function () {
        $jwksService = \Mockery::mock(JwksService::class);
        $verifier = new JwtVerifier($jwksService);

        // Token with HS256 (symmetric) instead of RS256 (asymmetric)
        $header = base64_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $payload = base64_encode(json_encode(['sub' => 123]));
        $fakeSignature = base64_encode('fake-signature');
        $tokenWithHS256 = $header.'.'.$payload.'.'.$fakeSignature;

        $result = $verifier->verify($tokenWithHS256);

        expect($result)->toBeNull();
    });

    test('rejects empty token', function () {
        $jwksService = \Mockery::mock(JwksService::class);
        $verifier = new JwtVerifier($jwksService);

        expect($verifier->verify(''))->toBeNull();
        expect($verifier->verify('   '))->toBeNull();
    });

    test('rejects token with invalid JSON in payload', function () {
        $jwksService = \Mockery::mock(JwksService::class);
        $verifier = new JwtVerifier($jwksService);

        $header = base64_encode(json_encode(['alg' => 'RS256']));
        $invalidPayload = base64_encode('not-json');
        $signature = base64_encode('signature');
        $token = $header.'.'.$invalidPayload.'.'.$signature;

        $result = $verifier->verify($token);

        expect($result)->toBeNull();
    });
});

// =============================================================================
// JWKS Security Tests
// =============================================================================

describe('JWKS Security', function () {
    test('handles missing keys gracefully', function () {
        $jwksService = \Mockery::mock(JwksService::class);
        $jwksService->shouldReceive('getKeys')->andReturn([]);

        $verifier = new JwtVerifier($jwksService);

        // Create a valid-looking token
        $header = base64_encode(json_encode(['alg' => 'RS256', 'kid' => 'key-1']));
        $payload = base64_encode(json_encode(['sub' => 123]));
        $signature = base64_encode('signature');
        $token = $header.'.'.$payload.'.'.$signature;

        $result = $verifier->verify($token);

        expect($result)->toBeNull();
    });

    test('handles JWKS fetch failure gracefully', function () {
        $jwksService = \Mockery::mock(JwksService::class);
        $jwksService->shouldReceive('getKeys')
            ->andThrow(new \Exception('Network error'));

        $verifier = new JwtVerifier($jwksService);

        $header = base64_encode(json_encode(['alg' => 'RS256']));
        $payload = base64_encode(json_encode(['sub' => 123]));
        $signature = base64_encode('signature');
        $token = $header.'.'.$payload.'.'.$signature;

        // Should handle exception gracefully
        $result = $verifier->verify($token);

        expect($result)->toBeNull();
    });
});

// =============================================================================
// Token Manipulation Tests
// =============================================================================

describe('Token Manipulation Prevention', function () {
    test('rejects token with modified payload', function () {
        $jwksService = \Mockery::mock(JwksService::class);
        $verifier = new JwtVerifier($jwksService);

        // Even if header/signature look valid, modified payload should fail
        $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
        $modifiedPayload = base64_encode(json_encode([
            'sub' => 999, // Changed user ID
            'email' => 'admin@victim.com',
            'exp' => time() + 3600,
        ]));
        $originalSignature = 'original-signature-for-different-payload';
        $token = $header.'.'.$modifiedPayload.'.'.base64_encode($originalSignature);

        $result = $verifier->verify($token);

        expect($result)->toBeNull();
    });

    test('rejects token with swapped signature from another token', function () {
        $jwksService = \Mockery::mock(JwksService::class);
        $verifier = new JwtVerifier($jwksService);

        // Token 1's signature attached to Token 2's header+payload
        $header = base64_encode(json_encode(['alg' => 'RS256']));
        $payload = base64_encode(json_encode(['sub' => 123, 'email' => 'user@example.com']));
        $stolenSignature = base64_encode('signature-from-another-valid-token');
        $token = $header.'.'.$payload.'.'.$stolenSignature;

        $result = $verifier->verify($token);

        expect($result)->toBeNull();
    });
});

// =============================================================================
// Claims Validation Tests
// =============================================================================

describe('Claims Validation', function () {
    test('token must have required claims', function () {
        // This tests that the verifier checks for required claims
        // like sub, email, etc.
        $jwksService = \Mockery::mock(JwksService::class);
        $verifier = new JwtVerifier($jwksService);

        // Token missing required claims
        $header = base64_encode(json_encode(['alg' => 'RS256']));
        $payload = base64_encode(json_encode([])); // Empty claims
        $signature = base64_encode('signature');
        $token = $header.'.'.$payload.'.'.$signature;

        $result = $verifier->verify($token);

        expect($result)->toBeNull();
    });
});
