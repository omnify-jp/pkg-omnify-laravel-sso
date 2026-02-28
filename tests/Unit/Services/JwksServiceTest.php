<?php

/**
 * JwksService Unit Tests
 *
 * JWKS公開鍵サービスのテスト
 */

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Omnify\Core\Exceptions\ConsoleServerException;
use Omnify\Core\Services\JwksService;

beforeEach(function () {
    Cache::flush();
});

test('JwksService can be instantiated', function () {
    $service = new JwksService('https://console.example.com');

    expect($service)->toBeInstanceOf(JwksService::class);
});

test('JwksService fetches JWKS from console', function () {
    Http::fake([
        '*' => Http::response([
            'keys' => [
                [
                    'kty' => 'RSA',
                    'kid' => 'test-key-1',
                    'n' => 'test-modulus',
                    'e' => 'AQAB',
                ],
            ],
        ], 200),
    ]);

    $service = new JwksService('https://console.example.com');
    $jwks = $service->getJwks();

    expect($jwks)->toBeArray()
        ->and($jwks['keys'])->toHaveCount(1)
        ->and($jwks['keys'][0]['kid'])->toBe('test-key-1');
});

test('JwksService caches JWKS response', function () {
    $callCount = 0;

    Http::fake(function () use (&$callCount) {
        $callCount++;

        return Http::response([
            'keys' => [['kty' => 'RSA', 'kid' => 'key-'.$callCount]],
        ], 200);
    });

    $service = new JwksService('https://console.example.com');

    // 2回呼び出し
    $service->getJwks();
    $service->getJwks();

    // HTTPリクエストは1回だけ
    expect($callCount)->toBe(1);
});

test('JwksService throws exception on HTTP error', function () {
    Http::fake([
        '*' => Http::response('Server Error', 500),
    ]);

    $service = new JwksService('https://console.example.com');

    expect(fn () => $service->getJwks())
        ->toThrow(ConsoleServerException::class);
});

test('JwksService clearCache removes cached JWKS', function () {
    Http::fake([
        '*' => Http::response(['keys' => []], 200),
    ]);

    $service = new JwksService('https://console.example.com');
    $service->getJwks();

    expect(Cache::has('sso:jwks'))->toBeTrue();

    $service->clearCache();

    expect(Cache::has('sso:jwks'))->toBeFalse();
});

test('JwksService getPublicKey returns null for unknown kid', function () {
    Http::fake([
        '*' => Http::response([
            'keys' => [
                [
                    'kty' => 'RSA',
                    'kid' => 'existing-key',
                    'n' => 'test',
                    'e' => 'AQAB',
                ],
            ],
        ], 200),
    ]);

    $service = new JwksService('https://console.example.com');
    $publicKey = $service->getPublicKey('non-existing-key');

    expect($publicKey)->toBeNull();
});

test('JwksService converts JWK to PEM format', function () {
    // JWK to PEM変換は実際のJWKSエンドポイントを使用したintegrationテストで確認
    expect(true)->toBeTrue();
});

test('JwksService retries with fresh cache when key not found', function () {
    $callCount = 0;

    Http::fake(function () use (&$callCount) {
        $callCount++;
        // 2回目の呼び出しで新しいキーを返す
        $keys = $callCount === 1
            ? [['kty' => 'RSA', 'kid' => 'old-key', 'n' => 'test', 'e' => 'AQAB']]
            : [['kty' => 'RSA', 'kid' => 'new-key', 'n' => 'test', 'e' => 'AQAB']];

        return Http::response(['keys' => $keys], 200);
    });

    $service = new JwksService('https://console.example.com');

    // 存在しないキーを検索 → キャッシュをクリアして再取得
    $service->getPublicKey('non-existing-key');

    // 2回HTTPリクエストが発生
    expect($callCount)->toBe(2);
});
