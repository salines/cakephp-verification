<?php
declare(strict_types=1);

namespace CakeVerification\Transport\Sms;

interface TransportInterface
{
    /**
     * @param \CakeVerification\Transport\Sms\Message $message Message payload
     * @return \CakeVerification\Transport\Sms\Result
     */
    public function send(Message $message): Result;
}
