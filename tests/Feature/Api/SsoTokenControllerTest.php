<?php

/**
 * SsoTokenController Feature Tests
 *
 * API トークン管理コントローラーのテスト
 * - GET /api/sso/tokens → list user's tokens
 * - DELETE /api/sso/tokens/{tokenId} → revoke specific token
 * - POST /api/sso/tokens/revoke-others → revoke all except current
 *
 * Routes are registered by ServiceProvider under 'api/sso' prefix
 * with 'core.auth' middleware. We use Sanctum::actingAs() to authenticate.
 */

use Laravel\Sanctum\Sanctum;
use Omnify\Core\Models\User;

// =============================================================================
// Index Tests — GET /api/sso/tokens
// =============================================================================

test('index requires authentication', function () {
    $response = $this->getJson('/api/sso/tokens');

    $response->assertStatus(401);
});

test('index returns user tokens', function () {
    $user = User::factory()->create();

    // Create some tokens
    $user->createToken('mobile-app');
    $user->createToken('tablet-app');

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/sso/tokens');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'tokens' => [
                '*' => ['id', 'name', 'last_used_at', 'created_at'],
            ],
        ])
        ->assertJsonCount(2, 'tokens');
});

test('index returns empty when no tokens', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/sso/tokens');

    $response->assertStatus(200)
        ->assertJsonCount(0, 'tokens');
});

test('index orders tokens by last_used_at desc', function () {
    $user = User::factory()->create();

    // Create tokens
    $user->createToken('oldest');
    $user->createToken('newest');
    $user->createToken('middle');

    // Set last_used_at via DB query to ensure persistence
    \Laravel\Sanctum\PersonalAccessToken::where('name', 'oldest')
        ->update(['last_used_at' => now()->subDays(3)]);
    \Laravel\Sanctum\PersonalAccessToken::where('name', 'newest')
        ->update(['last_used_at' => now()]);
    \Laravel\Sanctum\PersonalAccessToken::where('name', 'middle')
        ->update(['last_used_at' => now()->subDay()]);

    Sanctum::actingAs($user);

    $response = $this->getJson('/api/sso/tokens');

    $response->assertStatus(200);

    $tokens = $response->json('tokens');
    expect($tokens[0]['name'])->toBe('newest');
    expect($tokens[1]['name'])->toBe('middle');
    expect($tokens[2]['name'])->toBe('oldest');
});

test('index does not return other user tokens', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $user1->createToken('user1-token');
    $user2->createToken('user2-token');

    Sanctum::actingAs($user1);

    $response = $this->getJson('/api/sso/tokens');

    $response->assertStatus(200)
        ->assertJsonCount(1, 'tokens');

    expect($response->json('tokens.0.name'))->toBe('user1-token');
});

// =============================================================================
// Destroy Tests — DELETE /api/sso/tokens/{tokenId}
// =============================================================================

test('destroy requires authentication', function () {
    $response = $this->deleteJson('/api/sso/tokens/1');

    $response->assertStatus(401);
});

test('destroy revokes specific token', function () {
    $user = User::factory()->create();

    $token1 = $user->createToken('keep-this');
    $token2 = $user->createToken('delete-this');

    Sanctum::actingAs($user);

    $response = $this->deleteJson("/api/sso/tokens/{$token2->accessToken->id}");

    $response->assertStatus(200)
        ->assertJson(['message' => 'Token revoked successfully']);

    // token2 should be deleted, token1 remains
    expect($user->tokens()->count())->toBe(1);
    expect($user->tokens()->first()->name)->toBe('keep-this');
});

test('destroy returns 404 for non-existent token', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $response = $this->deleteJson('/api/sso/tokens/99999');

    $response->assertStatus(404)
        ->assertJson(['error' => 'TOKEN_NOT_FOUND']);
});

test('destroy cannot revoke other user token', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $otherToken = $user2->createToken('other-user-token');

    Sanctum::actingAs($user1);

    $response = $this->deleteJson("/api/sso/tokens/{$otherToken->accessToken->id}");

    $response->assertStatus(404)
        ->assertJson(['error' => 'TOKEN_NOT_FOUND']);

    // Token should still exist
    expect($user2->tokens()->count())->toBe(1);
});

// =============================================================================
// Revoke Others Tests — POST /api/sso/tokens/revoke-others
// =============================================================================

test('revoke others requires authentication', function () {
    $response = $this->postJson('/api/sso/tokens/revoke-others');

    $response->assertStatus(401);
});

test('revoke others deletes all except current token', function () {
    $user = User::factory()->create();

    // Create multiple tokens
    $user->createToken('old-device-1');
    $user->createToken('old-device-2');
    $currentToken = $user->createToken('current-device');

    // Use Sanctum::actingAs for auth, then override with real token
    // so currentAccessToken()->id returns the actual token ID
    Sanctum::actingAs($user);
    $user->withAccessToken($currentToken->accessToken);

    $response = $this->postJson('/api/sso/tokens/revoke-others');

    $response->assertStatus(200)
        ->assertJson(['message' => 'Tokens revoked successfully']);

    // Should have revoked 2 tokens (the old ones)
    expect($response->json('revoked_count'))->toBe(2);

    // Only current token remains
    expect($user->tokens()->count())->toBe(1);
    expect($user->tokens()->first()->id)->toBe($currentToken->accessToken->id);
});

test('revoke others returns deleted count', function () {
    $user = User::factory()->create();

    // Create 3 extra tokens
    $user->createToken('device-1');
    $user->createToken('device-2');
    $user->createToken('device-3');
    $currentToken = $user->createToken('current');

    Sanctum::actingAs($user);
    $user->withAccessToken($currentToken->accessToken);

    $response = $this->postJson('/api/sso/tokens/revoke-others');

    $response->assertStatus(200);
    expect($response->json('revoked_count'))->toBe(3);
});

test('revoke others with no other tokens returns zero count', function () {
    $user = User::factory()->create();
    $currentToken = $user->createToken('only-device');

    Sanctum::actingAs($user);
    $user->withAccessToken($currentToken->accessToken);

    $response = $this->postJson('/api/sso/tokens/revoke-others');

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Tokens revoked successfully',
            'revoked_count' => 0,
        ]);

    // The single token should remain
    expect($user->tokens()->count())->toBe(1);
});
