<?php
declare(strict_types=1);

namespace CakeVerification\Security\Driver;

use CakeVerification\Security\CryptoInterface;
use CakeVerification\Security\KeyDecoderTrait;
use RuntimeException;
use function Cake\I18n\__d;

/**
 * AES-GCM crypto driver using OpenSSL.
 *
 * Uses AES-256-GCM with a random 12-byte IV per message.
 * Payload format (base64-encoded): IV(12) || TAG(16) || CIPHERTEXT
 */
final class AesGcmCrypto implements CryptoInterface
{
    use KeyDecoderTrait;

    private const CIPHER = 'aes-256-gcm';
    private const KEY_LENGTH = 32;
    private const TAG_LENGTH = 16;

    /**
     * @var string Raw binary key (32 bytes)
     */
    private string $key;

    /**
     * @param string $key Raw binary or base64-encoded key (32 bytes).
     * @throws \RuntimeException
     */
    public function __construct(string $key)
    {
        if ($key === '') {
            throw new RuntimeException(__d('verification', 'AesGcmCrypto requires a non-empty key.'));
        }

        $key = $this->decodeKey($key, self::KEY_LENGTH);

        if (strlen($key) !== self::KEY_LENGTH) {
            throw new RuntimeException(sprintf(
                __d('verification', 'Invalid key length for AesGcmCrypto. Expected {0} bytes, got {1}.'),
                self::KEY_LENGTH,
                strlen($key),
            ));
        }
        $this->key = $key;

        $this->assertCipherSupported();
    }

    /**
     * Destructor - securely wipe key from memory.
     */
    public function __destruct()
    {
        if (function_exists('sodium_memzero')) {
            /** @var string $key */
            $key = $this->key;
            sodium_memzero($key);
        }
    }

    /**
     * @inheritDoc
     */
    public static function name(): string
    {
        return 'aesgcm';
    }

    /**
     * @inheritDoc
     */
    public function getKeyLength(): int
    {
        return self::KEY_LENGTH;
    }

    /**
     * @inheritDoc
     */
    public function encrypt(string $plaintext): string
    {
        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        if ($ivLen === false || $ivLen < 12) {
            throw new RuntimeException(__d('verification', 'Invalid IV length for AES-GCM.'));
        }
        $iv = random_bytes($ivLen);
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH,
        );
        if ($ciphertext === false || strlen($tag) !== self::TAG_LENGTH) {
            throw new RuntimeException(__d('verification', 'Encryption failed using AesGcmCrypto.'));
        }

        return base64_encode($iv . $tag . $ciphertext);
    }

    /**
     * @inheritDoc
     */
    public function decrypt(string $payload): string
    {
        $data = base64_decode($payload, true);
        if ($data === false) {
            throw new RuntimeException(__d('verification', 'Invalid base64 payload for AesGcmCrypto.'));
        }

        $ivLen = openssl_cipher_iv_length(self::CIPHER);
        if ($ivLen === false || strlen($data) <= $ivLen + self::TAG_LENGTH) {
            throw new RuntimeException(__d('verification', 'Invalid payload size for AesGcmCrypto.'));
        }

        $offset = 0;
        $iv = substr($data, $offset, $ivLen);
        $offset += $ivLen;
        $tag = substr($data, $offset, self::TAG_LENGTH);
        $offset += self::TAG_LENGTH;
        $ciphertext = substr($data, $offset);

        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
        );

        if ($plaintext === false) {
            throw new RuntimeException(__d('verification', 'Decryption failed using AesGcmCrypto.'));
        }

        return $plaintext;
    }

    /**
     * Ensure the cipher is available on this system.
     *
     * @throws \RuntimeException
     * @return void
     */
    private function assertCipherSupported(): void
    {
        $ciphers = openssl_get_cipher_methods(true);
        if (!in_array(self::CIPHER, $ciphers, true)) {
            throw new RuntimeException(__d('verification', 'OpenSSL cipher "{0}" is not supported.', self::CIPHER));
        }
    }
}
