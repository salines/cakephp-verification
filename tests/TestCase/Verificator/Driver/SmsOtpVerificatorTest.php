<?php
declare(strict_types=1);

namespace Salines\Verification\Test\TestCase\Verificator\Driver;

use Cake\TestSuite\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Salines\Verification\Test\TestSuite\Stub\CaptureTransport;
use Salines\Verification\Test\TestSuite\Stub\IdentityStub;
use Salines\Verification\Transport\Sms\TransportInterface;
use Salines\Verification\Verificator\Driver\SmsOtpVerificator;
use function Cake\I18n\__d;

/**
 * @covers \Verification\Verificator\Driver\SmsOtpVerificator
 */
final class SmsOtpVerificatorTest extends TestCase
{
    public function testKeyLabelRequiresInput(): void
    {
        $transport = $this->createStub(TransportInterface::class);
        $v = new SmsOtpVerificator($transport);

        $this->assertSame('sms_otp', $v->key());
        $this->assertSame(__d('verification', 'SMS One-Time Code'), $v->label());
        $this->assertTrue($v->requiresInput());
    }

    public function testExpectedFieldsReflectOtpLength(): void
    {
        $transport = $this->createStub(TransportInterface::class);
        $v = (new SmsOtpVerificator($transport))->withConfig([
            'otp' => ['length' => 7, 'ttl' => 180],
        ]);

        $this->assertSame(7, $v->getConfig()['otp']['length']);
        $this->assertStringContainsString('length:7', $v->expectedFields()['code']);
    }

    public function testCanStartDependsOnPhonePresence(): void
    {
        $identityNoPhone = new IdentityStub(['phone' => '']);
        $identityWithPhone = new IdentityStub(['phone' => '+38761111222']);

        $transport = $this->createStub(TransportInterface::class);
        $v = new SmsOtpVerificator($transport);

        $this->assertFalse($v->canStart($identityNoPhone));
        $this->assertTrue($v->canStart($identityWithPhone));
    }

    public function testStartAndVerifyAreToBeImplemented(): void
    {
        $transport = new CaptureTransport();

        $v = new SmsOtpVerificator($transport, [
            'otp' => ['length' => 6],
            'options' => [
                'ttl' => 300,
                'messageTemplate' => 'Your verification code is {code}.',
            ],
        ]);

        $identity = new IdentityStub([
            'id' => 10,
            'phone' => '+38599111222',
        ]);
        $request = $this->req();

        $v->start($request, $identity);
        $this->assertCount(1, $transport->sent);
        $this->assertSame('+38599111222', $transport->sent[0]->recipient);

        $digits = (string)preg_replace('/\D+/', '', $transport->sent[0]->body);
        if (strlen($digits) !== 6) {
            $this->fail('SMS body does not contain a 6 digit code.');
        }
        $code = $digits;

        $this->assertTrue($v->verify($request, $identity, ['code' => $code]));
        $this->assertFalse($v->verify($request, $identity, ['code' => $code]));
    }

    public function testIsVerifiedFalseWhenNoPhoneVerifiedAt(): void
    {
        $transport = $this->createStub(TransportInterface::class);
        $v = new SmsOtpVerificator($transport);

        $identity = new IdentityStub(['phone_verified_at' => null]);
        $this->assertFalse($v->isVerified($identity));
    }

    public function testIsVerifiedTrueWhenPhoneVerifiedAtSet(): void
    {
        $transport = $this->createStub(TransportInterface::class);
        $v = new SmsOtpVerificator($transport);

        $identity = new IdentityStub(['phone_verified_at' => '2024-01-01 12:00:00']);
        $this->assertTrue($v->isVerified($identity));
    }

    public function testLockoutAfterMaxAttempts(): void
    {
        $transport = new CaptureTransport();
        $v = new SmsOtpVerificator($transport, [
            'storage' => [
                'maxAttempts' => 1,
                'lockoutSeconds' => 600,
            ],
            'otp' => ['length' => 6],
            'options' => ['ttl' => 300],
        ]);

        $identity = new IdentityStub(['id' => 20, 'phone' => '+38599000111']);
        $request = $this->req();

        $v->start($request, $identity);
        $code = (string)preg_replace('/\D+/', '', $transport->sent[0]->body);

        $this->assertFalse($v->verify($request, $identity, ['code' => '000000']));
        // Locked — correct code is also rejected
        $this->assertFalse($v->verify($request, $identity, ['code' => $code]));
    }

    public function testVerifyFailsOnEmptyCode(): void
    {
        $transport = $this->createStub(TransportInterface::class);
        $v = new SmsOtpVerificator($transport);

        $identity = new IdentityStub(['id' => 21, 'phone' => '+38599000222']);
        $request = $this->req();

        $this->assertFalse($v->verify($request, $identity, ['code' => '']));
    }

    private function req(): ServerRequestInterface
    {
        return $this->createStub(ServerRequestInterface::class);
    }
}
