# Documentation Index

Central index for all cakephp-verification plugin documentation.

## Getting started

| Topic | File |
|---|---|
| README (overview, features, quick start) | [../README.md](../README.md) |
| Installation (plugin setup, database columns, migrations) | [installation.md](installation.md) |
| Configuration reference | [configuration.md](configuration.md) |
| Environment variables (single source of truth) | [env.md](env.md) |

## Guides

| Topic | File |
|---|---|
| Verification flows (setup, login, OTP choice) | [verification_flow.md](verification_flow.md) |
| Email verification & Email OTP | [email_verification.md](email_verification.md) |
| SMS OTP | [sms_verification.md](sms_verification.md) |
| TOTP (authenticator app) | [totp_verification.md](totp_verification.md) |
| Enable / disable individual steps | [verificator_enable_disable.md](verificator_enable_disable.md) |
| Translations (shipped locales, overriding) | [translations.md](translations.md) |

## Integration

| Topic | File |
|---|---|
| UsersController actions | [users_controller.md](users_controller.md) |
| VerificationComponent | [verification_component.md](verification_component.md) |
| VerificationHelper | [verification_helper.md](verification_helper.md) |

## API reference (internals)

| Topic | File |
|---|---|
| API index | [api/index.md](api/index.md) |
| OTP storage — rate limiting, lockout, cache config | [api/otp_storage.md](api/otp_storage.md) |
| Sodium encryption driver | [api/sodium_crypto.md](api/sodium_crypto.md) |
| AES-256-GCM encryption driver | [api/aes_gcm_crypto.md](api/aes_gcm_crypto.md) |
| SMS transport — custom transport implementation | [api/sms_transport.md](api/sms_transport.md) |
| Custom verification step (verificator interface) | [api/verificator_interface.md](api/verificator_interface.md) |
