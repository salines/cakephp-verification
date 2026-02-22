<?php
declare(strict_types=1);

namespace CakeVerification\Test\TestCase\Transport\Sms;

use CakeVerification\Test\TestSuite\Stub\ConfigurableTransport;
use CakeVerification\Transport\Sms\Driver\DummyTransport;
use CakeVerification\Transport\Sms\TransportFactory;
use CakeVerification\Transport\Sms\TransportInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class TransportFactoryTest extends TestCase
{
    public function testCreateDummy(): void
    {
        $transport = TransportFactory::create('dummy');
        $this->assertInstanceOf(DummyTransport::class, $transport);
        $this->assertInstanceOf(TransportInterface::class, $transport);
    }

    public function testUnknownTransportThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TransportFactory::create('nope');
    }

    public function testCreateCustomTransportWithClassName(): void
    {
        $transport = TransportFactory::create('custom', [
            'custom' => ['className' => DummyTransport::class],
        ]);

        $this->assertInstanceOf(DummyTransport::class, $transport);
    }

    public function testCreateCustomTransportPassesOptions(): void
    {
        $transport = TransportFactory::create('custom', [
            'custom' => [
                'className' => ConfigurableTransport::class,
                'options' => ['apiKey' => 'test-key'],
            ],
        ]);

        $this->assertInstanceOf(ConfigurableTransport::class, $transport);
        $this->assertSame('test-key', $transport->options['apiKey']);
    }

    public function testCreateThrowsForInvalidClassName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TransportFactory::create('custom', [
            'custom' => ['className' => 'NonExistentClass'],
        ]);
    }

    public function testCreateThrowsForMissingClassName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TransportFactory::create('custom', [
            'custom' => ['options' => []],
        ]);
    }
}
