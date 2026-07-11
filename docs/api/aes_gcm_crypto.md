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

Full documentation index: [../index.md](../index.md)
