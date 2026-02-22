<?php
declare(strict_types=1);

namespace Salines\Verification\Verificator\Driver;

use Authentication\IdentityInterface;
use Psr\Http\Message\ServerRequestInterface;
use Salines\Verification\Transport\Sms\Message;
use Salines\Verification\Transport\Sms\TransportInterface;
use function Cake\I18n\__d;

/**
 * SMS OTP verification step.
 *
 * Responsibilities:
 * - Generate and deliver a short-lived OTP via SMS transport (start()).
 * - Validate user-provided code in verify().
 *
 * Config (array):
 * - 'fields.phone'   : string Field name on identity (default: 'phone')
 * - 'otp.length'     : int    Digits length (default: 6)
 * - 'otp.ttl'        : int    Seconds time-to-live (default: 300)
 * - 'from'           : string|null Sender id/number if transport supports it
 * - 'normalizeE164'  : bool   Normalize phone to E.164 when possible (default: false)
 * - 'defaultCountryCode' : string|null Default country code for E.164
 */
final class SmsOtpVerificator extends AbstractOtpVerificator
{
    /**
     * @param \Salines\Verification\Transport\Sms\TransportInterface $transport SMS transport
     * @param array<string, mixed> $config Config
     */
    public function __construct(
        private TransportInterface $transport,
        array $config = [],
    ) {
        $options = (array)($config['options'] ?? []);
        $otp = (array)($config['otp'] ?? []);
        $normalized = $config;
        $normalized['fields'] = $config['fields'] ?? [
            'phone' => 'phone',
            'phoneVerifiedAt' => 'phone_verified_at',
        ];
        $normalized['otp'] = [
            'length' => (int)($otp['length'] ?? 6),
            'ttl' => (int)($otp['ttl'] ?? $options['ttl'] ?? 300),
        ];
        $normalized['from'] = $options['senderId'] ?? null;
        $normalized['messageTemplate'] = $options['messageTemplate'] ?? 'Your verification code is {code}.';
        $normalized['normalizeE164'] = filter_var(
            $options['normalizeE164'] ?? false,
            FILTER_VALIDATE_BOOLEAN,
        );
        $normalized['defaultCountryCode'] = $options['defaultCountryCode'] ?? null;

        parent::__construct($normalized);
    }

    /**
     * @inheritDoc
     */
    public function key(): string
    {
        return 'sms_otp';
    }

    /**
     * @inheritDoc
     */
    public function label(): string
    {
        return __d('verification', 'SMS One-Time Code');
    }

    /**
     * @inheritDoc
     */
    public function canStart(IdentityInterface $identity): bool
    {
        $phoneField = $this->config['fields']['phone'] ?? 'phone';

        return (string)$this->readField($identity, (string)$phoneField) !== '';
    }

    /**
     * @inheritDoc
     */
    public function isVerified(IdentityInterface $identity): bool
    {
        $verifiedField = (string)($this->config['fields']['phoneVerifiedAt'] ?? 'phone_verified_at');
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
        $phoneField = $this->config['fields']['phone'] ?? 'phone';
        $to = (string)$this->readField($identity, $phoneField);
        if ($to === '') {
            return;
        }
        if (!empty($this->config['normalizeE164'])) {
            $to = $this->normalizeE164($to, $this->config['defaultCountryCode'] ?? null);
        }
        if ($to === '') {
            return;
        }

        $template = (string)($this->config['messageTemplate'] ?? 'Your verification code is {code}.');
        $ttl = (int)($this->config['otp']['ttl'] ?? 300);
        $body = str_replace(
            ['{code}', '{ttl}'],
            [$code, (string)ceil($ttl / 60)],
            $template,
        );

        $message = new Message($to, $body, $this->config['from'] ?? null);
        $this->transport->send($message);
    }

    /**
     * @param string $value Phone number
     * @param string|null $defaultCountryCode Default country code
     * @return string
     */
    private function normalizeE164(string $value, ?string $defaultCountryCode): string
    {
        $value = trim($value);
        if ($value === '') {
            return '';
        }

        $hasPlus = str_starts_with($value, '+');
        $digits = (string)preg_replace('/\D+/', '', $value);
        if ($digits === '') {
            return '';
        }

        if ($hasPlus) {
            return '+' . $digits;
        }

        if (str_starts_with($value, '00')) {
            $digits = substr($digits, 2);
            if ($digits === '') {
                return '';
            }

            return '+' . $digits;
        }

        $country = $defaultCountryCode !== null ? (string)preg_replace('/\D+/', '', $defaultCountryCode) : '';
        if ($country !== '') {
            return '+' . $country . $digits;
        }

        return $digits;
    }
}
