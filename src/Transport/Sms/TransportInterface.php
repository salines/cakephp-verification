<?php
declare(strict_types=1);

namespace Salines\Verification\Transport\Sms;

interface TransportInterface
{
    /**
     * @param \Verification\Transport\Sms\Message $message Message payload
     * @return \Verification\Transport\Sms\Result
     */
    public function send(Message $message): Result;
}
