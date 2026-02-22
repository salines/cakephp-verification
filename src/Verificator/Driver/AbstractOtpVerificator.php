<?php
declare(strict_types=1);

namespace Verification\Verificator\Driver;

use Authentication\IdentityInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Verification\Storage\CacheOtpStorage;
use Verification\Storage\OtpStorageInterface;
use Verification\Verificator\VerificationVerificatorInterface;
use function Cake\I18n\__d;

abstract class AbstractOtpVerificator implements VerificationVerificatorInterface
{
    use ReadFieldTrait;

    /**
     * @var array<string, mixed>
     */
    protected array $config;

    protected OtpStorageInterface $storage;

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(array $config = [], ?OtpStorageInterface $storage = null)
    {
        $this->config = $config + [
            'fields' => [],
            'otp' => ['length' => 6, 'ttl' => 600],
            'storage' => [],
            'identityField' => 'id',
            'options' => [],
        ];

        $this->storage = $storage ?? new CacheOtpStorage((array)$this->config['storage']);
    }

    /**
     * @inheritDoc
     */
    public function withConfig(array $config): static
    {
        $clone = clone $this;
        $clone->config = $config + $this->config;
        if (array_key_exists('storage', $config)) {
            $clone->storage = new CacheOtpStorage((array)$clone->config['storage']);
        }

        return $clone;
    }

    /**
     * @inheritDoc
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @inheritDoc
     */
    public function requiresInput(): bool
    {
        return true;
    }

    /**
     * @inheritDoc
     */
    public function expectedFields(): array
    {
        $length = (int)($this->config['otp']['length'] ?? 6);

        return ['code' => 'string|digits|length:' . $length];
    }

    /**
     * @inheritDoc
     */
    public function start(ServerRequestInterface $request, IdentityInterface $identity): void
    {
        if (!$this->canStart($identity)) {
            return;
        }

        $code = $this->issueCode($identity);
        $this->deliver($request, $identity, $code);
    }

    /**
     * @inheritDoc
     */
    public function verify(ServerRequestInterface $request, IdentityInterface $identity, array $data): bool
    {
        $code = $this->normalizeSubmittedCode((string)($data['code'] ?? ''));
        if ($code === '') {
            return false;
        }

        return $this->storage->verify($this->identityKey($identity), $this->key(), $code);
    }

    /**
     * Deliver a generated OTP to the user (email or SMS).
     */
    abstract protected function deliver(
        ServerRequestInterface $request,
        IdentityInterface $identity,
        string $code,
    ): void;

    /**
     * @param \Authentication\IdentityInterface $identity Identity
     * @return string
     */
    protected function issueCode(IdentityInterface $identity): string
    {
        $length = (int)($this->config['otp']['length'] ?? 6);
        $ttl = (int)($this->config['otp']['ttl'] ?? 600);
        $code = $this->generateNumericCode($length);

        $this->storage->issue($this->identityKey($identity), $this->key(), $code, $ttl);

        return $code;
    }

    /**
     * @param \Authentication\IdentityInterface $identity Identity
     * @return string
     */
    protected function identityKey(IdentityInterface $identity): string
    {
        $field = (string)($this->config['identityField'] ?? 'id');
        $value = $this->readField($identity, $field);
        $key = $value === null ? '' : (string)$value;

        if ($key === '') {
            throw new RuntimeException(__d('verification', 'Identity key is missing for OTP storage.'));
        }

        return $key;
    }

    /**
     * @param int $length Code length
     * @return string
     */
    protected function generateNumericCode(int $length): string
    {
        $length = max(4, min(10, $length));
        $digits = '';
        for ($i = 0; $i < $length; $i++) {
            $digits .= (string)random_int(0, 9);
        }

        return $digits;
    }

    /**
     * @param string $code User input
     * @return string
     */
    protected function normalizeSubmittedCode(string $code): string
    {
        return (string)preg_replace('/\D+/', '', $code);
    }
}
