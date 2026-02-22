<?php
declare(strict_types=1);

namespace CakeVerification\Test\TestCase\Verificator\Driver;

use Cake\TestSuite\TestCase;
use CakeVerification\Test\TestSuite\Stub\IdentityStub;
use CakeVerification\Verificator\Driver\EmailOtpVerificator;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use function Cake\I18n\__d;

/**
 * @covers \CakeVerification\Verificator\Driver\EmailOtpVerificator
 */
final class EmailOtpVerificatorTest extends TestCase
{
    public function testKeyAndLabelAndRequiresInput(): void
    {
        $v = new EmailOtpVerificator();
        $this->assertSame('email_otp', $v->key());
        $this->assertSame(__d('verification', 'Email One-Time Code'), $v->label());
        $this->assertTrue($v->requiresInput());
    }

    public function testDefaultExpectedFieldsReflectOtpLength(): void
    {
        $v = new EmailOtpVerificator();
        $fields = $v->expectedFields();
        $this->assertArrayHasKey('code', $fields);
        $this->assertStringContainsString('length:6', $fields['code']);
    }

    public function testWithConfigOverridesLengthInExpectedFields(): void
    {
        $v = (new EmailOtpVerificator())->withConfig([
            'otp' => ['length' => 8, 'ttl' => 300],
        ]);

        $this->assertSame(8, $v->getConfig()['otp']['length']);
        $this->assertStringContainsString('length:8', $v->expectedFields()['code']);
    }

    public function testCanStartDependsOnEmailPresence(): void
    {
        $identityNoEmail = new IdentityStub(['email' => '']);
        $identityWithEmail = new IdentityStub(['email' => 'user@example.com']);

        $v = new EmailOtpVerificator();

        $this->assertFalse($v->canStart($identityNoEmail));
        $this->assertTrue($v->canStart($identityWithEmail));
    }

    public function testStartAndVerifyAreToBeImplemented(): void
    {
        $sent = [];
        $v = new EmailOtpVerificator([
            'otp' => ['length' => 6],
            'options' => [
                'ttl' => 300,
                'delivery' => function ($request, $identity, string $code) use (&$sent): void {
                    $sent[] = $code;
                },
            ],
        ]);

        $identity = new IdentityStub([
            'id' => 1,
            'email' => 'user@example.test',
        ]);
        $request = $this->req();

        $v->start($request, $identity);
        $this->assertCount(1, $sent);

        $ok = $v->verify($request, $identity, ['code' => $sent[0]]);
        $this->assertTrue($ok);

        $fail = $v->verify($request, $identity, ['code' => $sent[0]]);
        $this->assertFalse($fail);
    }

    public function testVerifyFailsOnEmptyOrWrongCode(): void
    {
        $sent = [];
        $v = new EmailOtpVerificator([
            'otp' => ['length' => 6],
            'options' => [
                'ttl' => 300,
                'delivery' => function ($request, $identity, string $code) use (&$sent): void {
                    $sent[] = $code;
                },
            ],
        ]);

        $identity = new IdentityStub([
            'id' => 2,
            'email' => 'user@example.test',
        ]);
        $request = $this->req();

        $v->start($request, $identity);
        $this->assertCount(1, $sent);

        $this->assertFalse($v->verify($request, $identity, ['code' => '']));
        $this->assertFalse($v->verify($request, $identity, ['code' => '000000']));
    }

    public function testVerifyFailsWhenExpired(): void
    {
        $sent = [];
        $v = new EmailOtpVerificator([
            'otp' => ['length' => 6],
            'options' => [
                'ttl' => -1,
                'delivery' => function ($request, $identity, string $code) use (&$sent): void {
                    $sent[] = $code;
                },
            ],
        ]);

        $identity = new IdentityStub([
            'id' => 3,
            'email' => 'user@example.test',
        ]);
        $request = $this->req();

        $v->start($request, $identity);
        $this->assertCount(1, $sent);

        $this->assertFalse($v->verify($request, $identity, ['code' => $sent[0]]));
    }

    public function testStartHonorsResendCooldown(): void
    {
        $sent = [];
        $v = new EmailOtpVerificator([
            'storage' => [
                'resendCooldown' => 600,
            ],
            'otp' => ['length' => 6],
            'options' => [
                'ttl' => 300,
                'delivery' => function ($request, $identity, string $code) use (&$sent): void {
                    $sent[] = $code;
                },
            ],
        ]);

        $identity = new IdentityStub([
            'id' => 4,
            'email' => 'user@example.test',
        ]);
        $request = $this->req();

        $v->start($request, $identity);
        $this->assertCount(1, $sent);

        $this->expectException(RuntimeException::class);
        $v->start($request, $identity);
    }

    public function testLockoutAfterMaxAttempts(): void
    {
        $sent = [];
        $v = new EmailOtpVerificator([
            'storage' => [
                'maxAttempts' => 1,
                'lockoutSeconds' => 600,
            ],
            'otp' => ['length' => 6],
            'options' => [
                'ttl' => 300,
                'delivery' => function ($request, $identity, string $code) use (&$sent): void {
                    $sent[] = $code;
                },
            ],
        ]);

        $identity = new IdentityStub([
            'id' => 5,
            'email' => 'user@example.test',
        ]);
        $request = $this->req();

        $v->start($request, $identity);
        $this->assertCount(1, $sent);

        $this->assertFalse($v->verify($request, $identity, ['code' => '000000']));
        $this->assertFalse($v->verify($request, $identity, ['code' => $sent[0]]));
    }

    public function testStartFailsWhenIdentityKeyMissing(): void
    {
        $v = new EmailOtpVerificator([
            'otp' => ['length' => 6],
            'options' => [
                'ttl' => 300,
                'delivery' => function ($request, $identity, string $code): void {
                },
            ],
        ]);

        $identity = new IdentityStub([
            'email' => 'user@example.test',
        ]);
        $request = $this->req();

        $this->expectException(RuntimeException::class);
        $v->start($request, $identity);
    }

    private function req(): ServerRequestInterface
    {
        return $this->createStub(ServerRequestInterface::class);
    }
}
