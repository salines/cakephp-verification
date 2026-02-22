<?php
declare(strict_types=1);

namespace CakeVerification\Transport\Sms;

use CakeVerification\Transport\Sms\Driver\DummyTransport;
use InvalidArgumentException;
use ReflectionClass;
use function Cake\I18n\__d;

class TransportFactory
{
    /**
     * @param array<string, mixed> $transports
     */
    public static function create(string $name, array $transports = []): TransportInterface
    {
        if ($name === 'dummy') {
            return new DummyTransport();
        }

        $definition = $transports[$name] ?? null;
        if (is_array($definition)) {
            $class = $definition['className'] ?? null;
            if (!is_string($class) || $class === '') {
                throw new InvalidArgumentException(__d('verification', 'Invalid transport definition: {0}', $name));
            }
            if (!class_exists($class) || !is_subclass_of($class, TransportInterface::class)) {
                throw new InvalidArgumentException(__d('verification', 'Invalid transport class: {0}', $class));
            }

            $options = $definition['options'] ?? [];
            if (!is_array($options)) {
                $options = [];
            }

            $ref = new ReflectionClass($class);
            $ctor = $ref->getConstructor();
            if ($ctor === null || $ctor->getNumberOfRequiredParameters() === 0) {
                return new $class();
            }

            return new $class($options);
        }

        throw new InvalidArgumentException(__d('verification', 'Unknown transport: {0}', $name));
    }
}
