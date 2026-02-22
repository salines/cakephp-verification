<?php
declare(strict_types=1);

namespace Salines\Verification\Test\TestCase\Security;

use Cake\TestSuite\TestCase;
use Salines\Verification\Security\CryptoFactory;
use Salines\Verification\Security\Driver\AesGcmCrypto;
use Salines\Verification\Security\Driver\SodiumCrypto;

/**
 * @covers \Salines\Verification\Security\CryptoFactory
 */
final class CryptoFactoryTest extends TestCase
{
    public function testCreateReturnsNullForEmptyConfig(): void
    {
        $this->assertNull(CryptoFactory::create([]));
    }

    public function testCreateReturnsNullWhenDriverMissing(): void
    {
        $this->assertNull(CryptoFactory::create(['key' => 'abc']));
    }

    public function testCreateReturnsNullWhenKeyMissing(): void
    {
        $this->assertNull(CryptoFactory::create(['driver' => 'sodium']));
    }

    public function testCreateReturnsNullWhenKeyEmpty(): void
    {
        $this->assertNull(CryptoFactory::create(['driver' => 'sodium', 'key' => '']));
    }

    public function testCreateReturnsNullForUnknownDriver(): void
    {
        $this->assertNull(CryptoFactory::create(['driver' => 'unknown', 'key' => 'abc']));
    }

    public function testCreateReturnsSodiumCrypto(): void
    {
        $key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $crypto = CryptoFactory::create(['driver' => 'sodium', 'key' => $key]);

        $this->assertInstanceOf(SodiumCrypto::class, $crypto);
    }

    public function testCreateReturnsAesGcmCrypto(): void
    {
        $key = random_bytes(32);
        $crypto = CryptoFactory::create(['driver' => 'aes-gcm', 'key' => $key]);

        $this->assertInstanceOf(AesGcmCrypto::class, $crypto);
    }

    public function testSodiumRoundtrip(): void
    {
        $key = random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
        $crypto = CryptoFactory::create(['driver' => 'sodium', 'key' => $key]);

        $this->assertNotNull($crypto);
        $plain = 'MYSECRETTOTP';
        $this->assertSame($plain, $crypto->decrypt($crypto->encrypt($plain)));
    }

    public function testAesGcmRoundtrip(): void
    {
        $key = random_bytes(32);
        $crypto = CryptoFactory::create(['driver' => 'aes-gcm', 'key' => $key]);

        $this->assertNotNull($crypto);
        $plain = 'MYSECRETTOTP';
        $this->assertSame($plain, $crypto->decrypt($crypto->encrypt($plain)));
    }

    public function testGenerateKeyReturnsBase64String(): void
    {
        $key = CryptoFactory::generateKey('sodium');
        $this->assertNotEmpty($key);
        $decoded = base64_decode($key, true);
        $this->assertNotFalse($decoded);
        $this->assertSame(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, strlen($decoded));
    }

    public function testGenerateKeyAesGcm(): void
    {
        $key = CryptoFactory::generateKey('aes-gcm');
        $decoded = base64_decode($key, true);
        $this->assertNotFalse($decoded);
        $this->assertSame(32, strlen($decoded));
    }
}
