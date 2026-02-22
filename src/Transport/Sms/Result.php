<?php
declare(strict_types=1);

namespace Verification\Transport\Sms;

class Result
{
    public bool $success = true;
    public ?string $error = null;
    public ?Message $message = null;
    public ?string $providerId = null;
    public ?int $statusCode = null;
    public ?int $retryAfter = null;
}
