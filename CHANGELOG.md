# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.2] - 2026-07-12

### Fixed

- CI static analysis: PHPStan ignore patterns now tolerate version drift
  (newer PHPStan reports `ArrayAccess` with generics), and unmatched ignore
  patterns no longer fail the run. No runtime code changes.

## [1.1.1] - 2026-07-12

### Added

- Support for string (UUID) user primary keys. `VerificationComponent` no
  longer casts the identity id to `int`: `identityId()` now returns the raw
  integer or string key (falling back to `0` when the identity has no
  identifier), and user lookups for email verification pass the entity id
  through unchanged. Integer-keyed apps are unaffected. OTP storage already
  used string identity keys, so no storage changes were needed.

## [1.1.0] - 2026-07-12

### Security

- `TotpVerificator` now throttles failed verification attempts per identity
  (default: 5 attempts per 300 seconds, then lockout until the window expires).
  Prevents brute forcing of TOTP codes. Configurable via
  `drivers.totp.options.throttle` or `VERIFICATION_TOTP_THROTTLE_MAX` /
  `VERIFICATION_TOTP_THROTTLE_WINDOW`; set `max` to `0` to disable. Throttling
  is skipped when the identity has no id field, so existing integrations keep
  working unchanged.

### Fixed

- Default `emailVerified` field alias for the `emailOtp` driver in
  `config/verification.php` was `emailVerifiedAt` (camelCase); corrected to
  `email_verified_at` to match the driver default and all other column
  defaults. Apps overriding the column via `VERIFICATION_DB_COL_EMAIL_VERIFIED_AT`
  or app config are unaffected.

### Added

- `CacheOtpStorage` logs a warning when the configured cache profile is missing
  and the per-request `Array` engine fallback is used (OTP codes do not survive
  across requests in that case).

### Documentation

- Restructured to remove duplication: `docs/env.md` is now the single source of
  truth for environment variables (including a variable to config key map),
  database schemas and the example migration live only in `docs/installation.md`,
  and encryption key generation only in the crypto API docs.
- New central documentation index at `docs/index.md`; per-file footer navigation
  tables replaced with a single link.
- Documented TOTP brute force protection and the cache fallback behaviour.
- Corrected driver docblocks (`options.*` key paths, TOTP `period` naming).

## [1.0.0] - 2026-02-22

### Added

- Initial release: verification flow for CakePHP 5 with `emailVerify`,
  `emailOtp`, `smsOtp`, and `totp` steps, `VerificationComponent`,
  `VerificationHelper`, cache-backed OTP storage with rate limiting and
  lockout, pluggable SMS transports, and TOTP secret encryption at rest
  (Sodium / AES-256-GCM).
