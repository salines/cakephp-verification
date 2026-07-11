# VerificationVerificatorInterface

Reference for implementing a custom verification step (driver).

The plugin ships four built-in drivers: `emailVerify`, `emailOtp`, `smsOtp`, `totp`.
If you need a custom step (e.g. backup codes, hardware key), implement this interface.

---

## Interface

```php
namespace CakeVerification\Verificator;

use Authentication\IdentityInterface;
use Psr\Http\Message\ServerRequestInterface;

interface VerificationVerificatorInterface
{
    public function key(): string;
    public function label(): string;
    public function withConfig(array $config): static;
    public function getConfig(): array;
    public function canStart(IdentityInterface $identity): bool;
    public function start(ServerRequestInterface $request, IdentityInterface $identity): void;
    public function requiresInput(): bool;
    public function expectedFields(): array;
    public function verify(ServerRequestInterface $request, IdentityInterface $identity, array $data): bool;
    public function isVerified(IdentityInterface $identity): bool;
}
```

## Methods

| Method | Description |
|---|---|
| `key(): string` | Unique snake_case step identifier (e.g. `'backup_code'`) |
| `label(): string` | Human-readable label shown in the choose-verification screen |
| `withConfig(array $config): static` | Return a new instance with merged config overrides |
| `getConfig(): array` | Return the current config array |
| `canStart(IdentityInterface $identity): bool` | Return `true` if prerequisites exist (e.g. user has a phone number) |
| `start(ServerRequestInterface $request, IdentityInterface $identity): void` | Issue the OTP / trigger delivery |
| `requiresInput(): bool` | Return `true` if the user must submit a code; `false` for link-based flows |
| `expectedFields(): array` | Describe expected input fields (`['code' => 'string']`) |
| `verify(ServerRequestInterface $request, IdentityInterface $identity, array $data): bool` | Verify submitted data; return `true` on success |
| `isVerified(IdentityInterface $identity): bool` | Return `true` if the identity has already completed this step |

---

## Minimal example

```php
namespace App\Verification;

use Authentication\IdentityInterface;
use Psr\Http\Message\ServerRequestInterface;
use CakeVerification\Verificator\VerificationVerificatorInterface;

class BackupCodeVerificator implements VerificationVerificatorInterface
{
    private array $config = [];

    public function key(): string { return 'backup_code'; }
    public function label(): string { return 'Backup code'; }

    public function withConfig(array $config): static
    {
        $clone = clone $this;
        $clone->config = array_merge($this->config, $config);
        return $clone;
    }

    public function getConfig(): array { return $this->config; }

    public function canStart(IdentityInterface $identity): bool
    {
        return !empty($identity->getOriginalData()->backup_codes);
    }

    public function start(ServerRequestInterface $request, IdentityInterface $identity): void
    {
        // nothing to send — user already has their codes
    }

    public function requiresInput(): bool { return true; }
    public function expectedFields(): array { return ['code' => 'string']; }

    public function verify(ServerRequestInterface $request, IdentityInterface $identity, array $data): bool
    {
        $submitted = $data['code'] ?? '';
        // validate $submitted against stored backup codes ...
        return true;
    }

    public function isVerified(IdentityInterface $identity): bool
    {
        return (bool)$identity->getOriginalData()->backup_code_verified_at;
    }
}
```

---

## Documentation

Full documentation index: [../index.md](../index.md)
