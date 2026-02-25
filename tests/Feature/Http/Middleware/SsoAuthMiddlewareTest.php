<?php

/**
 * SSO Auth Middleware Tests
 *
 * SSO認証ミドルウェアのテスト
 */

use Illuminate\Support\Facades\Route;
use Omnify\SsoClient\Tests\Fixtures\Models\User;

beforeEach(function () {
    // テスト用のルートを定義
    Route::middleware(['sso.auth'])->get('/test-sso-auth', function () {
        return response()->json(['message' => 'authenticated']);
    });

    Route::middleware(['sso.auth'])->get('/test-sso-user', function () {
        return response()->json([
            'user_id' => request()->user()->id,
            'console_user_id' => request()->user()->console_user_id,
        ]);
    });
});

test('sso.auth middleware rejects unauthenticated requests', function () {
    $response = $this->getJson('/test-sso-auth');

    $response->assertStatus(401)
        ->assertJson([
            'error' => 'UNAUTHENTICATED',
        ]);
});

test('sso.auth middleware accepts authenticated requests', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/test-sso-auth');

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'authenticated',
        ]);
});

test('sso.auth middleware provides user in request', function () {
    $user = User::factory()->create([
        'console_user_id' => 12345,
    ]);

    $response = $this->actingAs($user)->getJson('/test-sso-user');

    $response->assertStatus(200)
        ->assertJsonPath('user_id', $user->id)
        ->assertJsonPath('console_user_id', 12345);
});

test('sso.auth middleware accepts user without console_user_id', function () {
    // sso.authはSanctum認証のみをチェック
    // console_user_idの有無は別のミドルウェアまたはビジネスロジックで処理
    $user = User::factory()->withoutConsoleUserId()->create();

    $response = $this->actingAs($user)->getJson('/test-sso-auth');

    // 認証は成功する（console_user_idのチェックはsso.authの責務ではない）
    $response->assertStatus(200);
});
