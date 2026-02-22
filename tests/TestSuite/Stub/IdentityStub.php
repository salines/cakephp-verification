<?php
declare(strict_types=1);

namespace Verification\Test\TestSuite\Stub;

use Authentication\IdentityInterface;

final class IdentityStub implements IdentityInterface
{
    /**
     * @param array<string, mixed> $data
     */
    public function __construct(private array $data = [])
    {
    }

    /**
     * @return array<string, mixed>|string|int|null
     */
    public function getIdentifier(): array|string|int|null
    {
        return $this->data['id'] ?? null;
    }

    /**
     * @return array<string, mixed>
     */
    public function getOriginalData(): array
    {
        return $this->data;
    }

    public function get(string $field): mixed
    {
        return $this->data[$field] ?? null;
    }

    public function set(string $field, mixed $value): void
    {
        $this->data[$field] = $value;
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists((string)$offset, $this->data);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->data[(string)$offset] ?? null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->data[(string)$offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[(string)$offset]);
    }
}
