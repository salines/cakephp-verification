<?php
declare(strict_types=1);

namespace CakeVerification\Test\TestSuite\Stub;

use CakeVerification\Transport\Sms\Driver\DummyTransport;

/**
 * Transport stub that accepts an $options array in its constructor,
 * used for testing TransportFactory's className-based instantiation path.
 */
final class ConfigurableTransport extends DummyTransport
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(public readonly array $options)
    {
    }
}
