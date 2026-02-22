<?php
declare(strict_types=1);

namespace CakeVerification\Security;

/**
 * Trait for decoding encryption keys.
 *
 * Provides consistent base64 key detection and decoding across crypto drivers.
 */
trait KeyDecoderTrait
{
    /**
     * Decode a key that may be base64-encoded.
     *
     * @param string $key Raw binary or base64-encoded key.
     * @param int $expectedLength Expected key length in bytes.
     * @return string Raw binary key.
     */
    private function decodeKey(string $key, int $expectedLength): string
    {
        // If already correct length, assume raw binary
        if (strlen($key) === $expectedLength) {
            return $key;
        }

        // Try base64 decode
        $decoded = base64_decode($key, true);
        if ($decoded !== false && strlen($decoded) === $expectedLength) {
            return $decoded;
        }

        // Return original - validation will catch invalid length
        return $key;
    }
}
