<?php

declare(strict_types=1);

namespace Omnify\SsoClient\Services;

use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Support\Facades\Log;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Signer\Key\InMemory;
use Lcobucci\JWT\Signer\Rsa\Sha256;
use Lcobucci\JWT\Token\Parser;
use Lcobucci\JWT\Token\Plain;
use Lcobucci\JWT\Validation\Constraint\LooseValidAt;
use Lcobucci\JWT\Validation\Constraint\SignedWith;
use Lcobucci\JWT\Validation\Validator;
use Omnify\SsoClient\Exceptions\ConsoleAuthException;
use Psr\Clock\ClockInterface;

/**
 * JWT Token検証サービス
 *
 * lcobucci/jwt v5.x対応
 */
class JwtVerifier
{
    private Parser $parser;

    private Validator $validator;

    public function __construct(
        private readonly JwksService $jwksService
    ) {
        $this->parser = new Parser(new JoseEncoder);
        $this->validator = new Validator;
    }

    /**
     * JWTトークンを検証してパースする
     *
     * @return array{sub: int, email: string, name: string, aud: string|array|null}|null
     */
    public function verify(string $token): ?array
    {
        try {
            // トークンをパースしてヘッダー(kid)を取得
            $parsedToken = $this->parser->parse($token);

            if (! $parsedToken instanceof Plain) {
                Log::warning('[SSO] Token is not a Plain token');

                return null;
            }

            // ヘッダーからkey IDを取得
            $kid = $parsedToken->headers()->get('kid');
            if (! $kid) {
                Log::warning('[SSO] Token missing key ID (kid) header');
                throw new ConsoleAuthException('Token missing key ID');
            }

            // 公開鍵を取得
            $publicKey = $this->jwksService->getPublicKey($kid);
            if (! $publicKey) {
                Log::warning('[SSO] Public key not found for kid: '.$kid);
                throw new ConsoleAuthException('Public key not found for kid: '.$kid);
            }

            // トークンを検証（署名と有効期限）
            // PSR-20 Clock実装を使用（クロックスキュー対策として5分の許容を追加）
            $clock = new class implements ClockInterface
            {
                public function now(): DateTimeImmutable
                {
                    return new DateTimeImmutable('now', new DateTimeZone('UTC'));
                }
            };

            // 5分の許容時間を設定（サーバー間の時刻ずれ対策）
            $leeway = new \DateInterval('PT5M');

            $constraints = [
                new SignedWith(new Sha256, InMemory::plainText($publicKey)),
                new LooseValidAt($clock, $leeway),
            ];

            if (! $this->validator->validate($parsedToken, ...$constraints)) {
                // 具体的な検証エラーを確認
                foreach ($constraints as $constraint) {
                    try {
                        $this->validator->assert($parsedToken, $constraint);
                    } catch (\Throwable $e) {
                        Log::warning('[SSO] Token validation failed: '.$e->getMessage());
                    }
                }

                return null;
            }

            // クレームを抽出
            $claims = $parsedToken->claims();

            return [
                'sub' => $claims->get('sub'),
                'email' => $claims->get('email'),
                'name' => $claims->get('name'),
                'aud' => $claims->get('aud'),
            ];
        } catch (ConsoleAuthException $e) {
            Log::warning('[SSO] Auth exception: '.$e->getMessage());
            throw $e;
        } catch (\Throwable $e) {
            Log::error('[SSO] JWT verification failed: '.$e->getMessage(), [
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    /**
     * 検証なしでトークンからクレームを取得
     * 既に検証済みのトークンにのみ使用
     *
     * @return array<string, mixed>|null
     */
    public function getClaims(string $token): ?array
    {
        try {
            $parsedToken = $this->parser->parse($token);

            if (! $parsedToken instanceof Plain) {
                return null;
            }

            return $parsedToken->claims()->all();
        } catch (\Throwable) {
            return null;
        }
    }
}
