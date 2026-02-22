<?php
declare(strict_types=1);

namespace CakeVerification\Transport\Sms\Driver;

use Cake\Cache\Cache;
use Cake\Log\Log;
use CakeVerification\Transport\Sms\Message;
use CakeVerification\Transport\Sms\Result;
use CakeVerification\Transport\Sms\TransportInterface;
use function Cake\I18n\__d;

class DummyTransport implements TransportInterface
{
    /**
     * @inheritDoc
     */
    public function send(Message $message): Result
    {
        $result = new Result();
        $result->success = true;
        $result->message = $message;

        if (!Cache::getConfig('default')) {
            Cache::setConfig('default', [
                'className' => 'Array',
                'prefix' => 'verification_',
                'duration' => '+1 day',
            ]);
        }
        Cache::write('verification_last_sms', $message, 'default');
        Log::info(
            __d('verification', 'Dummy SMS sent to {0}', $message->recipient),
            ['scope' => ['verification']],
        );

        return $result;
    }
}
