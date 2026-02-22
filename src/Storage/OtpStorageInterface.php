<?php
declare(strict_types=1);

namespace CakeVerification\Storage;

interface OtpStorageInterface
{
    /**
     * @param string $identityKey Identity key
     * @param string $step Step name
     * @param string $code Code to store
     * @param int $ttl Time-to-live in seconds
     * @return void
     */
    public function issue(string $identityKey, string $step, string $code, int $ttl): void;

    /**
     * @param string $identityKey Identity key
     * @param string $step Step name
     * @param string $code Code to verify
     * @return bool
     */
    public function verify(string $identityKey, string $step, string $code): bool;

    /**
     * @param string $identityKey Identity key
     * @param string $step Step name
     * @return void
     */
    public function invalidate(string $identityKey, string $step): void;
}
