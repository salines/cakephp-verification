<?php
declare(strict_types=1);

namespace Verification\Verificator\Driver;

use Authentication\IdentityInterface;
use Psr\Http\Message\ServerRequestInterface;
use Verification\Verificator\VerificationVerificatorInterface;
use function Cake\I18n\__d;

/**
 * TOTP verification step (e.g., Google Authenticator).
 *
 * Responsibilities:
 * - If not enrolled, redirect user to enrollment UI (QR code) handled by app/controller.
 * - If enrolled, validate a time-based one-time code.
 *
 * Config (array):
 * - 'fields.totpSecret' : string Field name on identity (default: 'totp_secret')
 * - 'window'             : int    Step window (default: 30s)
 * - 'digits'             : int    Digits (default: 6)
 * - 'algo'               : string Algorithm (default: 'sha1')
 * - 'drift'              : int    Allowed past/future steps (default: 1)
 * - 'throttle.max'       : int    Max attempts per window (default: 5)
 * - 'throttle.window'    : int    Seconds window (default: 300)
 */
final class TotpVerificator implements VerificationVerificatorInterface
{
    use ReadFieldTrait;

    /**
     * @var array<string,mixed>
     */
    private array $config;

    /**
     * @param array<string,mixed> $config
     */
    public function __construct(array $config = [])
    {
        $options = (array)($config['options'] ?? []);
        $this->config = $config + [
            'fields' => [
                'totpSecret' => 'totp_secret',
                'totpVerified' => 'totp_verified_at',
            ],
            'period' => (int)($options['period'] ?? $config['period'] ?? 30),
            'digits' => (int)($options['digits'] ?? $config['digits'] ?? 6),
            'algo' => (string)($options['algorithm'] ?? $options['algo'] ?? $config['algo'] ?? 'sha1'),
            'drift' => (int)($options['drift'] ?? $options['window'] ?? $config['drift'] ?? 1),
            'now' => $options['now'] ?? null,
        ];
    }

    /**
     * @inheritDoc
     */
    public function key(): string
    {
        return 'totp';
    }

    /**
     * @inheritDoc
     */
    public function label(): string
    {
        return __d('verification', 'Authenticator App (TOTP)');
    }

    /**
     * @inheritDoc
     */
    public function withConfig(array $config): static
    {
        $clone = clone $this;
        $clone->config = $config + $this->config;

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
    public function canStart(IdentityInterface $identity): bool
    {
        $field = $this->config['fields']['totpSecret'] ?? 'totp_secret';

        return (string)$this->readField($identity, (string)$field) !== '';
    }

    /**
     * @inheritDoc
     */
    public function start(ServerRequestInterface $request, IdentityInterface $identity): void
    {
        // TOTP has no out-of-band delivery step.
        // If not enrolled, VerificationComponent routes to the enroll action.
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
        return ['code' => 'string|digits|length:' . (int)$this->config['digits']];
    }

    /**
     * @inheritDoc
     */
    public function verify(ServerRequestInterface $request, IdentityInterface $identity, array $data): bool
    {
        $secretField = (string)($this->config['fields']['totpSecret'] ?? 'totp_secret');
        $secret = (string)$this->readField($identity, $secretField);
        $code = trim((string)($data['code'] ?? ''));
        if ($secret === '' || $code === '') {
            return false;
        }

        $digits = (int)$this->config['digits'];
        if (strlen($code) !== $digits || !ctype_digit($code)) {
            return false;
        }

        $period = (int)$this->config['period'];
        $drift = (int)$this->config['drift'];
        $algo = strtolower((string)$this->config['algo']);
        $now = $this->config['now'];
        $timestamp = is_int($now) ? $now : time();

        return $this->verifyTotp($secret, $code, $timestamp, $period, $digits, $algo, $drift);
    }

    /**
     * @inheritDoc
     */
    public function isVerified(IdentityInterface $identity): bool
    {
        $verifiedField = $this->config['fields']['totpVerified'] ?? null;
        if (is_string($verifiedField) && $verifiedField !== '') {
            $val = $this->readField($identity, $verifiedField);

            return $val !== '' && $val !== null && $val !== false;
        }

        $secretField = (string)($this->config['fields']['totpSecret'] ?? 'totp_secret');

        return (string)$this->readField($identity, $secretField) !== '';
    }

    /**
     * @param string $secret Base32 encoded secret
     * @param string $code User-provided code
     * @param int $timestamp Unix time
     * @param int $period Time step in seconds
     * @param int $digits Code length
     * @param string $algo HMAC algorithm
     * @param int $drift Allowed past/future steps
     * @return bool
     */
    private function verifyTotp(
        string $secret,
        string $code,
        int $timestamp,
        int $period,
        int $digits,
        string $algo,
        int $drift,
    ): bool {
        $secret = $this->normalizeSecret($secret);
        $key = $this->base32Decode($secret);
        if ($key === '') {
            return false;
        }

        $counter = (int)floor($timestamp / max(1, $period));
        for ($offset = -$drift; $offset <= $drift; $offset++) {
            $hotp = $this->hotp($key, $counter + $offset, $digits, $algo);
            if ($hotp !== '' && hash_equals($hotp, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string $secret Base32 secret
     * @return string
     */
    private function normalizeSecret(string $secret): string
    {
        $secret = strtoupper($secret);
        $secret = str_replace([' ', '-'], '', $secret);

        return $secret;
    }

    /**
     * @param string $secret Base32 secret
     * @return string Raw binary key
     */
    private function base32Decode(string $secret): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = rtrim($secret, '=');
        $bits = '';
        foreach (str_split($secret) as $char) {
            $pos = strpos($alphabet, $char);
            if ($pos === false) {
                return '';
            }
            $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) < 8) {
                continue;
            }
            $bytes .= chr((int)bindec($byte));
        }

        return $bytes;
    }

    /**
     * @param string $key Raw key
     * @param int $counter Counter
     * @param int $digits Code length
     * @param string $algo HMAC algorithm
     * @return string
     */
    private function hotp(string $key, int $counter, int $digits, string $algo): string
    {
        $algo = in_array($algo, ['sha1', 'sha256', 'sha512'], true) ? $algo : 'sha1';
        $binaryCounter = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac($algo, $binaryCounter, $key, true);
        $offset = ord(substr($hash, -1)) & 0x0f;
        $part = substr($hash, $offset, 4);
        if (strlen($part) !== 4) {
            return '';
        }
        $unpacked = unpack('N', $part);
        if (!is_array($unpacked) || !isset($unpacked[1])) {
            return '';
        }
        $value = $unpacked[1] & 0x7fffffff;
        $mod = 10 ** $digits;
        $otp = (string)($value % $mod);

        return str_pad($otp, $digits, '0', STR_PAD_LEFT);
    }
}
