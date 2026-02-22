# SodiumCrypto Driver

Symmetric encryption via the PHP **libsodium** extension (`sodium_crypto_secretbox`).
Recommended driver for encrypting TOTP secrets at rest.

## Requirements

- libsodium extension (bundled with PHP ≥ 7.2, so covered by CakePHP 5)
- A 32-byte (256-bit) secret key

Verify availability:

```bash
php -r "var_dump(function_exists('sodium_crypto_secretbox'));"
# bool(true)
```

## Key generation

```bash
php -r "echo base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)), PHP_EOL;"
```

Store the result in your environment — never hardcode it:

```
VERIFICATION_SODIUM_KEY="VLWUPtYvCE6APk9d+1x6l/jVblP+9uA0fTwREzzfnPs="
```

## Configuration

```php
// config/verification.php
'crypto' => [
    'driver' => 'sodium',
    'key'    => base64_decode(env('VERIFICATION_SODIUM_KEY', '')),
],
```

See [configuration.md](../configuration.md) for the full `crypto` reference.

## vs AES-GCM

Both drivers are secure. Use `SodiumCrypto` when libsodium is available (it usually is).
Fall back to `AesGcmCrypto` only when the host does not have the sodium extension.
See [aes_gcm_crypto.md](aes_gcm_crypto.md).

---

## Documentation

| Topic | File |
|---|---|
| README | [../../README.md](../../README.md) |
| Verification flows (setup, login, OTP choice) | [../verification_flow.md](../verification_flow.md) |
| Installation | [../installation.md](../installation.md) |
| Configuration reference | [../configuration.md](../configuration.md) |
| Environment variables | [../env.md](../env.md) |
| UsersController actions | [../users_controller.md](../users_controller.md) |
| VerificationComponent | [../verification_component.md](../verification_component.md) |
| VerificationHelper | [../verification_helper.md](../verification_helper.md) |
| Email verification & Email OTP | [../email_verification.md](../email_verification.md) |
| SMS OTP | [../sms_verification.md](../sms_verification.md) |
| TOTP | [../totp_verification.md](../totp_verification.md) |
| Enable / disable individual steps | [../verificator_enable_disable.md](../verificator_enable_disable.md) |
| API reference index | [index.md](index.md) |
