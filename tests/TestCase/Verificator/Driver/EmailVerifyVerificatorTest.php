<?php
declare(strict_types=1);

namespace Verification\Test\TestCase\Verificator\Driver;

use Cake\TestSuite\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Verification\Test\TestSuite\Stub\IdentityStub;
use Verification\Verificator\Driver\EmailVerifyVerificator;
use function Cake\I18n\__d;

/**
 * @covers \Verification\Verificator\Driver\EmailVerifyVerificator
 */
final class EmailVerifyVerificatorTest extends TestCase
{
    public function testKeyLabelAndRequiresInput(): void
    {
        $v = new EmailVerifyVerificator();
        $this->assertSame('email_verify', $v->key());
        $this->assertSame(__d('verification', 'Email Verification'), $v->label());
        $this->assertFalse($v->requiresInput());
        $this->assertSame([], $v->expectedFields());
    }

    public function testCanStartDependsOnEmailPresence(): void
    {
        $identityNoEmail = new IdentityStub(['email' => '']);
        $identityWithEmail = new IdentityStub(['email' => 'user@example.test']);

        $v = new EmailVerifyVerificator();

        $this->assertFalse($v->canStart($identityNoEmail));
        $this->assertTrue($v->canStart($identityWithEmail));
    }

    public function testIsVerifiedUsesEmailVerifiedAt(): void
    {
        $identityNoVerified = new IdentityStub(['email_verified_at' => null]);
        $identityVerified = new IdentityStub(['email_verified_at' => '2024-01-01 12:00:00']);

        $v = new EmailVerifyVerificator();

        $this->assertFalse($v->isVerified($identityNoVerified));
        $this->assertTrue($v->isVerified($identityVerified));
    }

    public function testStartCallsDeliveryCallable(): void
    {
        $called = [];
        $v = new EmailVerifyVerificator([
            'options' => [
                'delivery' => function ($req, $identity, $config) use (&$called): void {
                    $called[] = $identity->get('email');
                },
            ],
        ]);

        $identity = new IdentityStub(['email' => 'user@example.test']);
        $request = $this->createStub(ServerRequestInterface::class);

        $v->start($request, $identity);

        $this->assertSame(['user@example.test'], $called);
    }

    public function testStartWithNoDeliveryIsNoOp(): void
    {
        $v = new EmailVerifyVerificator();
        $identity = new IdentityStub(['email' => 'user@example.test']);
        $request = $this->createStub(ServerRequestInterface::class);

        $v->start($request, $identity);
        $this->assertTrue(true);
    }

    public function testWithConfigAndGetConfig(): void
    {
        $v = new EmailVerifyVerificator();
        $clone = $v->withConfig(['fields' => ['email' => 'email_address']]);

        $this->assertNotSame($v, $clone);
        $this->assertSame('email_address', $clone->getConfig()['fields']['email']);
        // Original unchanged
        $this->assertSame('email', $v->getConfig()['fields']['email']);
    }

    public function testVerifyDelegatesToIsVerified(): void
    {
        $v = new EmailVerifyVerificator();
        $request = $this->createStub(ServerRequestInterface::class);

        $notVerified = new IdentityStub(['email_verified_at' => null]);
        $verified = new IdentityStub(['email_verified_at' => '2024-01-01 12:00:00']);

        $this->assertFalse($v->verify($request, $notVerified, []));
        $this->assertTrue($v->verify($request, $verified, []));
    }
}
