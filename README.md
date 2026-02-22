# CakePHP Verification

A CakePHP 5.x plugin for step-up verification and MFA: email verification links,
Email OTP, SMS OTP, and TOTP (authenticator apps).

## Features

- `emailVerify` — email verification link
- `emailOtp` — email one-time code
- `smsOtp` — SMS one-time code
- `totp` — TOTP / authenticator apps (RFC 6238, no external library needed for code generation)
- Pluggable SMS transports (dummy driver included)
- Optional at-rest encryption of the TOTP secret (Sodium or AES-256-GCM)
- Rate-limiting, lockout, and resend cooldown for OTP codes
- `VerificationComponent` handles all controller logic (auto-start, verify, mark verified, redirect)

## Requirements

- PHP 8.2+
- CakePHP 5.3+
- cakephp/authentication ^4.0
- bacon/bacon-qr-code (optional, for SVG QR rendering in TOTP enrollment)

## How It Works

The plugin adds two verification gates to your application:

**1. Setup flow** — runs once, immediately after registration. The user must
complete every step listed in `requiredSetupSteps` before they can access the
app. Steps are executed in order:

1. `emailVerify` — user receives a confirmation link; clicks it to confirm
   their address. Until confirmed, all other steps are blocked.
2. OTP enrollment — if `emailOtp`, `smsOtp`, or `totp` are listed, the user
   enrolls in the chosen method (enters a code, scans a QR, etc.).

> If more than one OTP driver is listed in `requiredSetupSteps` the user is
> first directed to a **choose-verification** screen where they pick which
> method they want to use. See [docs/verification_flow.md](docs/verification_flow.md).

**2. Login flow** — runs on every subsequent login, after the user
authenticates with their password. The plugin checks which OTP method the user
enrolled in and redirects them to enter a code before they reach the app.

The plugin relies on the CakePHP Authentication identity object to identify the
current user. It does not manage its own session. Persistent verification
results (`email_verified_at`, `totp_secret`, `verification_preferences`, …)
are written to your `users` table. OTP codes and rate-limiting state are stored
temporarily in the CakePHP Cache (auto-deleted after use or expiry).

## Installation

```bash
composer require salines/cakephp-verification
bin/cake plugin load Verification
bin/cake verification:install
```

Add the required columns to your `users` table (see migration example in the full guide),
then implement the `UsersController` actions.

See [docs/installation.md](docs/installation.md) for the full installation guide.

## Configuration

Open `config/verification.php` and set the steps your app needs:

```php
'Verification' => [
    'enabled' => true,

    // Available steps: 'emailVerify', 'emailOtp', 'smsOtp', 'totp'
    // emailVerify always runs first (blocks other steps until confirmed).
    // If more than one OTP step is listed, the user is asked to choose one.
    'requiredSetupSteps' => ['emailVerify', 'emailOtp'],

    'routing' => [
        'nextRoute'               => ['plugin' => false, 'controller' => 'Users', 'action' => 'verify'],
        'pendingRoute'            => ['plugin' => false, 'controller' => 'Users', 'action' => 'pending'],
        'enrollRoute'             => ['plugin' => false, 'controller' => 'Users', 'action' => 'enroll'],
        'enrollPhoneRoute'        => ['plugin' => false, 'controller' => 'Users', 'action' => 'enrollPhone'],
        'chooseVerificationRoute' => ['plugin' => false, 'controller' => 'Users', 'action' => 'chooseVerification'],
        'onVerifiedRoute'         => ['plugin' => false, 'controller' => 'Users', 'action' => 'index'],
    ],

    'storage' => [
        'maxAttempts'    => 5,
        'lockoutSeconds' => 900,
        'resendCooldown' => 60,
    ],
],
```

See [docs/configuration.md](docs/configuration.md) for the full configuration reference.

## Setup

### Component

Load `VerificationComponent` alongside `Authentication` in `AppController`:

```php
// src/Controller/AppController.php
public function initialize(): void
{
    parent::initialize();
    $this->loadComponent('Flash');
    $this->loadComponent('Authentication.Authentication');
    $this->loadComponent('CakeVerification.Verification');
}
```

See [docs/verification_component.md](docs/verification_component.md) for the full component API.

### Helper

`VerificationHelper` is auto-loaded by the plugin. It provides `qrCode()` for
TOTP enrollment views and `lastSmsCode()` for debug-mode SMS inspection.

See [docs/verification_helper.md](docs/verification_helper.md) for details.

## Available Steps

| Key           | Type        | Description                                 |
|---------------|-------------|---------------------------------------------|
| `emailVerify` | Setup only  | Send link by email; user clicks to confirm  |
| `emailOtp`    | Setup/Login | Send numeric code by email                  |
| `smsOtp`      | Setup/Login | Send numeric code by SMS                    |
| `totp`        | Setup/Login | TOTP code from authenticator app (RFC 6238) |

## Documentation

| Topic | File |
|---|---|
| Verification flows (setup, login, OTP choice) | [docs/verification_flow.md](docs/verification_flow.md) |
| Installation | [docs/installation.md](docs/installation.md) |
| Configuration reference | [docs/configuration.md](docs/configuration.md) |
| Environment variables | [docs/env.md](docs/env.md) |
| UsersController actions | [docs/users_controller.md](docs/users_controller.md) |
| VerificationComponent | [docs/verification_component.md](docs/verification_component.md) |
| VerificationHelper | [docs/verification_helper.md](docs/verification_helper.md) |
| Email verification & Email OTP | [docs/email_verification.md](docs/email_verification.md) |
| SMS OTP | [docs/sms_verification.md](docs/sms_verification.md) |
| TOTP | [docs/totp_verification.md](docs/totp_verification.md) |
| Enable / disable individual steps | [docs/verificator_enable_disable.md](docs/verificator_enable_disable.md) |
| API reference | [docs/api/index.md](docs/api/index.md) |

## License

MIT License. See [LICENSE](LICENSE) for details.
