<?php
declare(strict_types=1);

namespace Salines\Verification\Verificator\Driver;

use Authentication\IdentityInterface;
use Psr\Http\Message\ServerRequestInterface;
use function Cake\I18n\__d;

/**
 * Email OTP verification step.
 *
 * Responsibilities:
 * - Generate and deliver a short-lived OTP to user's email (done in start()).
 * - Validate user-provided code in verify().
 *
 * Config (array):
 * - 'fields.email'   : string Field name on identity (default: 'email')
 * - 'otp.length'     : int    Digits length (default: 6)
 * - 'otp.ttl'        : int    Seconds time-to-live (default: 300)
 * - 'delivery'       : callable(Request, Identity, code): void  Userland email sender
 */
final class EmailOtpVerificator extends AbstractOtpVerificator
{
    /**
     * @param array<string,mixed> $config
     */
    public function __construct(array $config = [])
    {
        $options = (array)($config['options'] ?? []);
        $otp = (array)($config['otp'] ?? []);
        $normalized = $config;
        $normalized['fields'] = $config['fields'] ?? [
            'email' => 'email',
            'emailVerified' => 'email_verified_at',
        ];
        $normalized['otp'] = [
            'length' => (int)($otp['length'] ?? 6),
            'ttl' => (int)($otp['ttl'] ?? $options['ttl'] ?? 300),
        ];
        $normalized['delivery'] = $options['delivery'] ?? null;

        parent::__construct($normalized);
    }

    /**
     * @inheritDoc
     */
    public function key(): string
    {
        return 'email_otp';
    }

    /**
     * @inheritDoc
     */
    public function label(): string
    {
        return __d('verification', 'Email One-Time Code');
    }

    /**
     * @inheritDoc
     */
    public function canStart(IdentityInterface $identity): bool
    {
        $emailField = $this->config['fields']['email'] ?? 'email';

        return (string)$this->readField($identity, (string)$emailField) !== '';
    }

    /**
     * @inheritDoc
     */
    public function isVerified(IdentityInterface $identity): bool
    {
        $verifiedField = (string)($this->config['fields']['emailVerified'] ?? 'email_verified_at');
        $val = $this->readField($identity, $verifiedField);

        return $val !== '' && $val !== null && $val !== false;
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface $request Request
     * @param \Authentication\IdentityInterface $identity Identity
     * @param string $code OTP code
     * @return void
     */
    protected function deliver(ServerRequestInterface $request, IdentityInterface $identity, string $code): void
    {
        $delivery = $this->config['delivery'] ?? null;
        if (is_callable($delivery)) {
            $delivery($request, $identity, $code, $this->config);
        }
    }
}
