<?php
declare(strict_types=1);

namespace Salines\Verification\Test\TestSuite\Http;

use Authentication\IdentityInterface;
use Cake\Http\ServerRequest;
use Salines\Verification\Test\TestSuite\Stub\IdentityStub;

trait ServerRequestFactoryTrait
{
    /**
     * @param array<string, mixed> $params
     */
    protected function makeRequestWithIdentity(?IdentityInterface $identity = null, array $params = []): ServerRequest
    {
        $request = new ServerRequest($params);

        if ($identity === null) {
            $identity = new IdentityStub([
                'id' => 1,
                'email' => 'user@example.test',
            ]);
        }

        return $request->withAttribute('identity', $identity);
    }
}
