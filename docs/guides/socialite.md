# Social Login — Laravel Socialite Integration

## Tổng quan

Core package hỗ trợ **social login** (Google, GitHub, Facebook, v.v.) thông qua Laravel Socialite. Feature này chỉ hoạt động ở **standalone mode** — console mode dùng OAuth SSO riêng.

Mặc định feature bị **tắt**. Host app bật bằng env `OMNIFY_SOCIALITE_ENABLED=true` + cấu hình provider credentials.

---

## Flow tổng thể

```
┌─ LOGIN PAGE ────────────────────────────────────────────────┐
│                                                              │
│  1. User click "Login with Google"                          │
│     └─ <a href="/auth/google/redirect">                     │
│                                                              │
└──────────────────────────────────────────────────────────────┘
                        │
                        ▼
┌─ SOCIALITE REDIRECT ────────────────────────────────────────┐
│                                                              │
│  2. GET /auth/google/redirect                               │
│     └─ SocialLoginController::redirect('google')            │
│        └─ Socialite::driver('google')->redirect()           │
│           └─ 302 → https://accounts.google.com/o/oauth2/..  │
│                                                              │
└──────────────────────────────────────────────────────────────┘
                        │
                        │  User authenticates with Google
                        ▼
┌─ GOOGLE CALLBACK ───────────────────────────────────────────┐
│                                                              │
│  3. Google redirects → GET /auth/google/callback             │
│     └─ SocialLoginController::callback('google')            │
│        ├─ Socialite::driver('google')->user()               │
│        │   └─ Returns: { id, name, email, avatar, token }  │
│        │                                                     │
│        ├─ Find SocialAccount by provider + provider_id      │
│        │   ├─ Found → login as linked user                  │
│        │   └─ Not found →                                   │
│        │       ├─ Find User by email                        │
│        │       │   ├─ Found → link SocialAccount + login    │
│        │       │   └─ Not found → create User + link + login│
│        │                                                     │
│        └─ Auth::login($user) → redirect to dashboard        │
│                                                              │
└──────────────────────────────────────────────────────────────┘
```

---

## Chi tiết từng bước

### 1. Bật feature

```env
# .env
OMNIFY_SOCIALITE_ENABLED=true
```

### 2. Cấu hình provider

Có 2 cách:

**Cách A — Trong `omnify-auth.php` (khuyến nghị):**

```php
// config/omnify-auth.php
'socialite' => [
    'enabled' => env('OMNIFY_SOCIALITE_ENABLED', false),
    'providers' => [
        'google' => [
            'client_id' => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'redirect' => env('GOOGLE_REDIRECT_URL', '/auth/google/callback'),
        ],
        'github' => [
            'client_id' => env('GITHUB_CLIENT_ID'),
            'client_secret' => env('GITHUB_CLIENT_SECRET'),
            'redirect' => env('GITHUB_REDIRECT_URL', '/auth/github/callback'),
        ],
    ],
],
```

Core tự merge providers vào `config('services.*')` trong `CoreServiceProvider::configureSocialite()` — host app **không cần** sửa `config/services.php`.

**Cách B — Trực tiếp trong `services.php` (standard Laravel):**

```php
// config/services.php
'google' => [
    'client_id' => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect' => env('GOOGLE_REDIRECT_URL', '/auth/google/callback'),
],
```

> **Lưu ý:** Nếu dùng cách B, vẫn phải khai báo provider key trong `omnify-auth.socialite.providers` (có thể để mảng rỗng) để route và login page nhận biết provider nào đang enabled.

### 3. Env variables

```env
# Google
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
GOOGLE_REDIRECT_URL=/auth/google/callback

# GitHub
GITHUB_CLIENT_ID=your-github-client-id
GITHUB_CLIENT_SECRET=your-github-client-secret
GITHUB_REDIRECT_URL=/auth/github/callback
```

### 4. Migration

Chạy migration để tạo bảng `social_accounts`:

```bash
php artisan migrate
```

---

## Database Schema

### Bảng `social_accounts`

| Column | Type | Description |
|--------|------|-------------|
| `id` | ULID | Primary key |
| `user_id` | ULID (FK) | Link đến bảng `users` |
| `provider` | string(30) | Tên provider: `google`, `github`, etc. |
| `provider_id` | string | ID user từ provider (unique per provider) |
| `provider_email` | string? | Email từ provider |
| `provider_avatar` | string? | Avatar URL từ provider |
| `access_token` | text? | OAuth access token (encrypted) |
| `refresh_token` | text? | OAuth refresh token (encrypted) |
| `token_expires_at` | timestamp? | Thời điểm token hết hạn |
| `created_at` | timestamp | |
| `updated_at` | timestamp | |

**Constraints:**
- Unique: `[provider, provider_id]` — mỗi provider chỉ có 1 account per provider_id
- Index: `[user_id, provider]` — tra cứu nhanh social accounts của user

---

## Routes

Routes chỉ được load khi `omnify-auth.socialite.enabled = true`:

```
GET  /auth/{provider}/redirect   → SocialLoginController::redirect()
GET  /auth/{provider}/callback   → SocialLoginController::callback()
```

Middleware: `['web', 'guest']` — chỉ cho guests (chưa đăng nhập).

---

## User matching logic

Khi nhận callback từ provider:

```
1. Tìm SocialAccount (provider + provider_id)
   ├─ Tìm thấy → cập nhật tokens → login as linked user
   └─ Không tìm thấy →
       2. Tìm User by email
          ├─ Tìm thấy → tạo SocialAccount link → login
          └─ Không tìm thấy →
              3. Tạo User mới
                 ├─ password = random (is_default_password = true)
                 ├─ email_verified_at = now()
                 ├─ avatar_url = provider avatar
                 └─ Tạo SocialAccount link → login
```

**Lưu ý:**
- User được tạo qua social login có `is_default_password = true` — host app có thể yêu cầu user đặt password sau
- Email từ provider được tự động verify (`email_verified_at = now()`)
- Một user có thể link nhiều providers (Google + GitHub cùng 1 user)

---

## Model & Trait

### SocialAccount Model

```php
use Omnify\Core\Models\SocialAccount;

$account = SocialAccount::where('provider', 'google')
    ->where('provider_id', $googleUserId)
    ->first();

$account->user; // → User model
```

### HasSocialAccounts Trait

Đã được thêm vào `Omnify\Core\Models\User`:

```php
$user->socialAccounts();              // HasMany<SocialAccount>
$user->socialAccount('google');       // SocialAccount|null
$user->hasSocialAccount('google');    // bool
```

---

## Tùy chỉnh

### Override controller logic

Host app có thể publish và override `SocialLoginController`:

```php
// app/Http/Controllers/Auth/SocialLoginController.php
use Omnify\Core\Http\Controllers\Standalone\Auth\SocialLoginController as BaseSocialLoginController;

class SocialLoginController extends BaseSocialLoginController
{
    protected function createSocialAccount($user, string $provider, $socialUser): SocialAccount
    {
        // Custom logic: assign default role, send welcome email, etc.
        $account = parent::createSocialAccount($user, $provider, $socialUser);

        $user->assignRole('member');

        return $account;
    }
}
```

### Thêm provider mới

Chỉ cần thêm config + env variables:

```php
// config/omnify-auth.php
'socialite' => [
    'providers' => [
        // ... existing
        'facebook' => [
            'client_id' => env('FACEBOOK_CLIENT_ID'),
            'client_secret' => env('FACEBOOK_CLIENT_SECRET'),
            'redirect' => env('FACEBOOK_REDIRECT_URL', '/auth/facebook/callback'),
        ],
    ],
],
```

Login page sẽ tự hiện button cho provider mới. Nếu provider không có icon trong `SocialIcon` component, button sẽ hiển thị tên provider dạng text.

---

## Supported Providers (built-in Socialite)

| Provider | Config Key | Redirect URL Pattern |
|----------|-----------|---------------------|
| Google | `google` | `/auth/google/callback` |
| GitHub | `github` | `/auth/github/callback` |
| Facebook | `facebook` | `/auth/facebook/callback` |
| Twitter/X | `twitter` | `/auth/twitter/callback` |
| LinkedIn | `linkedin` | `/auth/linkedin/callback` |
| Apple | `apple` | `/auth/apple/callback` |
| Microsoft | `microsoft` | `/auth/microsoft/callback` |

Các provider khác có thể dùng thêm package `socialiteproviders/*` (community).

---

## Checklist khi enable social login

- [ ] Đặt `OMNIFY_SOCIALITE_ENABLED=true` trong `.env`
- [ ] Cấu hình ít nhất 1 provider với client_id + client_secret
- [ ] Chạy `php artisan migrate` (bảng `social_accounts`)
- [ ] Đăng ký OAuth app trên Google Console / GitHub Settings / etc.
- [ ] Đặt redirect URL đúng: `https://your-domain.com/auth/{provider}/callback`
- [ ] Test login flow: click button → provider auth → redirect back → logged in
