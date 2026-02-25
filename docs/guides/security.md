# Security Guide

Security features and best practices for the SSO Client package.

## Security Features

| Feature                  | Description                               |
| ------------------------ | ----------------------------------------- |
| Open Redirect Protection | Validates redirect URLs against whitelist |
| JWT Verification         | RS256 signature verification with JWKS    |
| CSRF Protection          | Stateful requests via Laravel Sanctum     |
| Input Validation         | Strict validation on all inputs           |
| Email Masking            | Privacy protection in logs                |
| Secure Token Storage     | Encrypted token storage                   |

## Open Redirect Protection

### The Problem

Open redirect vulnerabilities allow attackers to redirect users to malicious sites:

```
https://yourapp.com/logout?redirect_uri=https://evil.com/phishing
```

### The Solution

The package validates all redirect URLs:

```php
use Omnify\SsoClient\Support\RedirectUrlValidator;

$validator = new RedirectUrlValidator();
$safeUrl = $validator->validate($userProvidedUrl, '/fallback');
```

### Configuration

```env
# Allow specific hosts (comma-separated)
SSO_ALLOWED_REDIRECT_HOSTS=myapp.com,api.myapp.com

# Allow subdomains with wildcard
SSO_ALLOWED_REDIRECT_HOSTS=*.myapp.com,myapp.com

# Require HTTPS (production)
SSO_REQUIRE_HTTPS_REDIRECTS=true
```

### What Gets Blocked

| Input                    | Result  | Reason                |
| ------------------------ | ------- | --------------------- |
| `https://evil.com`       | Blocked | Not in allowed hosts  |
| `//evil.com`             | Blocked | Protocol-relative URL |
| `javascript:alert(1)`    | Blocked | Dangerous protocol    |
| `/dashboard`             | Allowed | Relative URL          |
| `https://myapp.com/page` | Allowed | In allowed hosts      |

## JWT Security

### Verification Process

1. **Fetch JWKS** - Public keys from Console (cached)
2. **Verify Algorithm** - Only RS256 accepted
3. **Verify Signature** - Cryptographic verification
4. **Validate Claims** - exp, aud, iss, sub

### Protected Against

| Attack              | Protection             |
| ------------------- | ---------------------- |
| Token tampering     | Signature verification |
| Algorithm confusion | Only RS256 accepted    |
| Expired tokens      | exp claim validation   |
| Token replay        | Short-lived tokens     |
| None algorithm      | Explicitly rejected    |

### Configuration

```env
# JWKS cache duration (minutes)
SSO_JWKS_CACHE_TTL=60
```

## Session Security

### Sanctum Configuration

```php
// config/sanctum.php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', '')),
```

```env
SANCTUM_STATEFUL_DOMAINS=myapp.com,www.myapp.com
SESSION_DOMAIN=.myapp.com
SESSION_SECURE_COOKIE=true
```

### Cookie Settings

```php
// config/session.php
return [
    'secure' => env('SESSION_SECURE_COOKIE', true),
    'same_site' => 'lax',
    'http_only' => true,
];
```

## Input Validation

All inputs are validated:

```php
// In SsoCallbackController
$validated = $request->validate([
    'code' => ['required', 'string'],
    'device_name' => ['nullable', 'string', 'max:255'],
]);
```

### Admin API Validation

```php
// Role creation
$validated = $request->validate([
    'name' => ['required', 'string', 'max:255'],
    'slug' => ['required', 'string', 'max:255', 'unique:roles'],
    'description' => ['nullable', 'string'],
    'level' => ['required', 'integer', 'min:0', 'max:1000'],
]);
```

## Security Headers

Recommended headers for your application:

```php
// app/Http/Middleware/SecurityHeaders.php
public function handle($request, $next)
{
    $response = $next($request);
    
    return $response
        ->header('X-Content-Type-Options', 'nosniff')
        ->header('X-Frame-Options', 'DENY')
        ->header('X-XSS-Protection', '1; mode=block')
        ->header('Strict-Transport-Security', 'max-age=31536000; includeSubDomains')
        ->header('Content-Security-Policy', "default-src 'self'");
}
```

## Rate Limiting

Add rate limiting to SSO endpoints:

```php
// routes/api.php
Route::middleware(['throttle:sso'])->group(function () {
    Route::post('/sso/callback', [SsoCallbackController::class, 'callback']);
});
```

```php
// app/Providers/RouteServiceProvider.php
RateLimiter::for('sso', function (Request $request) {
    return Limit::perMinute(10)->by($request->ip());
});
```

## Security Logging

All security events are logged:

```php
sso_log()->securityEvent('blocked_redirect', [
    'requested_uri' => $url,
    'ip' => request()->ip(),
]);
```

Monitor logs for:

- Multiple failed login attempts
- Blocked redirect attempts
- Invalid token submissions
- Unusual API errors

## Security Checklist

### Production Deployment

- [ ] HTTPS enabled (`APP_URL=https://...`)
- [ ] Secure cookies enabled (`SESSION_SECURE_COOKIE=true`)
- [ ] CORS properly configured
- [ ] Rate limiting enabled
- [ ] Allowed redirect hosts configured
- [ ] Security headers set
- [ ] Logging enabled for audit trail
- [ ] Regular dependency updates

### Environment Variables

```env
# Required for production
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourapp.com

# Session security
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

# SSO security
SSO_REQUIRE_HTTPS_REDIRECTS=true
SSO_ALLOWED_REDIRECT_HOSTS=yourapp.com,*.yourapp.com
SSO_LOGGING_ENABLED=true
```

## Reporting Vulnerabilities

If you discover a security vulnerability:

1. **Do NOT** open a public issue
2. Email security@famgia.com
3. Include detailed reproduction steps
4. Allow reasonable time for fix before disclosure

## Security Updates

Stay updated:

```bash
# Check for updates
composer outdated famgia/omnify-sso-client

# Update package
composer update famgia/omnify-sso-client
```

Subscribe to security advisories for notifications.
