# Logging Guide

The SSO Client provides dedicated logging for audit trails and debugging.

## Configuration

```env
SSO_LOGGING_ENABLED=true
SSO_LOG_CHANNEL=sso
SSO_LOG_LEVEL=debug
```

## Log File Location

```
storage/logs/sso.log
```

- Daily rotation
- 14 days retention
- Separate from main application logs

## Logged Events

| Event            | Level     | Description                       |
| ---------------- | --------- | --------------------------------- |
| Auth success     | `info`    | Successful authentication         |
| Auth failure     | `warning` | Failed authentication attempt     |
| Code exchange    | `debug`   | SSO code exchange                 |
| JWT verification | `debug`   | Token verification                |
| Token refresh    | `debug`   | Token refresh operations          |
| Logout           | `info`    | User logout                       |
| Security events  | `warning` | Blocked redirects, invalid tokens |
| API errors       | `error`   | Console API failures              |
| Permission sync  | `info`    | Permission synchronization        |

## Using the Logger

### Via Helper Function

```php
use function sso_log;

// Log custom info
sso_log()->info('Custom event', ['user_id' => $user->id]);

// Log warning
sso_log()->warning('Suspicious activity', [
    'ip' => request()->ip(),
    'action' => 'multiple_failed_logins',
]);

// Log error
sso_log()->error('Critical issue', ['details' => $error]);
```

### Via Dependency Injection

```php
use Omnify\SsoClient\Support\SsoLogger;

class MyController extends Controller
{
    public function __construct(
        private readonly SsoLogger $logger
    ) {}

    public function store(Request $request)
    {
        $this->logger->info('Resource created', [
            'resource_id' => $resource->id,
            'user_id' => auth()->id(),
        ]);
    }
}
```

## Built-in Logging Methods

### Authentication

```php
// Log auth attempt
sso_log()->authAttempt($email, $success, $reason);

// Examples:
sso_log()->authAttempt('user@example.com', true);
sso_log()->authAttempt('user@example.com', false, 'Invalid credentials');
```

### SSO Code Exchange

```php
sso_log()->codeExchange($success, $error);

// Examples:
sso_log()->codeExchange(true);
sso_log()->codeExchange(false, 'Code expired');
```

### JWT Verification

```php
sso_log()->jwtVerification($success, $reason);

// Examples:
sso_log()->jwtVerification(true);
sso_log()->jwtVerification(false, 'Invalid signature');
```

### Token Refresh

```php
sso_log()->tokenRefresh($userId, $success, $error);

// Examples:
sso_log()->tokenRefresh(123, true);
sso_log()->tokenRefresh(123, false, 'Refresh token expired');
```

### Logout

```php
sso_log()->logout($userId, $global);

// Examples:
sso_log()->logout(123);           // Local logout
sso_log()->logout(123, true);     // Global logout
```

### Security Events

```php
sso_log()->securityEvent($event, $context);

// Examples:
sso_log()->securityEvent('blocked_redirect', [
    'requested_uri' => 'https://evil.com',
    'reason' => 'Not in allowed hosts',
]);

sso_log()->securityEvent('invalid_token', [
    'token_type' => 'access_token',
    'reason' => 'Signature mismatch',
]);
```

### API Errors

```php
sso_log()->apiError($endpoint, $statusCode, $error);

// Examples:
sso_log()->apiError('/api/token', 500, 'Internal server error');
sso_log()->apiError('/api/user', 401, 'Unauthorized');
```

### Permission Sync

```php
sso_log()->permissionSync($created, $updated, $deleted);

// Example:
sso_log()->permissionSync(5, 3, 1);
// Logs: "Permissions synced" with counts
```

## Sample Log Output

```log
[2024-01-15 10:30:45] sso.INFO: [SSO] Authentication successful {"email":"us***@example.com","success":true,"ip":"192.168.1.1"}
[2024-01-15 10:30:46] sso.DEBUG: [SSO] Code exchange successful {"success":true,"ip":"192.168.1.1"}
[2024-01-15 10:30:46] sso.DEBUG: [SSO] JWT verification successful {"success":true,"ip":"192.168.1.1"}
[2024-01-15 10:35:00] sso.WARNING: [SSO] Security event: blocked_redirect {"requested_uri":"https://evil.com","ip":"192.168.1.1"}
[2024-01-15 11:00:00] sso.INFO: [SSO] User logged out {"user_id":123,"global":false,"ip":"192.168.1.1"}
```

## Privacy: Email Masking

Emails are automatically masked in logs:

```
user@example.com  →  us***@example.com
john.doe@company.com  →  jo***@company.com
```

## Disable Logging

```env
SSO_LOGGING_ENABLED=false
```

Or programmatically:

```php
config(['sso-client.logging.enabled' => false]);
```

## Custom Log Channel

Configure a custom channel in `config/logging.php`:

```php
'channels' => [
    'sso' => [
        'driver' => 'daily',
        'path' => storage_path('logs/sso.log'),
        'level' => env('SSO_LOG_LEVEL', 'debug'),
        'days' => 30,  // Custom retention
    ],
    
    // Or use a different driver
    'sso-slack' => [
        'driver' => 'slack',
        'url' => env('SSO_SLACK_WEBHOOK_URL'),
        'level' => 'warning',  // Only warnings and above
    ],
],
```

Then set:

```env
SSO_LOG_CHANNEL=sso-slack
```

## Log Levels

| Level     | Use Case                          |
| --------- | --------------------------------- |
| `debug`   | Detailed debugging info           |
| `info`    | Normal operations                 |
| `warning` | Potential issues, security events |
| `error`   | Errors that need attention        |

## Monitoring & Alerting

Integrate with monitoring tools:

```php
// config/logging.php
'channels' => [
    'sso' => [
        'driver' => 'stack',
        'channels' => ['sso-daily', 'sso-slack'],
    ],
    'sso-daily' => [
        'driver' => 'daily',
        'path' => storage_path('logs/sso.log'),
        'level' => 'debug',
    ],
    'sso-slack' => [
        'driver' => 'slack',
        'url' => env('SLACK_SSO_WEBHOOK'),
        'level' => 'warning',
    ],
],
```

This sends warnings to Slack while keeping all logs in files.
