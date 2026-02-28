<?php

declare(strict_types=1);

use Omnify\Core\Support\RedirectUrlValidator;

beforeEach(function () {
    // Set app URL for testing
    config(['app.url' => 'https://myapp.example.com']);
    config(['app.frontend_url' => 'https://frontend.example.com']);
    config(['omnify-auth.security.allowed_redirect_hosts' => ['*.trusted.com', 'allowed.org']]);
});

// =============================================================================
// Valid URLs - Should Pass
// =============================================================================

test('allows relative URLs starting with /', function () {
    $validator = new RedirectUrlValidator;

    expect($validator->validate('/dashboard'))->toBe('/dashboard');
    expect($validator->validate('/users/profile'))->toBe('/users/profile');
    expect($validator->validate('/'))->toBe('/');
    expect($validator->validate('/path?query=value'))->toBe('/path?query=value');
    expect($validator->validate('/path#hash'))->toBe('/path#hash');
});

test('allows app URL host', function () {
    $validator = new RedirectUrlValidator;

    expect($validator->validate('https://myapp.example.com/dashboard'))
        ->toBe('https://myapp.example.com/dashboard');
    expect($validator->validate('http://myapp.example.com/'))
        ->toBe('http://myapp.example.com/');
});

test('allows frontend URL host', function () {
    $validator = new RedirectUrlValidator;

    expect($validator->validate('https://frontend.example.com/callback'))
        ->toBe('https://frontend.example.com/callback');
});

test('allows configured trusted hosts', function () {
    $validator = new RedirectUrlValidator;

    expect($validator->validate('https://allowed.org/path'))
        ->toBe('https://allowed.org/path');
});

test('allows wildcard subdomain matches', function () {
    $validator = new RedirectUrlValidator;

    expect($validator->validate('https://sub.trusted.com/path'))
        ->toBe('https://sub.trusted.com/path');
    expect($validator->validate('https://deep.sub.trusted.com/'))
        ->toBe('https://deep.sub.trusted.com/');
    expect($validator->validate('https://trusted.com/'))
        ->toBe('https://trusted.com/');
});

test('allows manually added hosts', function () {
    $validator = new RedirectUrlValidator;
    $validator->addAllowedHost('custom.domain.com');

    expect($validator->validate('https://custom.domain.com/'))
        ->toBe('https://custom.domain.com/');
});

// =============================================================================
// Open Redirect Attacks - Should Reject
// =============================================================================

test('rejects external domains not in allowed list', function () {
    $validator = new RedirectUrlValidator;

    expect($validator->validate('https://evil.com/'))->toBe('/');
    expect($validator->validate('https://attacker.org/phishing'))->toBe('/');
    expect($validator->validate('http://malicious-site.net/'))->toBe('/');
});

test('rejects protocol-relative URLs (//evil.com)', function () {
    $validator = new RedirectUrlValidator;

    expect($validator->validate('//evil.com'))->toBe('/');
    expect($validator->validate('//attacker.org/path'))->toBe('/');
    expect($validator->validate('///evil.com'))->toBe('/');
});

test('rejects backslash URL bypass attempts', function () {
    $validator = new RedirectUrlValidator;

    expect($validator->validate('/\\evil.com'))->toBe('/');
    expect($validator->validate('/\\/evil.com'))->toBe('/');
    expect($validator->validate('\\\\evil.com'))->toBe('/');
});

test('rejects URL-encoded bypass attempts', function () {
    $validator = new RedirectUrlValidator;

    // %2f = /
    expect($validator->validate('/%2f/evil.com'))->toBe('/');
    expect($validator->validate('/%2F%2Fevil.com'))->toBe('/');

    // Note: Double encoding (/%252f%252f) doesn't decode to // in single pass
    // so it's technically a valid relative URL. This is expected behavior.
    // Application should handle double decoding at web server level if needed.
});

test('rejects dangerous protocols', function () {
    $validator = new RedirectUrlValidator;

    expect($validator->validate('javascript:alert(1)'))->toBe('/');
    expect($validator->validate('JAVASCRIPT:alert(1)'))->toBe('/');
    expect($validator->validate('vbscript:msgbox(1)'))->toBe('/');
    expect($validator->validate('data:text/html,<script>alert(1)</script>'))->toBe('/');
    expect($validator->validate('file:///etc/passwd'))->toBe('/');
    expect($validator->validate('ftp://ftp.evil.com/'))->toBe('/');
});

test('rejects URLs with javascript protocol with spaces/tabs', function () {
    $validator = new RedirectUrlValidator;

    expect($validator->validate(' javascript:alert(1)'))->toBe('/');
    expect($validator->validate("\tjavascript:alert(1)"))->toBe('/');
    expect($validator->validate("\njavascript:alert(1)"))->toBe('/');
});

test('rejects similar domain attacks', function () {
    $validator = new RedirectUrlValidator;

    // Attacker registers similar domains
    expect($validator->validate('https://myapp.example.com.evil.com/'))->toBe('/');
    expect($validator->validate('https://evil.com/myapp.example.com/'))->toBe('/');
});

test('rejects URLs without proper host parsing', function () {
    $validator = new RedirectUrlValidator;

    expect($validator->validate('@evil.com'))->toBe('/');
    expect($validator->validate('user@evil.com'))->toBe('/');
});

// =============================================================================
// Edge Cases
// =============================================================================

test('handles null input', function () {
    $validator = new RedirectUrlValidator;

    expect($validator->validate(null))->toBe('/');
    expect($validator->validate(null, '/fallback'))->toBe('/fallback');
});

test('handles empty string input', function () {
    $validator = new RedirectUrlValidator;

    expect($validator->validate(''))->toBe('/');
    expect($validator->validate('', '/fallback'))->toBe('/fallback');
});

test('uses custom default URL', function () {
    $validator = new RedirectUrlValidator;

    expect($validator->validate('https://evil.com/', '/dashboard'))->toBe('/dashboard');
    expect($validator->validate(null, '/home'))->toBe('/home');
});

test('can disable relative URLs', function () {
    $validator = new RedirectUrlValidator;
    $validator->setAllowRelative(false);

    expect($validator->validate('/dashboard'))->toBe('/');
    expect($validator->validate('https://myapp.example.com/dashboard'))
        ->toBe('https://myapp.example.com/dashboard');
});

test('preserves query strings and fragments', function () {
    $validator = new RedirectUrlValidator;

    expect($validator->validate('/path?foo=bar&baz=qux'))
        ->toBe('/path?foo=bar&baz=qux');
    expect($validator->validate('/path#section'))
        ->toBe('/path#section');
    expect($validator->validate('/path?foo=bar#section'))
        ->toBe('/path?foo=bar#section');
});

test('rejects overly long URLs', function () {
    $validator = new RedirectUrlValidator;
    $maxLength = config('omnify-auth.security.max_redirect_url_length', 2048);

    $longPath = '/'.str_repeat('a', $maxLength + 100);

    expect($validator->validate($longPath))->toBe('/');
});

// =============================================================================
// Configuration Tests
// =============================================================================

test('getAllowedHosts returns configured hosts', function () {
    $validator = new RedirectUrlValidator;
    $hosts = $validator->getAllowedHosts();

    expect($hosts)->toContain('myapp.example.com');
    expect($hosts)->toContain('frontend.example.com');
    expect($hosts)->toContain('*.trusted.com');
    expect($hosts)->toContain('allowed.org');
});

test('addAllowedHost adds to list without duplicates', function () {
    $validator = new RedirectUrlValidator;
    $validator->addAllowedHost('new.domain.com');
    $validator->addAllowedHost('new.domain.com'); // Duplicate

    $hosts = $validator->getAllowedHosts();
    $count = array_count_values($hosts)['new.domain.com'] ?? 0;

    expect($count)->toBe(1);
});

// =============================================================================
// Real-world Attack Scenarios
// =============================================================================

test('prevents OAuth redirect manipulation attack', function () {
    $validator = new RedirectUrlValidator;

    // Attacker tries to steal OAuth code by manipulating redirect
    expect($validator->validate('https://attacker.com/steal?code='))
        ->toBe('/');
});

test('prevents login phishing redirect', function () {
    $validator = new RedirectUrlValidator;

    // Attacker creates a phishing page that looks like login
    expect($validator->validate('https://myapp-example.com/login'))
        ->toBe('/');
    expect($validator->validate('https://myapp.example.com.attacker.com/login'))
        ->toBe('/');
});

test('prevents credential harvesting after logout', function () {
    $validator = new RedirectUrlValidator;

    // After logout, attacker tries to redirect to credential harvesting page
    expect($validator->validate('https://login-myapp.example.com/'))
        ->toBe('/');
});
