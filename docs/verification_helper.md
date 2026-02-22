# Verification Helper

`VerificationHelper` provides view-layer utilities for the verification plugin.
It is automatically available in all views once the plugin is loaded — no
explicit `$this->loadHelper()` call is needed.

## Helper methods

| Method | Returns | Description |
|---|---|---|
| `qrCode(string $data)` | `string` | Renders a QR code SVG for the given payload, or a plain `<div>` fallback if `bacon/bacon-qr-code` is not installed |
| `lastSmsCode()` | `string\|null` | Returns the last SMS OTP code from the cache (debug mode only) |

---

## `qrCode(string $data): string`

Renders the `otpauth://totp/` URI as an inline SVG QR code. Used in the TOTP
enrollment view.

**Dependencies:** Requires `bacon/bacon-qr-code` (`BaconQrCode\Writer`).
If the package is not installed, a plain `<div class="qrcode">` containing the
raw URI string is returned instead — useful as a fallback during development.

### Output

```html
<!-- bacon/bacon-qr-code installed -->
<div class="qrcode"><svg ...>...</svg></div>

<!-- fallback (package not installed) -->
<div class="qrcode">otpauth://totp/...</div>
```

### Usage in enroll view

The `$qrData` variable is set automatically by `handleEnroll()`:

```php
<!-- templates/Users/enroll.php -->
<?php if (!empty($qrData)) : ?>
    <?= $this->Verification->qrCode($qrData) ?>
<?php endif; ?>

<?php if (!empty($secret)) : ?>
    <p>Manual secret: <code><?= h($secret) ?></code></p>
<?php endif; ?>
```

To style the output, target `.qrcode` in your CSS:

```css
.qrcode svg {
    width: 200px;
    height: 200px;
}
```

---

## `lastSmsCode(): ?string`

Returns the numeric OTP code from the last SMS sent via `DummyTransport`.
Returns `null` outside of debug mode or when no SMS has been sent yet.

**Only available in `debug` mode.** Always returns `null` in production.

### Purpose

During development, when the real SMS gateway is replaced with `DummyTransport`,
the sent code is stored in the CakePHP `default` cache under the key
`verification_last_sms`. This method extracts the numeric digits from that
cached message and trims them to the configured OTP length
(`Verification.otp.length`, default `6`).

Use it in a development layout or debug toolbar to display the code without
checking logs or the cache manually.

### Usage

```php
<!-- layouts/default.php or a debug-only partial -->
<?php if (\Cake\Core\Configure::read('debug')) : ?>
    <?php $code = $this->Verification->lastSmsCode() ?>
    <?php if ($code !== null) : ?>
        <div class="debug-sms-code">Last SMS code: <strong><?= h($code) ?></strong></div>
    <?php endif; ?>
<?php endif; ?>
```

---

## Loading the helper manually

The helper is auto-loaded by the plugin when you add
`$this->loadComponent('Verification.Verification')` in `AppController`.
If you need it in a controller that does not load the component, load it
explicitly in the view class:

```php
// src/View/AppView.php
public function initialize(): void
{
    parent::initialize();
    $this->loadHelper('Verification.Verification');
}
```

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
