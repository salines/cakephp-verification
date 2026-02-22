<?php
declare(strict_types=1);

namespace Salines\Verification\Test\TestCase\Transport\Sms\Driver;

use PHPUnit\Framework\TestCase;
use Salines\Verification\Transport\Sms\Driver\DummyTransport;
use Salines\Verification\Transport\Sms\Message;

final class DummyTransportTest extends TestCase
{
    public function testSendReturnsSuccess(): void
    {
        $transport = new DummyTransport();
        $msg = new Message('+385911234567', 'Test OTP 123456');

        $result = $transport->send($msg);

        $this->assertTrue($result->success, 'Dummy transport should always succeed in v1.');
        $this->assertNull($result->error ?? null);
    }
}
