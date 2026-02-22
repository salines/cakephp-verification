# AesGcmCrypto Driver

Symmetric encryption via **OpenSSL AES-256-GCM**.
Use this driver when libsodium is not available on the host.

## Requirements

- PHP 8.1+ (covered by CakePHP 5)
- OpenSSL with `aes-256-gcm` support (OpenSSL 1.1.1+)

Verify support:

```bash
php -r "var_dump(in_array('aes-256-gcm', openssl_get_cipher_methods(true)));"
# bool(true)
```

## Key generation

```bash
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

Store the result in your environment — never hardcode it:

```
VERIFICATION_AESGCM_KEY="...base64-key..."
```

## Configuration

```php
// config/verification.php
'crypto' => [
    'driver' => 'aes-gcm',
    'key'    => base64_decode(env('VERIFICATION_AESGCM_KEY', '')),
],
```

See [configuration.md](../configuration.md) for the full `crypto` reference.

## vs SodiumCrypto

Both drivers are secure. Prefer `SodiumCrypto` when libsodium is available.
See [sodium_crypto.md](sodium_crypto.md).

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
