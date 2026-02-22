# TOTP Verification

`totp` implements RFC 6238 Time-based One-Time Passwords using an authenticator
app (Google Authenticator, Authy, etc.). No external library is required for
code generation.

For SVG QR code rendering in the enroll view, install the optional package:

```bash
composer require bacon/bacon-qr-code
```

Without it, `VerificationHelper::qrCode()` falls back to displaying the raw
`otpauth://totp/...` URI, which the user can enter manually.

It can be used as a **setup step** (list it in `requiredSetupSteps`) — enroll
and verify once, then require on every login.

## Prerequisites

See [installation.md](installation.md) for the full setup guide.

## Database fields

| Column | Type | Notes |
|---|---|---|
| `totp_secret` | VARCHAR(255), nullable | Base32 secret — store encrypted |
| `totp_verified_at` | DATETIME, nullable | Set after first successful verify |

## Configuration

```php
// config/verification.php
<?php
return [
    'Verification' => [
        // as a setup step:
        'requiredSetupSteps' => ['emailVerify', 'totp'],

        // AND/OR as the login 2FA step:
        'login' => [
            'enabled' => true,
            'step'    => 'totp',
        ],

        'drivers' => [
            'totp' => [
                'options' => [
                    'issuer'    => 'MyApp',  // shown in the authenticator app
                    'digits'    => 6,
                    'period'    => 30,       // seconds per TOTP window
                    'algorithm' => 'sha1',   // sha1 | sha256 | sha512
                ],
            ],
        ],

        // Secret encryption (strongly recommended)
        'crypto' => [
            'driver' => 'sodium',
            'key'    => base64_decode(env('VERIFICATION_SODIUM_KEY', '')),
        ],
    ],
];
```

### Custom field names

```php
'drivers' => [
    'totp' => [
        'fields' => [
            'totpSecret'   => 'mfa_secret',
            'totpVerified' => 'mfa_verified_at',
        ],
    ],
],
```

---

## How `isVerified` works

`TotpVerificator::isVerified()` checks both:

1. The secret field (`totp_secret`) is not empty.
2. The verified-at field (`totp_verified_at`) is not null.

Having a secret alone is **not** sufficient. Both conditions must be met.

---

## Controller actions

### AppController

```php
public function initialize(): void
{
    parent::initialize();
    $this->loadComponent('Authentication.Authentication');
    $this->loadComponent('Verification.Verification', ['requireVerified' => true]);
    $this->Verification->allowUnverified(['login', 'logout', 'pending']);
}
```

### enroll

Generates a secret, displays the QR code, and verifies the first code entered
by the user to confirm enrollment.

```php
public function enroll(): void
{
    $this->request->allowMethod(['get', 'post']);
    $response = $this->Verification->handleEnroll();
    if ($response !== null) {
        return $response;
    }
    // View variables set automatically:
    // $qrData  — otpauth:// URI for the QR code
    // $secret  — plain Base32 secret to display as manual fallback
}
```

The component:

1. Loads the user from the database.
2. If no secret exists, generates a random 32-character Base32 secret, optionally
   encrypts it (see [sodium_crypto.md](api/sodium_crypto.md) / [aes_gcm_crypto.md](api/aes_gcm_crypto.md)),
   saves it, and refreshes the Authentication identity.
3. Builds the `otpauth://totp/` URI with `issuer` and `email` from config.
4. On POST: verifies the submitted 6-digit code, marks `totp_verified_at`, then
   redirects to the next pending step.

### verify

Handles TOTP entry for the login 2FA flow.

```php
public function verify(?string $step = null): void
{
    $this->request->allowMethod(['get', 'post']);
    $response = $this->Verification->handleVerify($step);
    if ($response !== null) {
        return $response;
    }
    // $verification and $step are set on the view automatically
}
```

---

## QR code template

Display the QR code in `templates/Users/enroll.php`:

```php
<?php
/**
 * @var string $qrData    otpauth:// URI
 * @var string $secret    plain Base32 secret
 */
?>
<h2><?= __('Scan with your authenticator app') ?></h2>

<?php
// Using endroid/qr-code or equivalent:
// echo $this->Html->image(
//     'https://api.qrserver.com/v1/create-qr-code/?data=' . urlencode($qrData) . '&size=200x200',
//     ['alt' => 'QR code']
// );
?>

<p><?= __('Or enter this code manually:') ?> <strong><?= h($secret) ?></strong></p>

<?= $this->Form->create(null) ?>
    <?= $this->Form->control('code', ['label' => __('6-digit code'), 'type' => 'text', 'autocomplete' => 'one-time-code']) ?>
    <?= $this->Form->button(__('Confirm')) ?>
<?= $this->Form->end() ?>
```

---

## Secret encryption

The TOTP secret is sensitive. Encrypt it at rest with the `crypto` config key.

### Sodium (recommended)

```php
'crypto' => [
    'driver' => 'sodium',
    'key'    => base64_decode(env('VERIFICATION_SODIUM_KEY', '')),
],
```

Generate a key:

```bash
php -r "echo base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)), PHP_EOL;"
```

See [sodium_crypto.md](api/sodium_crypto.md) for full details.

### AES-GCM (fallback)

```php
'crypto' => [
    'driver' => 'aes-gcm',
    'key'    => base64_decode(env('VERIFICATION_AESGCM_KEY', '')),
],
```

Generate a key:

```bash
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

See [aes_gcm_crypto.md](api/aes_gcm_crypto.md) for full details.

---

## Notes

- TOTP uses a sliding ±1 window, accepting the previous, current, and next
  30-second codes to tolerate clock skew.
- The secret is decrypted in memory by the component before calling
  `TotpVerificator::verify()`. It is never stored in plain text if crypto is configured.
- For login 2FA with TOTP, the component skips auto-start (TOTP has no delivery
  step), unlike `emailOtp` and `smsOtp`.

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
