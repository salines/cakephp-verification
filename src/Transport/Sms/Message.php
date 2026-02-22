<?php
declare(strict_types=1);

namespace CakeVerification\Transport\Sms;

class Message
{
    public string $recipient;
    public string $body;
    public ?string $sender = null;

    /**
     * @param string $recipient Recipient phone number
     * @param string $body Message body
     * @param string|null $sender Sender id
     */
    public function __construct(string $recipient, string $body, ?string $sender = null)
    {
        $this->recipient = $recipient;
        $this->body = $body;
        $this->sender = $sender;
    }
}
