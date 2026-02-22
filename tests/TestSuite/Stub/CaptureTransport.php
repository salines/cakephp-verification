<?php
declare(strict_types=1);

namespace CakeVerification\Test\TestSuite\Stub;

use CakeVerification\Transport\Sms\Driver\DummyTransport;
use CakeVerification\Transport\Sms\Message;
use CakeVerification\Transport\Sms\Result;

final class CaptureTransport extends DummyTransport
{
    /**
     * @var array<int, \CakeVerification\Transport\Sms\Message>
     */
    public array $sent = [];

    public function send(Message $message): Result
    {
        $this->sent[] = $message;

        return parent::send($message);
    }
}
