# Environment Variables Reference

All env variables are optional. The default value is used when the variable is
not set. Set them in your `.env` file or server environment.

This file is the single source of truth for environment variables. See the
[variable to config key map](#variable-to-config-key-map) at the bottom for how
each variable maps to a key in `config/verification.php`.

---

## General

| Variable | Default | Description |
|---|---|---|
| `VERIFICATION_ENABLED` | `true` | Master toggle. Set to `false` to disable all verification globally. |
| `VERIFICATION_REQUIRED_SETUP_STEPS` | *(see config)* | Comma-separated list of steps to run, e.g. `emailVerify,totp`. Overrides the `requiredSetupSteps` array in `config/verification.php`. |
| `VERIFICATION_OTP_LENGTH` | `6` | Number of digits for all OTP codes (email OTP and SMS OTP). |

---

## Routing

Routes default to `UsersController`. Override only if your controller or
prefix differs.

| Variable | Default | Description |
|---|---|---|
| `VERIFICATION_ROUTE_CONTROLLER` | `Users` | Controller for the `verify` action |
| `VERIFICATION_ROUTE_ACTION` | `verify` | Action name for OTP verification |
| `VERIFICATION_ROUTE_PREFIX` | *(none)* | Routing prefix for the verify route |
| `VERIFICATION_PENDING_CONTROLLER` | `Users` | Controller for the `pending` (check inbox) page |
| `VERIFICATION_PENDING_ACTION` | `pending` | Action name for the pending page |
| `VERIFICATION_PENDING_PREFIX` | *(none)* | Routing prefix for the pending route |
| `VERIFICATION_VERIFIED_CONTROLLER` | `Users` | Controller to redirect to after all steps are complete |
| `VERIFICATION_VERIFIED_ACTION` | `index` | Action to redirect to after all steps are complete |
| `VERIFICATION_VERIFIED_PREFIX` | *(none)* | Routing prefix for the post-verification redirect |
| `VERIFICATION_ENROLL_CONTROLLER` | `Users` | Controller for TOTP enrollment |
| `VERIFICATION_ENROLL_ACTION` | `enroll` | Action name for TOTP enrollment |
| `VERIFICATION_ENROLL_PREFIX` | *(none)* | Routing prefix for the enroll route |
| `VERIFICATION_ENROLL_PHONE_CONTROLLER` | `Users` | Controller for phone number enrollment |
| `VERIFICATION_ENROLL_PHONE_ACTION` | `enrollPhone` | Action name for phone enrollment |
| `VERIFICATION_ENROLL_PHONE_PREFIX` | *(none)* | Routing prefix for the enroll-phone route |
| `VERIFICATION_CHOOSE_CONTROLLER` | `Users` | Controller for OTP method selection |
| `VERIFICATION_CHOOSE_ACTION` | `chooseVerification` | Action name for OTP method selection |
| `VERIFICATION_CHOOSE_PREFIX` | *(none)* | Routing prefix for the choose-verification route |

---

## Storage & Rate Limiting

| Variable | Default | Description |
|---|---|---|
| `VERIFICATION_CACHE_CONFIG` | `verification` | CakePHP cache configuration name used to store OTP codes and rate-limit state |
| `VERIFICATION_MAX_ATTEMPTS` | `5` | Maximum failed code attempts before lockout |
| `VERIFICATION_LOCKOUT` | `900` | Lockout duration in seconds after too many failed attempts (900 = 15 min) |
| `VERIFICATION_RESEND_COOLDOWN` | `60` | Minimum seconds between OTP resend requests. Set to `0` to disable |
| `VERIFICATION_OTP_BURST` | `0` | Maximum OTP codes that can be issued within the burst period. `0` = unlimited |
| `VERIFICATION_OTP_PERIOD` | `0` | Burst window in seconds. `0` = disabled |

---

## Encryption (TOTP secret at rest)

| Variable | Default | Description |
|---|---|---|
| `VERIFICATION_CRYPTO_DRIVER` | `aes-gcm` | Encryption driver: `aes-gcm` or `sodium` |
| `VERIFICATION_CRYPTO_KEY` | *(empty)* | Base64-encoded 32-byte encryption key. **Required** when TOTP is enabled. Key generation: see [api/sodium_crypto.md](api/sodium_crypto.md) / [api/aes_gcm_crypto.md](api/aes_gcm_crypto.md) |

> If `VERIFICATION_CRYPTO_KEY` is empty, the TOTP secret is stored unencrypted.
> Always set this in production.

---

## Identity

| Variable | Default | Description |
|---|---|---|
| `VERIFICATION_ID_FIELD` | `id` | Identity field used as the key for OTP cache entries |

---

## Database column mapping

Override only when your `users` table uses different column names than the defaults.

| Variable | Default column | Description |
|---|---|---|
| `VERIFICATION_DB_COL_EMAIL` | `email` | Email address column |
| `VERIFICATION_DB_COL_PHONE` | `phone` | Phone number column |
| `VERIFICATION_DB_COL_TOTP_SECRET` | `totp_secret` | TOTP secret column |
| `VERIFICATION_DB_COL_EMAIL_VERIFIED_AT` | `email_verified_at` | Timestamp set when email is confirmed |
| `VERIFICATION_DB_COL_PHONE_VERIFIED_AT` | `phone_verified_at` | Timestamp set after successful SMS OTP |
| `VERIFICATION_DB_COL_TOTP_VERIFIED_AT` | `totp_verified_at` | Timestamp set after first successful TOTP |
| `VERIFICATION_DB_COL_PHONE_VERIFIED` | `phone_verified` | Boolean flag for phone verification (optional) |
| `VERIFICATION_DB_COL_PREFS` | `verification_preferences` | JSON column for storing the user's chosen OTP driver |
| `VERIFICATION_EMAIL_FIELD` | `email` | Email field read by `emailVerify` and `emailOtp` drivers |
| `VERIFICATION_PHONE_FIELD` | `phone` | Phone field read by `smsOtp` driver |

---

## Email Verify driver

| Variable | Default | Description |
|---|---|---|
| `VERIFICATION_EMAIL_VERIFY_ENABLED` | `true` | Enable or disable the `emailVerify` step |

---

## Email OTP driver

| Variable | Default | Description |
|---|---|---|
| `VERIFICATION_EMAIL_OTP_ENABLED` | `true` | Enable or disable the `emailOtp` step |
| `VERIFICATION_EMAIL_TTL` | `600` | OTP code validity in seconds (600 = 10 min) |

---

## SMS OTP driver

| Variable | Default | Description |
|---|---|---|
| `VERIFICATION_SMS_OTP_ENABLED` | `true` | Enable or disable the `smsOtp` step |
| `VERIFICATION_SMS_TTL` | `300` | OTP code validity in seconds (300 = 5 min) |
| `VERIFICATION_SMS_MESSAGE` | `Your verification code is {code}. It expires in {ttl} minutes.` | SMS message template. `{code}` and `{ttl}` are replaced at send time |
| `VERIFICATION_SMS_SENDER` | `YourApp` | Sender ID or phone number shown to the recipient |
| `VERIFICATION_SMS_E164` | `false` | Normalize phone numbers to E.164 format before sending |
| `VERIFICATION_SMS_COUNTRY_CODE` | *(empty)* | Default country code used for E.164 normalization (e.g. `BA`, `DE`) |
| `VERIFICATION_SMS_TRANSPORT` | `default` | Name of the SMS transport to use (must be defined under `sms.transports`) |

---

## TOTP driver

| Variable | Default | Description |
|---|---|---|
| `VERIFICATION_TOTP_ENABLED` | `true` | Enable or disable the `totp` step |
| `VERIFICATION_TOTP_DIGITS` | `6` | Number of digits in the TOTP code (6 or 8) |
| `VERIFICATION_TOTP_PERIOD` | `30` | TOTP window in seconds (standard: 30) |
| `VERIFICATION_TOTP_ALGO` | `sha1` | HMAC algorithm: `sha1`, `sha256`, or `sha512` |
| `VERIFICATION_TOTP_DRIFT` | `1` | Allowed clock drift in windows (±1 = ±30 s with default period) |
| `VERIFICATION_TOTP_THROTTLE_MAX` | `5` | Max failed TOTP attempts per window before lockout (`0` disables throttling) |
| `VERIFICATION_TOTP_THROTTLE_WINDOW` | `300` | Throttle window in seconds |

---

## Example `.env`

```dotenv
# General
VERIFICATION_ENABLED=true
VERIFICATION_REQUIRED_SETUP_STEPS=emailVerify,totp
VERIFICATION_OTP_LENGTH=6

# Encryption (required for TOTP in production)
VERIFICATION_CRYPTO_DRIVER=aes-gcm
VERIFICATION_CRYPTO_KEY=<base64-encoded-32-byte-key>

# Storage
VERIFICATION_CACHE_CONFIG=verification
VERIFICATION_MAX_ATTEMPTS=5
VERIFICATION_LOCKOUT=900
VERIFICATION_RESEND_COOLDOWN=60

# SMS (if smsOtp is used)
VERIFICATION_SMS_OTP_ENABLED=true
VERIFICATION_SMS_TTL=300
VERIFICATION_SMS_TRANSPORT=twilio
VERIFICATION_SMS_SENDER=MyApp
VERIFICATION_SMS_E164=true
VERIFICATION_SMS_COUNTRY_CODE=BA
```

---

## Variable to config key map

| Variable | Config key |
|---|---|
| `VERIFICATION_ENABLED` | `enabled` |
| `VERIFICATION_REQUIRED_SETUP_STEPS` | `requiredSetupSteps` |
| `VERIFICATION_OTP_LENGTH` | `otp.length` |
| `VERIFICATION_CACHE_CONFIG` | `storage.cacheConfig` |
| `VERIFICATION_MAX_ATTEMPTS` | `storage.maxAttempts` |
| `VERIFICATION_LOCKOUT` | `storage.lockoutSeconds` |
| `VERIFICATION_RESEND_COOLDOWN` | `storage.resendCooldown` |
| `VERIFICATION_OTP_BURST` | `storage.burst` |
| `VERIFICATION_OTP_PERIOD` | `storage.periodSeconds` |
| `VERIFICATION_CRYPTO_DRIVER` | `crypto.driver` |
| `VERIFICATION_CRYPTO_KEY` | `crypto.key` |
| `VERIFICATION_EMAIL_VERIFY_ENABLED` | `drivers.emailVerify.enabled` |
| `VERIFICATION_EMAIL_OTP_ENABLED` | `drivers.emailOtp.enabled` |
| `VERIFICATION_SMS_OTP_ENABLED` | `drivers.smsOtp.enabled` |
| `VERIFICATION_TOTP_ENABLED` | `drivers.totp.enabled` |
| `VERIFICATION_EMAIL_TTL` | `drivers.emailOtp.options.ttl` |
| `VERIFICATION_SMS_TTL` | `drivers.smsOtp.options.ttl` |
| `VERIFICATION_SMS_MESSAGE` | `drivers.smsOtp.options.messageTemplate` |
| `VERIFICATION_SMS_SENDER` | `drivers.smsOtp.options.senderId` |
| `VERIFICATION_SMS_E164` | `drivers.smsOtp.options.normalizeE164` |
| `VERIFICATION_SMS_COUNTRY_CODE` | `drivers.smsOtp.options.defaultCountryCode` |
| `VERIFICATION_TOTP_DIGITS` | `drivers.totp.options.digits` |
| `VERIFICATION_TOTP_PERIOD` | `drivers.totp.options.period` |
| `VERIFICATION_TOTP_ALGO` | `drivers.totp.options.algorithm` |
| `VERIFICATION_TOTP_DRIFT` | `drivers.totp.options.drift` |
| `VERIFICATION_TOTP_THROTTLE_MAX` | `drivers.totp.options.throttle.max` |
| `VERIFICATION_TOTP_THROTTLE_WINDOW` | `drivers.totp.options.throttle.window` |
| `VERIFICATION_SMS_TRANSPORT` | `sms.defaultTransport` |
| `VERIFICATION_ID_FIELD` | `identity.fields.id` |
| `VERIFICATION_DB_COL_*`, `VERIFICATION_EMAIL_FIELD`, `VERIFICATION_PHONE_FIELD` | `db.users.columns.*` and driver `fields` |
| `VERIFICATION_ROUTE_*`, `VERIFICATION_PENDING_*`, `VERIFICATION_VERIFIED_*`, `VERIFICATION_ENROLL_*`, `VERIFICATION_CHOOSE_*` | `routing.*` |

---

## Documentation

Full documentation index: [index.md](index.md)
