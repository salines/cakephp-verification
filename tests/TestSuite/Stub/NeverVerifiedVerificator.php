<?php
declare(strict_types=1);

namespace Salines\Verification\Test\TestSuite\Stub;

use Authentication\IdentityInterface;
use Psr\Http\Message\ServerRequestInterface;
use Salines\Verification\Verificator\VerificationVerificatorInterface;

final class NeverVerifiedVerificator implements VerificationVerificatorInterface
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
        $this->config = $config;
    }

    public function key(): string
    {
        return 'never';
    }

    public function label(): string
    {
        return 'Never Verified';
    }

    public function withConfig(array $config): static
    {
        $clone = clone $this;
        $clone->config = $config + $this->config;

        return $clone;
    }

    public function getConfig(): array
    {
        return $this->config;
    }

    public function canStart(IdentityInterface $identity): bool
    {
        return true;
    }

    public function start(ServerRequestInterface $request, IdentityInterface $identity): void
    {
    }

    public function requiresInput(): bool
    {
        return false;
    }

    public function expectedFields(): array
    {
        return [];
    }

    public function verify(ServerRequestInterface $request, IdentityInterface $identity, array $data): bool
    {
        return $this->isVerified($identity);
    }

    public function isVerified(IdentityInterface $identity): bool
    {
        return false;
    }
}
