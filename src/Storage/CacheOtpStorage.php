<?php
declare(strict_types=1);

namespace Verification\Storage;

use Cake\Cache\Cache;
use RuntimeException;
use function Cake\I18n\__d;

final class CacheOtpStorage implements OtpStorageInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [])
    {
        $this->config = $config + [
            'cacheConfig' => 'verification',
            'stepCacheConfig' => [],
            'maxAttempts' => 5,
            'lockoutSeconds' => 900,
            'resendCooldown' => 60,
            'burst' => 0,
            'periodSeconds' => 0,
        ];

        $this->ensureCacheConfig((string)$this->config['cacheConfig']);
    }

    /**
     * @inheritDoc
     */
    public function issue(string $identityKey, string $step, string $code, int $ttl): void
    {
        $cacheConfig = $this->cacheConfigForStep($step);
        $key = $this->cacheKey($identityKey, $step);
        $now = time();

        $this->assertRateLimit($identityKey, $now);

        $existing = $this->read($key, $cacheConfig);
        if ($existing !== null && isset($existing['lastIssued'])) {
            $cooldown = (int)$this->config['resendCooldown'];
            if ($cooldown > 0 && ($now - (int)$existing['lastIssued']) < $cooldown) {
                throw new RuntimeException(__d('verification', 'OTP resend cooldown is active.'));
            }
        }

        $salt = bin2hex(random_bytes(8));
        $hash = hash('sha256', $salt . $code);

        $payload = [
            'hash' => $hash,
            'salt' => $salt,
            'expires' => $now + $ttl,
            'attempts' => 0,
            'lockedUntil' => 0,
            'lastIssued' => $now,
        ];

        Cache::write($key, $payload, $cacheConfig);
    }

    /**
     * @inheritDoc
     */
    public function verify(string $identityKey, string $step, string $code): bool
    {
        $cacheConfig = $this->cacheConfigForStep($step);
        $key = $this->cacheKey($identityKey, $step);
        $payload = $this->read($key, $cacheConfig);
        if ($payload === null) {
            return false;
        }

        $now = time();
        if (!empty($payload['lockedUntil']) && $now < (int)$payload['lockedUntil']) {
            return false;
        }

        if (!empty($payload['expires']) && $now > (int)$payload['expires']) {
            Cache::delete($key, $cacheConfig);

            return false;
        }

        $salt = (string)($payload['salt'] ?? '');
        $hash = (string)($payload['hash'] ?? '');
        $candidate = hash('sha256', $salt . $code);

        if (hash_equals($hash, $candidate)) {
            Cache::delete($key, $cacheConfig);

            return true;
        }

        $attempts = (int)($payload['attempts'] ?? 0) + 1;
        $payload['attempts'] = $attempts;

        if ($attempts >= (int)$this->config['maxAttempts']) {
            $payload['lockedUntil'] = $now + (int)$this->config['lockoutSeconds'];
        }

        Cache::write($key, $payload, $cacheConfig);

        return false;
    }

    /**
     * @inheritDoc
     */
    public function invalidate(string $identityKey, string $step): void
    {
        $cacheConfig = $this->cacheConfigForStep($step);
        $key = $this->cacheKey($identityKey, $step);

        Cache::delete($key, $cacheConfig);
    }

    /**
     * @param string $identityKey Identity key
     * @param string $step Step name
     * @return string
     */
    private function cacheKey(string $identityKey, string $step): string
    {
        return sprintf('otp:%s:%s', $step, $identityKey);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function read(string $key, string $cacheConfig): ?array
    {
        $payload = Cache::read($key, $cacheConfig);

        return is_array($payload) ? $payload : null;
    }

    /**
     * @param string $step Step name
     * @return string
     */
    private function cacheConfigForStep(string $step): string
    {
        $map = $this->config['stepCacheConfig'];
        if (is_array($map) && isset($map[$step]) && is_string($map[$step]) && $map[$step] !== '') {
            $this->ensureCacheConfig($map[$step]);

            return $map[$step];
        }

        $default = (string)$this->config['cacheConfig'];
        $this->ensureCacheConfig($default);

        return $default;
    }

    /**
     * @param string $identityKey Identity key
     * @param int $now Current timestamp
     * @return void
     */
    private function assertRateLimit(string $identityKey, int $now): void
    {
        $burst = (int)($this->config['burst'] ?? 0);
        $periodSeconds = (int)($this->config['periodSeconds'] ?? 0);
        if ($burst <= 0 || $periodSeconds <= 0) {
            return;
        }

        $cacheConfig = (string)$this->config['cacheConfig'];
        $this->ensureCacheConfig($cacheConfig);

        $key = $this->rateKey($identityKey);
        $payload = $this->read($key, $cacheConfig);

        $windowStart = (int)($payload['windowStart'] ?? 0);
        $count = (int)($payload['count'] ?? 0);
        if ($windowStart === 0 || ($windowStart + $periodSeconds) <= $now) {
            $windowStart = $now;
            $count = 0;
        }

        if ($count >= $burst) {
            throw new RuntimeException(__d('verification', 'OTP rate limit exceeded.'));
        }

        $payload = [
            'windowStart' => $windowStart,
            'count' => $count + 1,
        ];

        Cache::write($key, $payload, $cacheConfig);
    }

    /**
     * @param string $identityKey Identity key
     * @return string
     */
    private function rateKey(string $identityKey): string
    {
        return sprintf('otp:rate:%s', $identityKey);
    }

    /**
     * @param string $cacheConfig Cache configuration name
     * @return void
     */
    private function ensureCacheConfig(string $cacheConfig): void
    {
        if (!Cache::getConfig($cacheConfig)) {
            Cache::setConfig($cacheConfig, [
                'className' => 'Array',
                'prefix' => 'verification_',
                'duration' => '+1 day',
            ]);
        }
    }
}
