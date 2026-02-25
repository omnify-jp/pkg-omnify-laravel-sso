# Authentication Guide

## ServiceInstance Architecture

Omnify Console uses a **ServiceInstance** architecture for multi-tenant, multi-environment deployments:

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                              SERVICE (Global)                                │
│   slug: "your-service"                                                       │
│   default_redirect_uris: ["https://your-service.com/*"]                      │
└─────────────────────────────────────────────────────────────────────────────┘
                                        │
                                        │ 1:N
                        ┌───────────────┼───────────────┐
                        ▼               ▼               ▼
┌──────────────────────────┐ ┌──────────────────────────┐ ┌──────────────────────────┐
│    ServiceInstance       │ │    ServiceInstance       │ │    ServiceInstance       │
│    (Org A - Production)  │ │    (Org A - Staging)     │ │    (Org B - Production)  │
├──────────────────────────┤ ├──────────────────────────┤ ├──────────────────────────┤
│ client_id: si_abc123     │ │ client_id: si_abc456     │ │ client_id: si_xyz789     │
│ environment: production  │ │ environment: staging     │ │ environment: production  │
│ allowed_redirect_uris:   │ │ allowed_redirect_uris:   │ │ allowed_redirect_uris:   │
│   (uses service default) │ │   ["https://stg.*.com/*"]│ │   ["https://orgb.com/*"] │
└──────────────────────────┘ └──────────────────────────┘ └──────────────────────────┘
```

**Key Points:**
- Your service only needs the `service_slug` - no client credentials required
- Console manages credentials per-organization via ServiceInstance
- Each organization can have multiple instances (production, staging, etc.)
- Redirect URIs are validated against instance-specific or service default URIs

## SSO Flow Overview

```
┌─────────────────┐         ┌─────────────────┐         ┌─────────────────┐
│                 │         │                 │         │                 │
│   Your App      │────1───▶│  Omnify Console │────2───▶│   Your App      │
│   (Frontend)    │         │   (Login Page)  │         │   (Callback)    │
│                 │◀───5────│                 │◀───3────│                 │
└─────────────────┘         └─────────────────┘         └─────────────────┘
                                                               │
                                                               │ 4
                                                               ▼
                                                        ┌─────────────────┐
                                                        │                 │
                                                        │   Your API      │
                                                        │   (Backend)     │
                                                        │                 │
                                                        └─────────────────┘

1. User clicks login → Redirect to Console with service_slug
2. User authenticates at Console
3. Console finds ServiceInstance for user's org, validates redirect_uri
4. Console redirects back with authorization code
5. Frontend sends code to backend API
6. Backend exchanges code for tokens using service_slug
```

## Step 1: Initiate Login (Frontend)

Redirect the user to Omnify Console:

```javascript
// React/Next.js example
const handleLogin = () => {
  const consoleUrl = process.env.NEXT_PUBLIC_SSO_CONSOLE_URL;
  const serviceSlug = process.env.NEXT_PUBLIC_SSO_SERVICE_SLUG;
  const redirectUri = `${window.location.origin}/sso/callback`;

  const loginUrl = `${consoleUrl}/sso/authorize?` + new URLSearchParams({
    service: serviceSlug,
    redirect_uri: redirectUri,
  });

  window.location.href = loginUrl;
};
```

## Step 2: Handle Callback (Frontend)

After login, Console redirects back with a code:

```javascript
// pages/sso/callback.tsx (Next.js example)
import { useEffect } from 'react';
import { useRouter, useSearchParams } from 'next/navigation';

export default function SsoCallback() {
  const router = useRouter();
  const searchParams = useSearchParams();
  
  useEffect(() => {
    const code = searchParams.get('code');
    
    if (code) {
      exchangeCode(code);
    }
  }, [searchParams]);

  const exchangeCode = async (code: string) => {
    try {
      const response = await fetch('/api/sso/callback', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
        },
        credentials: 'include', // Important for cookies
        body: JSON.stringify({ code }),
      });

      if (response.ok) {
        const data = await response.json();
        // Store user data, redirect to dashboard
        router.push('/dashboard');
      } else {
        // Handle error
        router.push('/login?error=auth_failed');
      }
    } catch (error) {
      router.push('/login?error=network_error');
    }
  };

  return <div>Authenticating...</div>;
}
```

## Step 3: Exchange Code (Backend)

The package handles this automatically via the `/api/sso/callback` endpoint:

```
POST /api/sso/callback
Content-Type: application/json

{
    "code": "authorization_code_from_console"
}
```

**Response (Success):**

```json
{
    "user": {
        "id": 1,
        "console_user_id": 12345,
        "email": "user@example.com",
        "name": "John Doe"
    },
    "organizations": [
        {
            "id": "org-123",
            "name": "My Organization",
            "role": "admin"
        }
    ]
}
```

**Response (Error):**

```json
{
    "error": "INVALID_CODE",
    "message": "Failed to exchange SSO code"
}
```

## Mobile App Authentication

For mobile apps, include `device_name` to receive an API token:

```
POST /api/sso/callback
Content-Type: application/json

{
    "code": "authorization_code",
    "device_name": "iPhone 15 Pro"
}
```

**Response:**

```json
{
    "user": { ... },
    "organizations": [ ... ],
    "token": "1|abcdefghijklmnopqrstuvwxyz..."
}
```

Use the token for subsequent requests:

```
GET /api/protected-resource
Authorization: Bearer 1|abcdefghijklmnopqrstuvwxyz...
```

## Get Current User

```
GET /api/sso/user
```

**Response:**

```json
{
    "user": {
        "id": 1,
        "console_user_id": 12345,
        "email": "user@example.com",
        "name": "John Doe"
    },
    "organizations": [...]
}
```

## Logout

### Local Logout

```
POST /api/sso/logout
```

This:
- Revokes Console tokens
- Deletes API tokens (if using bearer auth)
- Clears session

### Global Logout (Single Sign-Out)

For single sign-out across all Console services:

```
GET /api/sso/global-logout-url?redirect_uri=/logged-out
```

**Response:**

```json
{
    "logout_url": "https://console.omnify.jp/sso/logout?redirect_uri=..."
}
```

Redirect the user to this URL for global logout.

## Token Management (Mobile Apps)

### List Tokens

```
GET /api/sso/tokens
```

### Revoke Token

```
DELETE /api/sso/tokens/{tokenId}
```

### Revoke All Other Tokens

```
POST /api/sso/tokens/revoke-others
```

## JWT Verification

The package automatically verifies JWTs from Console:

1. Fetches JWKS from Console (cached)
2. Verifies token signature (RS256)
3. Validates claims (exp, aud, iss)
4. Extracts user info from claims

You typically don't need to interact with this directly, but if needed:

```php
use Omnify\SsoClient\Services\JwtVerifier;

$verifier = app(JwtVerifier::class);
$claims = $verifier->verify($accessToken);

if ($claims) {
    $userId = $claims['sub'];
    $email = $claims['email'];
}
```

## Session Configuration

For SPA authentication with cookies:

```php
// config/session.php
'domain' => env('SESSION_DOMAIN', null),
'same_site' => 'lax',
'secure' => env('SESSION_SECURE_COOKIE', true),
```

```php
// config/cors.php
'supports_credentials' => true,
```

```env
SESSION_DOMAIN=.example.com
SANCTUM_STATEFUL_DOMAINS=app.example.com,www.example.com
```

## Error Handling

| Error Code        | Description             | Action            |
| ----------------- | ----------------------- | ----------------- |
| `INVALID_CODE`    | Code expired or invalid | Retry login       |
| `INVALID_TOKEN`   | JWT verification failed | Retry login       |
| `UNAUTHENTICATED` | No valid session/token  | Redirect to login |

```javascript
// Frontend error handling
const handleAuthError = (error) => {
  if (error.code === 'INVALID_CODE' || error.code === 'INVALID_TOKEN') {
    // Clear local storage, redirect to login
    localStorage.clear();
    window.location.href = '/login?error=' + error.code;
  }
};
```
