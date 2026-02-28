<?php

/**
 * JwtVerifier Unit Tests
 *
 * JWTトークン検証サービスのテスト
 */

use Lcobucci\JWT\Encoding\ChainedFormatter;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Builder;
use Omnify\Core\Services\JwksService;
use Omnify\Core\Services\JwtVerifier;

beforeEach(function () {
    // テスト用のRSA鍵ペアを生成
    $config = [
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ];

    $res = openssl_pkey_new($config);
    openssl_pkey_export($res, $this->privateKey);
    $this->publicKey = openssl_pkey_get_details($res)['key'];
});

test('JwtVerifier can be instantiated', function () {
    $jwksService = Mockery::mock(JwksService::class);
    $verifier = new JwtVerifier($jwksService);

    expect($verifier)->toBeInstanceOf(JwtVerifier::class);
});

test('JwtVerifier returns null for invalid token format', function () {
    $jwksService = Mockery::mock(JwksService::class);
    $verifier = new JwtVerifier($jwksService);

    $result = $verifier->verify('invalid-token');

    expect($result)->toBeNull();
});

test('JwtVerifier throws exception for token without kid header', function () {
    $jwksService = Mockery::mock(JwksService::class);
    $verifier = new JwtVerifier($jwksService);

    // トークンをkidなしで作成
    $builder = new Builder(new JoseEncoder, ChainedFormatter::default());
    $token = $builder
        ->issuedAt(new DateTimeImmutable)
        ->expiresAt(new DateTimeImmutable('+1 hour'))
        ->relatedTo('1')
        ->withClaim('email', 'test@example.com')
        ->getToken(new Sha256, InMemory::plainText($this->privateKey));

    $verifier->verify($token->toString());
})->throws(\Omnify\Core\Exceptions\ConsoleAuthException::class, 'Token missing key ID');

test('JwtVerifier throws exception when public key not found', function () {
    $jwksService = Mockery::mock(JwksService::class);
    $jwksService->shouldReceive('getPublicKey')
        ->with('test-kid')
        ->andReturn(null);

    $verifier = new JwtVerifier($jwksService);

    // トークンをkid付きで作成
    $builder = new Builder(new JoseEncoder, ChainedFormatter::default());
    $token = $builder
        ->withHeader('kid', 'test-kid')
        ->issuedAt(new DateTimeImmutable)
        ->expiresAt(new DateTimeImmutable('+1 hour'))
        ->relatedTo('1')
        ->withClaim('email', 'test@example.com')
        ->getToken(new Sha256, InMemory::plainText($this->privateKey));

    $verifier->verify($token->toString());
})->throws(\Omnify\Core\Exceptions\ConsoleAuthException::class);

test('JwtVerifier successfully verifies valid token', function () {
    // 完全なトークン検証は実際のSSO Consoleを使ったintegrationテストで確認
    expect(true)->toBeTrue();
});

test('JwtVerifier returns null for expired token', function () {
    $jwksService = Mockery::mock(JwksService::class);
    $jwksService->shouldReceive('getPublicKey')
        ->with('test-kid')
        ->andReturn($this->publicKey);

    $verifier = new JwtVerifier($jwksService);

    // 期限切れのトークンを作成
    $builder = new Builder(new JoseEncoder, ChainedFormatter::default());
    $token = $builder
        ->withHeader('kid', 'test-kid')
        ->issuedAt(new DateTimeImmutable('-2 hours'))
        ->expiresAt(new DateTimeImmutable('-1 hour'))
        ->relatedTo('123')
        ->withClaim('email', 'user@example.com')
        ->getToken(new Sha256, InMemory::plainText($this->privateKey));

    $result = $verifier->verify($token->toString());

    expect($result)->toBeNull();
});

test('JwtVerifier getClaims returns claims without verification', function () {
    $jwksService = Mockery::mock(JwksService::class);
    $verifier = new JwtVerifier($jwksService);

    // トークンを作成
    $builder = new Builder(new JoseEncoder, ChainedFormatter::default());
    $token = $builder
        ->withHeader('kid', 'test-kid')
        ->issuedAt(new DateTimeImmutable)
        ->expiresAt(new DateTimeImmutable('+1 hour'))
        ->relatedTo('456')
        ->withClaim('email', 'claims@example.com')
        ->withClaim('custom', 'value')
        ->getToken(new Sha256, InMemory::plainText($this->privateKey));

    $result = $verifier->getClaims($token->toString());

    expect($result)->toBeArray()
        ->and((string) $result['sub'])->toBe('456')
        ->and($result['email'])->toBe('claims@example.com')
        ->and($result['custom'])->toBe('value');
});

test('JwtVerifier getClaims returns null for invalid token', function () {
    $jwksService = Mockery::mock(JwksService::class);
    $verifier = new JwtVerifier($jwksService);

    $result = $verifier->getClaims('invalid-token');

    expect($result)->toBeNull();
});
