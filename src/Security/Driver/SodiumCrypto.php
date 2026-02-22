<?php
declare(strict_types=1);

namespace CakeVerification\Security\Driver;

use CakeVerification\Security\CryptoInterface;
use CakeVerification\Security\KeyDecoderTrait;
use RuntimeException;
use function Cake\I18n\__d;

/**
 * Sodium-based crypto driver.
 *
 * Uses libsodium's secretbox (XSalsa20-Poly1305).
 * - Nonce is randomly generated per encryption and prepended to the payload.
 * - Payload is base64 encoded for storage/transport.
 */
final class SodiumCrypto implements CryptoInterface
{
    use KeyDecoderTrait;

    /**
     * @var string
     */
    private string $key;

    /**
     * Constructor.
     *
     * @param string $key Raw binary key or base64-encoded key.
     * @throws \RuntimeException If key length is invalid.
     */
    public function __construct(string $key)
    {
        if (!extension_loaded('sodium')) {
            throw new RuntimeException(
                __d('verification', 'Sodium extension is required for SodiumCrypto driver.'),
            );
        }

        if ($key === '') {
            throw new RuntimeException(__d('verification', 'SodiumCrypto requires a non-empty key.'));
        }

        $key = $this->decodeKey($key, SODIUM_CRYPTO_SECRETBOX_KEYBYTES);

        if (strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException(sprintf(
                __d('verification', 'Invalid key length for SodiumCrypto. Expected {0} bytes, got {1}.'),
                SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
                strlen($key),
            ));
        }

        $this->key = $key;
    }

    /**
     * Destructor - securely wipe key from memory.
     */
    public function __destruct()
    {
        /** @var string $key */
        $key = $this->key;
        sodium_memzero($key);
    }

    /**
     * @inheritDoc
     */
    public function encrypt(string $plaintext): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plaintext, $nonce, $this->key);

        return base64_encode($nonce . $cipher);
    }

    /**
     * @inheritDoc
     */
    public function decrypt(string $payload): string
    {
        $decoded = base64_decode($payload, true);
        if ($decoded === false) {
            throw new RuntimeException(__d('verification', 'Invalid base64 payload for SodiumCrypto.'));
        }

        $nonceSize = SODIUM_CRYPTO_SECRETBOX_NONCEBYTES;
        if (strlen($decoded) <= $nonceSize) {
            throw new RuntimeException(__d('verification', 'Invalid payload size for SodiumCrypto.'));
        }

        $nonce = substr($decoded, 0, $nonceSize);
        $cipher = substr($decoded, $nonceSize);

        $plaintext = sodium_crypto_secretbox_open($cipher, $nonce, $this->key);
        if ($plaintext === false) {
            throw new RuntimeException(__d('verification', 'Decryption failed using SodiumCrypto.'));
        }

        return $plaintext;
    }

    /**
     * @inheritDoc
     */
    public function getKeyLength(): int
    {
        return SODIUM_CRYPTO_SECRETBOX_KEYBYTES;
    }

    /**
     * @inheritDoc
     */
    public static function name(): string
    {
        return 'sodium';
    }
}
