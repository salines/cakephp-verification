<?php
declare(strict_types=1);

namespace Verification\Verificator\Driver;

use Authentication\IdentityInterface;
use Psr\Http\Message\ServerRequestInterface;
use Verification\Verificator\VerificationVerificatorInterface;
use function Cake\I18n\__d;

final class EmailVerifyVerificator implements VerificationVerificatorInterface
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
                'email' => 'email',
                'emailVerified' => 'email_verified_at',
            ],
            'delivery' => $options['delivery'] ?? null,
        ];
    }

    /**
     * @inheritDoc
     */
    public function key(): string
    {
        return 'email_verify';
    }

    /**
     * @inheritDoc
     */
    public function label(): string
    {
        return __d('verification', 'Email Verification');
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
        $emailField = $this->config['fields']['email'] ?? 'email';

        return (string)$this->readField($identity, (string)$emailField) !== '';
    }

    /**
     * @inheritDoc
     */
    public function start(ServerRequestInterface $request, IdentityInterface $identity): void
    {
        $delivery = $this->config['delivery'] ?? null;
        if (is_callable($delivery)) {
            $delivery($request, $identity, $this->config);
        }
    }

    /**
     * @inheritDoc
     */
    public function requiresInput(): bool
    {
        return false;
    }

    /**
     * @inheritDoc
     */
    public function expectedFields(): array
    {
        return [];
    }

    /**
     * @inheritDoc
     */
    public function verify(ServerRequestInterface $request, IdentityInterface $identity, array $data): bool
    {
        // Link-based verification is handled by the app.
        return $this->isVerified($identity);
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
}
