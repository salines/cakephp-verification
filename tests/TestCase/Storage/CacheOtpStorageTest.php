<?php
declare(strict_types=1);

namespace CakeVerification\Test\TestCase\Storage;

use Cake\TestSuite\TestCase;
use CakeVerification\Storage\CacheOtpStorage;
use RuntimeException;

final class CacheOtpStorageTest extends TestCase
{
    public function testIssueAndVerifyHappyPath(): void
    {
        $storage = new CacheOtpStorage();

        $storage->issue('user:1', 'email_otp', '123456', 300);

        $this->assertTrue($storage->verify('user:1', 'email_otp', '123456'));
    }

    public function testVerifyDeletesCodeAfterSuccess(): void
    {
        $storage = new CacheOtpStorage();

        $storage->issue('user:2', 'email_otp', '111111', 300);
        $storage->verify('user:2', 'email_otp', '111111');

        // Second attempt with the same code must fail (consumed)
        $this->assertFalse($storage->verify('user:2', 'email_otp', '111111'));
    }

    public function testVerifyReturnsFalseForWrongCode(): void
    {
        $storage = new CacheOtpStorage();

        $storage->issue('user:3', 'email_otp', '999999', 300);

        $this->assertFalse($storage->verify('user:3', 'email_otp', '000000'));
    }

    public function testVerifyReturnsFalseWhenNothingIssued(): void
    {
        $storage = new CacheOtpStorage();

        $this->assertFalse($storage->verify('user:ghost', 'email_otp', '000000'));
    }

    public function testVerifyReturnsFalseWhenExpired(): void
    {
        $storage = new CacheOtpStorage();

        $storage->issue('user:4', 'email_otp', '777777', -1);

        $this->assertFalse($storage->verify('user:4', 'email_otp', '777777'));
    }

    public function testInvalidate(): void
    {
        $storage = new CacheOtpStorage();

        $storage->issue('user:5', 'email_otp', '555555', 300);
        $storage->invalidate('user:5', 'email_otp');

        $this->assertFalse($storage->verify('user:5', 'email_otp', '555555'));
    }

    public function testLockoutAfterMaxAttempts(): void
    {
        $storage = new CacheOtpStorage([
            'maxAttempts' => 2,
            'lockoutSeconds' => 600,
        ]);

        $storage->issue('user:6', 'sms_otp', '424242', 300);

        $this->assertFalse($storage->verify('user:6', 'sms_otp', '000000'));
        $this->assertFalse($storage->verify('user:6', 'sms_otp', '000000'));

        // Correct code rejected because account is now locked
        $this->assertFalse($storage->verify('user:6', 'sms_otp', '424242'));
    }

    public function testResendCooldownThrows(): void
    {
        $storage = new CacheOtpStorage(['resendCooldown' => 600]);

        $storage->issue('user:7', 'email_otp', '123123', 300);

        $this->expectException(RuntimeException::class);
        $storage->issue('user:7', 'email_otp', '456456', 300);
    }

    public function testResendAllowedAfterCooldownExpires(): void
    {
        $storage = new CacheOtpStorage(['resendCooldown' => 0]);

        $storage->issue('user:8', 'email_otp', '111222', 300);
        // cooldown=0 means no cooldown enforced
        $storage->issue('user:8', 'email_otp', '333444', 300);

        $this->assertTrue($storage->verify('user:8', 'email_otp', '333444'));
    }

    public function testBurstRateLimitThrows(): void
    {
        $storage = new CacheOtpStorage([
            'burst' => 2,
            'periodSeconds' => 60,
            'resendCooldown' => 0,
        ]);

        $storage->issue('user:9', 'email_otp', '100001', 300);
        $storage->issue('user:9', 'email_otp', '100002', 300);

        $this->expectException(RuntimeException::class);
        $storage->issue('user:9', 'email_otp', '100003', 300);
    }

    public function testStepCacheConfigRouting(): void
    {
        $storage = new CacheOtpStorage([
            'stepCacheConfig' => ['sms_otp' => 'verification_sms'],
        ]);

        $storage->issue('user:10', 'sms_otp', '654321', 300);
        $this->assertTrue($storage->verify('user:10', 'sms_otp', '654321'));

        // Different step uses default cache config — they are isolated
        $storage->issue('user:10', 'email_otp', '654321', 300);
        $this->assertTrue($storage->verify('user:10', 'email_otp', '654321'));
    }
}
