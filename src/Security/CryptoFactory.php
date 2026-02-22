<?php
declare(strict_types=1);

namespace Verification\Security;

use RuntimeException;
use Verification\Security\Driver\AesGcmCrypto;
use Verification\Security\Driver\SodiumCrypto;
use function Cake\I18n\__d;

/**
 * Factory for creating crypto driver instances.
 *
 * Expects the flat config structure used in config/verification.php:
 *
 *   'crypto' => [
 *       'driver' => 'sodium',          // 'sodium' | 'aes-gcm'
 *       'key'    => base64_decode(...),
 *   ]
 */
final class CryptoFactory
{
    /**
     * Create a crypto driver from the Verification.crypto config array.
     *
     * @param array<string, mixed> $config
     * @return \Verification\Security\CryptoInterface|null  null when crypto is not configured
     */
    public static function create(array $config): ?CryptoInterface
    {
        $driver = (string)($config['driver'] ?? '');
        if ($driver === '') {
            return null;
        }

        $key = $config['key'] ?? '';
        if (!is_string($key) || $key === '') {
            return null;
        }

        try {
            return match ($driver) {
                'sodium' => new SodiumCrypto($key),
                'aes-gcm' => new AesGcmCrypto($key),
                default => throw new RuntimeException(
                    __d('verification', 'Unknown crypto driver: {0}', $driver),
                ),
            };
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * Generate a cryptographically secure key for a driver.
     *
     * @param string $driver  'aesgcm' or 'sodium'
     * @param bool   $base64  Return base64-encoded string (default true)
     * @return string
     * @throws \RuntimeException If driver is unknown or extension is missing.
     */
    public static function generateKey(string $driver, bool $base64 = true): string
    {
        $length = match ($driver) {
            'aesgcm', 'aes-gcm' => 32,
            'sodium' => self::getSodiumKeyLength(),
            default => throw new RuntimeException(
                __d('verification', 'Unknown crypto driver: {0}', $driver),
            ),
        };

        $key = random_bytes($length);

        return $base64 ? base64_encode($key) : $key;
    }

    /**
     * @return int
     * @throws \RuntimeException If sodium extension is not available.
     */
    private static function getSodiumKeyLength(): int
    {
        if (!extension_loaded('sodium')) {
            throw new RuntimeException(
                __d('verification', 'Sodium extension is required for sodium crypto driver.'),
            );
        }

        return SODIUM_CRYPTO_SECRETBOX_KEYBYTES;
    }
}
