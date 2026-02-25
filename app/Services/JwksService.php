<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Omnify\SsoClient\Exceptions\ConsoleServerException;

class JwksService
{
    private const CACHE_KEY = 'sso:jwks';

    public function __construct(
        private readonly string $consoleUrl,
        private readonly int $cacheTtlMinutes = 60
    ) {}

    /**
     * Get JWKS from Console (cached).
     *
     * @return array<string, mixed>
     */
    public function getJwks(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            now()->addMinutes($this->cacheTtlMinutes),
            fn () => $this->fetchJwks()
        );
    }

    /**
     * Get public key by key ID.
     *
     * @return string PEM formatted public key
     */
    public function getPublicKey(string $kid): ?string
    {
        $jwks = $this->getJwks();

        foreach ($jwks['keys'] ?? [] as $key) {
            if (($key['kid'] ?? null) === $kid) {
                return $this->jwkToPem($key);
            }
        }

        // Key not found, clear cache and try again
        $this->clearCache();
        $jwks = $this->getJwks();

        foreach ($jwks['keys'] ?? [] as $key) {
            if (($key['kid'] ?? null) === $kid) {
                return $this->jwkToPem($key);
            }
        }

        return null;
    }

    /**
     * Clear JWKS cache.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Fetch JWKS from Console.
     *
     * @return array<string, mixed>
     */
    private function fetchJwks(): array
    {
        $response = Http::timeout(10)
            ->get("{$this->consoleUrl}/.well-known/jwks.json");

        if (! $response->successful()) {
            throw new ConsoleServerException(
                'Failed to fetch JWKS from Console',
                $response->status()
            );
        }

        return $response->json();
    }

    /**
     * Convert JWK to PEM format.
     *
     * JWKからPEM形式の公開鍵に変換する
     *
     * @param  array<string, mixed>  $jwk
     */
    private function jwkToPem(array $jwk): string
    {
        if (($jwk['kty'] ?? '') !== 'RSA') {
            throw new \InvalidArgumentException('Only RSA keys are supported');
        }

        $n = $this->base64UrlDecode($jwk['n']);
        $e = $this->base64UrlDecode($jwk['e']);

        // Build RSA public key - ASN.1 DER encoding
        $modulus = chr(0x02).$this->encodeLength(strlen($n)).$n;
        $exponent = chr(0x02).$this->encodeLength(strlen($e)).$e;

        $rsaPublicKey = chr(0x30).$this->encodeLength(strlen($modulus.$exponent)).$modulus.$exponent;

        // Build the bit string
        $rsaPublicKey = "\x00".$rsaPublicKey;
        $rsaPublicKey = chr(0x03).$this->encodeLength(strlen($rsaPublicKey)).$rsaPublicKey;

        // Add algorithm identifier (RSA encryption OID)
        $algorithmIdentifier = hex2bin('300d06092a864886f70d0101010500');

        $rsaPublicKey = chr(0x30).$this->encodeLength(strlen($algorithmIdentifier.$rsaPublicKey)).$algorithmIdentifier.$rsaPublicKey;

        return "-----BEGIN PUBLIC KEY-----\n".
            chunk_split(base64_encode($rsaPublicKey), 64, "\n").
            "-----END PUBLIC KEY-----\n";
    }

    /**
     * Base64 URL decode.
     */
    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        $decoded = base64_decode(strtr($data, '-_', '+/'), true);

        if ($decoded === false) {
            throw new \InvalidArgumentException('Invalid base64url encoding');
        }

        // Ensure the first byte indicates a positive number for ASN.1
        if (ord($decoded[0]) > 127) {
            $decoded = "\x00".$decoded;
        }

        return $decoded;
    }

    /**
     * Encode ASN.1 length.
     */
    private function encodeLength(int $length): string
    {
        if ($length <= 127) {
            return chr($length);
        }

        $temp = ltrim(pack('N', $length), "\x00");

        return pack('Ca*', 0x80 | strlen($temp), $temp);
    }
}
