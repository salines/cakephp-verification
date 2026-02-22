# Configuration

The plugin is configured through `config/verification.php`, published by
`bin/cake verification:install`. All values can be set statically, via
`env()`, or overridden at runtime through `Configure::write()`.

---

## Configuration methods

### Static (hardcoded)

```php
// config/verification.php
'requiredSetupSteps' => ['emailVerify', 'emailOtp'],
```

### Environment variables

Every key in the default config reads from an `env()` call with a fallback.
Set the variable in your `.env` or server environment:

```dotenv
VERIFICATION_ENABLED=true
VERIFICATION_REQUIRED_SETUP_STEPS=emailVerify,emailOtp
VERIFICATION_MAX_ATTEMPTS=5
VERIFICATION_LOCKOUT=900
VERIFICATION_RESEND_COOLDOWN=60
VERIFICATION_CRYPTO_KEY=<base64-encoded-key>
```

`VERIFICATION_REQUIRED_SETUP_STEPS` accepts a comma-separated list; the
plugin bootstrap normalises it to an array automatically.

### Runtime override (app_local.php or Configure::write)

```php
// config/app_local.php
Configure::write('Verification.requiredSetupSteps', ['emailVerify', 'totp']);
```

---

## Top-level keys

| Key | Type | Default | Description |
|---|---|---|---|
| `enabled` | bool | `true` | Master toggle — disables all verification when `false` |
| `requiredSetupSteps` | array | `['emailVerify', 'totp', 'smsOtp']` | Ordered steps every user must complete after registration |
| `otp.length` | int | `6` | Number of digits for email and SMS OTP codes |

---

## `requiredSetupSteps`

Available step keys: `emailVerify`, `emailOtp`, `smsOtp`, `totp`.

- `emailVerify` always runs first and blocks all other steps until the address is confirmed.
- If more than one OTP step is listed, the user is redirected to `chooseVerification` to pick one method.

```php
// Email verification + authenticator app only
'requiredSetupSteps' => ['emailVerify', 'totp'],

// Email verification + user picks between email OTP, SMS OTP, or TOTP
'requiredSetupSteps' => ['emailVerify', 'emailOtp', 'smsOtp', 'totp'],
```

---

## `routing`

Maps logical plugin destinations to your app's controller actions. All routes
default to `Users` controller. Override per-route or via env:

```php
'routing' => [
    'nextRoute'               => ['plugin' => false, 'controller' => 'Users', 'action' => 'verify'],
    'pendingRoute'            => ['plugin' => false, 'controller' => 'Users', 'action' => 'pending'],
    'enrollRoute'             => ['plugin' => false, 'controller' => 'Users', 'action' => 'enroll'],
    'enrollPhoneRoute'        => ['plugin' => false, 'controller' => 'Users', 'action' => 'enrollPhone'],
    'chooseVerificationRoute' => ['plugin' => false, 'controller' => 'Users', 'action' => 'chooseVerification'],
    'onVerifiedRoute'         => ['plugin' => false, 'controller' => 'Users', 'action' => 'index'],
],
```

| Key | Purpose |
|---|---|
| `nextRoute` | OTP verify form (email code, SMS code, TOTP code) |
| `pendingRoute` | "Check your inbox" page shown while email is unconfirmed |
| `enrollRoute` | TOTP QR enrollment page |
| `enrollPhoneRoute` | Phone number entry for SMS OTP |
| `chooseVerificationRoute` | OTP method selection (multiple drivers configured) |
| `onVerifiedRoute` | Destination after all steps are complete |

Env variables: `VERIFICATION_ROUTE_CONTROLLER`, `VERIFICATION_ROUTE_ACTION`,
`VERIFICATION_PENDING_CONTROLLER`, `VERIFICATION_PENDING_ACTION`, etc.

---

## `storage`

Controls OTP code persistence and rate limiting (uses CakePHP Cache).

```php
'storage' => [
    'cacheConfig'    => 'verification',  // cache profile name
    'maxAttempts'    => 5,               // failed attempts before lockout
    'lockoutSeconds' => 900,             // lockout duration (15 min)
    'resendCooldown' => 60,              // seconds before resend is allowed
    'burst'          => 0,               // max codes issued in periodSeconds (0 = off)
    'periodSeconds'  => 0,              // burst window (0 = off)
],
```

Env variables: `VERIFICATION_CACHE_CONFIG`, `VERIFICATION_MAX_ATTEMPTS`,
`VERIFICATION_LOCKOUT`, `VERIFICATION_RESEND_COOLDOWN`, `VERIFICATION_OTP_BURST`,
`VERIFICATION_OTP_PERIOD`.

See [otp_storage.md](api/otp_storage.md) for details on rate limiting and lockout.

---

## `crypto`

Encrypts TOTP secrets at rest. Recommended for production.

```php
'crypto' => [
    'driver' => 'aes-gcm',   // or 'sodium'
    'key'    => base64_decode(env('VERIFICATION_CRYPTO_KEY', '')),
],
```

Generate a key:

```bash
php -r "echo base64_encode(random_bytes(32)) . PHP_EOL;"
```

See [aes_gcm_crypto.md](api/aes_gcm_crypto.md) and [sodium_crypto.md](api/sodium_crypto.md).

---

## `drivers`

Per-driver options. Each driver can be individually enabled/disabled and
its options tuned:

```php
'drivers' => [
    'emailVerify' => [
        'enabled' => true,
        'options' => [
            'delivery' => null,  // custom delivery closure; null = default mailer
        ],
    ],

    'emailOtp' => [
        'enabled' => true,
        'options' => [
            'ttl' => 600,  // code valid for 10 minutes
        ],
    ],

    'smsOtp' => [
        'enabled' => true,
        'options' => [
            'ttl'                => 300,
            'messageTemplate'    => 'Your code is {code}. Valid for {ttl} minutes.',
            'senderId'           => 'MyApp',
            'normalizeE164'      => false,
            'defaultCountryCode' => '',   // e.g. '+385'
        ],
    ],

    'totp' => [
        'enabled' => true,
        'options' => [
            'digits'    => 6,
            'period'    => 30,
            'algorithm' => 'sha1',
            'drift'     => 1,   // allowed clock drift in periods
        ],
    ],
],
```

---

## `db` — column name remapping

If your database columns differ from the defaults, remap them here.
The left side is the plugin's internal alias; the right side is your column name.

```php
'db' => [
    'users' => [
        'columns' => [
            'email'                   => 'email',
            'phone'                   => 'phone',
            'totpSecret'              => 'totp_secret',
            'emailVerifiedAt'         => 'email_verified_at',
            'phoneVerifiedAt'         => 'phone_verified_at',
            'totpVerifiedAt'          => 'totp_verified_at',
            'phoneVerifiedFlag'       => 'phone_verified',
            'verificationPreferences' => 'verification_preferences',
        ],
    ],
],
```

Per-driver field aliases (override column name per driver):

```php
'drivers' => [
    'emailVerify' => ['fields' => ['email' => 'user_email', 'emailVerified' => 'email_confirmed_at']],
    'smsOtp'      => ['fields' => ['phone' => 'mobile', 'phoneVerifiedAt' => 'mobile_verified_at']],
    'totp'        => ['fields' => ['totpSecret' => 'mfa_secret', 'totpVerified' => 'mfa_verified_at']],
],
```

---

## `identity`

Maps the identity field used as the OTP storage key (default: `id`).

```php
'identity' => [
    'fields' => ['id' => 'uuid'],
],
```

---

## `sms`

SMS transport configuration. The plugin ships `DummyTransport` for development.
Add a real transport for production:

```php
'sms' => [
    'defaultTransport' => 'twilio',
    'transports' => [
        'twilio' => ['className' => \App\Sms\TwilioTransport::class],
    ],
],
```

See [sms_verification.md](sms_verification.md).

---

## Environment variable reference

| Variable | Config key | Default |
|---|---|---|
| `VERIFICATION_ENABLED` | `enabled` | `true` |
| `VERIFICATION_REQUIRED_SETUP_STEPS` | `requiredSetupSteps` | `emailVerify,totp,smsOtp` |
| `VERIFICATION_OTP_LENGTH` | `otp.length` | `6` |
| `VERIFICATION_CACHE_CONFIG` | `storage.cacheConfig` | `verification` |
| `VERIFICATION_MAX_ATTEMPTS` | `storage.maxAttempts` | `5` |
| `VERIFICATION_LOCKOUT` | `storage.lockoutSeconds` | `900` |
| `VERIFICATION_RESEND_COOLDOWN` | `storage.resendCooldown` | `60` |
| `VERIFICATION_OTP_BURST` | `storage.burst` | `0` |
| `VERIFICATION_OTP_PERIOD` | `storage.periodSeconds` | `0` |
| `VERIFICATION_CRYPTO_DRIVER` | `crypto.driver` | `aes-gcm` |
| `VERIFICATION_CRYPTO_KEY` | `crypto.key` | _(empty)_ |
| `VERIFICATION_EMAIL_VERIFY_ENABLED` | `drivers.emailVerify.enabled` | `true` |
| `VERIFICATION_EMAIL_OTP_ENABLED` | `drivers.emailOtp.enabled` | `true` |
| `VERIFICATION_SMS_OTP_ENABLED` | `drivers.smsOtp.enabled` | `true` |
| `VERIFICATION_TOTP_ENABLED` | `drivers.totp.enabled` | `true` |
| `VERIFICATION_EMAIL_TTL` | `drivers.emailOtp.options.ttl` | `600` |
| `VERIFICATION_SMS_TTL` | `drivers.smsOtp.options.ttl` | `300` |
| `VERIFICATION_SMS_SENDER` | `drivers.smsOtp.options.senderId` | `YourApp` |
| `VERIFICATION_SMS_E164` | `drivers.smsOtp.options.normalizeE164` | `false` |
| `VERIFICATION_SMS_COUNTRY_CODE` | `drivers.smsOtp.options.defaultCountryCode` | _(empty)_ |
| `VERIFICATION_TOTP_DIGITS` | `drivers.totp.options.digits` | `6` |
| `VERIFICATION_TOTP_PERIOD` | `drivers.totp.options.period` | `30` |
| `VERIFICATION_TOTP_ALGO` | `drivers.totp.options.algorithm` | `sha1` |
| `VERIFICATION_TOTP_DRIFT` | `drivers.totp.options.drift` | `1` |
| `VERIFICATION_SMS_TRANSPORT` | `sms.defaultTransport` | `default` |
| `VERIFICATION_ID_FIELD` | `identity.fields.id` | `id` |

---

## Documentation

| Topic | File |
|---|---|
| README | [../README.md](../README.md) |
| Verification flows (setup, login, OTP choice) | [verification_flow.md](verification_flow.md) |
| Installation | [installation.md](installation.md) |
| Configuration reference | [configuration.md](configuration.md) |
| Environment variables | [env.md](env.md) |
| UsersController actions | [users_controller.md](users_controller.md) |
| VerificationComponent | [verification_component.md](verification_component.md) |
| VerificationHelper | [verification_helper.md](verification_helper.md) |
| Email verification & Email OTP | [email_verification.md](email_verification.md) |
| SMS OTP | [sms_verification.md](sms_verification.md) |
| TOTP | [totp_verification.md](totp_verification.md) |
| Enable / disable individual steps | [verificator_enable_disable.md](verificator_enable_disable.md) |
| API reference | [api/index.md](api/index.md) |
