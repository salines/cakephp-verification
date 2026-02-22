<?php
declare(strict_types=1);

namespace Salines\Verification\Test\TestSuite\Stub;

use Salines\Verification\Transport\Sms\Driver\DummyTransport;
use Salines\Verification\Transport\Sms\Message;
use Salines\Verification\Transport\Sms\Result;

final class CaptureTransport extends DummyTransport
{
    /**
     * @var array<int, \Verification\Transport\Sms\Message>
     */
    public array $sent = [];

    public function send(Message $message): Result
    {
        $this->sent[] = $message;

        return parent::send($message);
    }
}
