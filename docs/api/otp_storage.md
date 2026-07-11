# OTP Storage & Rate Limiting

All OTP-based steps (`emailOtp`, `smsOtp`) store codes in the CakePHP Cache
and enforce rate-limiting through `CacheOtpStorage`. Codes are hashed before
storage — plain text codes are never written to cache or database.

## Configuration

All options live under `Verification.storage` in `config/verification.php`.

```php
'storage' => [
    // Max wrong attempts before lockout
    'maxAttempts'    => 5,

    // Lockout duration in seconds (15 min)
    'lockoutSeconds' => 900,

    // Minimum seconds between resend requests (0 = no cooldown)
    'resendCooldown' => 60,

    // Burst rate limit: max OTP issues per period (0 = unlimited)
    'burst'          => 0,
    'periodSeconds'  => 0,

    // CakePHP cache config name
    'cacheConfig'    => 'verification',

    // Optional: route specific steps to a different cache config
    'stepCacheConfig' => [
        // 'sms_otp' => 'verification_sms',
    ],
],
```

## Options reference

### `maxAttempts` (int, default `5`)

Failed verification attempts allowed before lockout. After lockout, even the
correct code is rejected until `lockoutSeconds` elapses.

### `lockoutSeconds` (int, default `900`)

Lockout duration in seconds. `900` = 15 minutes.

### `resendCooldown` (int, default `60`)

Minimum seconds between successive OTP issue calls for the same identity+step.
Set to `0` to disable. `handleVerify()` shows a flash error when the cooldown
is active.

### `burst` / `periodSeconds` (int, default `0`)

Sliding-window rate limit. A maximum of `burst` codes may be issued within any
`periodSeconds`-second window. Example — at most 3 codes per 10 minutes:

```php
'storage' => [
    'burst'          => 3,
    'periodSeconds'  => 600,
    'resendCooldown' => 0,
],
```

Set either to `0` to disable burst limiting.

### `cacheConfig` (string, default `'verification'`)

CakePHP cache configuration used for OTP storage. In production use a
persistent backend (Redis, Memcached):

```php
// config/app.php — Cache section
'verification' => [
    'className' => 'Cake\Cache\Engine\RedisEngine',
    'prefix'    => 'otp_',
    'duration'  => '+1 day',
    'server'    => '127.0.0.1',
    'port'      => 6379,
],
```

> **Warning:** if this cache config is not defined in your app, the plugin falls
> back to the per-request `Array` engine and logs a warning. Codes issued in one
> request are then gone in the next, so OTP verification will always fail and
> lockout/rate limiting will be ineffective. This fallback exists only for tests
> and driver construction; never rely on it at runtime.

### `stepCacheConfig` (array, default `[]`)

Route individual steps to a dedicated cache config with different TTL or backend:

```php
'stepCacheConfig' => [
    'sms_otp'   => 'verification_sms',
    'email_otp' => 'verification_email',
],
```

---

## Documentation

Full documentation index: [../index.md](../index.md)
