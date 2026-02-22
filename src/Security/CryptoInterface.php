<?php
declare(strict_types=1);

namespace Salines\Verification\Security;

/**
 * Interface for all crypto drivers.
 *
 * Responsibilities:
 * - Provide symmetric encryption/decryption.
 * - Accept arbitrary string input/output.
 * - Implementations MUST throw \RuntimeException on failure.
 */
interface CryptoInterface
{
    /**
     * Encrypt a string into a secure payload.
     *
     * @param string $plaintext Raw data to encrypt.
     * @return string Encrypted payload (base64/url-safe).
     * @throws \RuntimeException On failure.
     */
    public function encrypt(string $plaintext): string;

    /**
     * Decrypt a previously encrypted payload.
     *
     * @param string $payload Encrypted payload as produced by encrypt().
     * @return string Decrypted plaintext.
     * @throws \RuntimeException On failure.
     */
    public function decrypt(string $payload): string;

    /**
     * Return the key length (in bytes) required by this driver.
     *
     * @return int|null Key length in bytes, or null if not applicable.
     */
    public function getKeyLength(): ?int;

    /**
     * Return the driver identifier (e.g. "sodium", "aesgcm", "base64", "plain").
     *
     * @return string
     */
    public static function name(): string;
}
