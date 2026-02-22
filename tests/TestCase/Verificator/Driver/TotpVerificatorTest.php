<?php
declare(strict_types=1);

namespace CakeVerification\Test\TestCase\Verificator\Driver;

use Cake\TestSuite\TestCase;
use CakeVerification\Test\TestSuite\Stub\IdentityStub;
use CakeVerification\Verificator\Driver\TotpVerificator;
use Psr\Http\Message\ServerRequestInterface;
use function Cake\I18n\__d;

/**
 * @covers \CakeVerification\Verificator\Driver\TotpVerificator
 */
final class TotpVerificatorTest extends TestCase
{
    public function testKeyLabelRequiresInput(): void
    {
        $v = new TotpVerificator();
        $this->assertSame('totp', $v->key());
        $this->assertSame(__d('verification', 'Authenticator App (TOTP)'), $v->label());
        $this->assertTrue($v->requiresInput());
    }

    public function testExpectedFieldsReflectDigitsConfig(): void
    {
        $v = (new TotpVerificator())->withConfig([
            'digits' => 8,
        ]);

        $this->assertSame(8, $v->getConfig()['digits']);
        $this->assertStringContainsString('length:8', $v->expectedFields()['code']);
    }

    public function testCanStartRequiresSecret(): void
    {
        $identityNoSecret = new IdentityStub(['totp_secret' => '']);
        $identityWithSecret = new IdentityStub(['totp_secret' => 'BASE32SECRET']);

        $v = new TotpVerificator();

        $this->assertFalse($v->canStart($identityNoSecret));
        $this->assertTrue($v->canStart($identityWithSecret));
    }

    public function testStartAndVerifyAreToBeImplemented(): void
    {
        $secret = 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ';
        $v = new TotpVerificator([
            'options' => [
                'period' => 30,
                'digits' => 6,
                'algorithm' => 'sha1',
                'drift' => 1,
                'now' => 59,
            ],
        ]);

        $identity = new IdentityStub(['totp_secret' => $secret]);
        $request = $this->req();

        $this->assertTrue($v->verify($request, $identity, ['code' => '287082']));
        $this->assertFalse($v->verify($request, $identity, ['code' => '000000']));
    }

    public function testIsVerifiedFalseWhenNoSecret(): void
    {
        $v = new TotpVerificator();

        $identity = new IdentityStub(['totp_secret' => null]);
        $this->assertFalse($v->isVerified($identity));
    }

    public function testIsVerifiedFalseWhenSecretPresentButVerifiedAtNull(): void
    {
        // Default config includes totpVerified => 'totp_verified_at', so that field is always checked
        $v = new TotpVerificator();

        $identity = new IdentityStub(['totp_secret' => 'BASE32SECRET', 'totp_verified_at' => null]);
        $this->assertFalse($v->isVerified($identity));
    }

    public function testIsVerifiedTrueWhenBothSecretAndVerifiedAtSet(): void
    {
        $v = new TotpVerificator();

        $identity = new IdentityStub([
            'totp_secret' => 'BASE32SECRET',
            'totp_verified_at' => '2024-01-01 12:00:00',
        ]);
        $this->assertTrue($v->isVerified($identity));
    }

    public function testIsVerifiedUsesTotpVerifiedAtWhenConfigured(): void
    {
        $v = (new TotpVerificator())->withConfig([
            'fields' => [
                'totpSecret' => 'totp_secret',
                'totpVerified' => 'totp_verified_at',
            ],
        ]);

        $notVerified = new IdentityStub(['totp_secret' => 'BASE32SECRET', 'totp_verified_at' => null]);
        $verified = new IdentityStub(['totp_secret' => 'BASE32SECRET', 'totp_verified_at' => '2024-01-01']);

        $this->assertFalse($v->isVerified($notVerified));
        $this->assertTrue($v->isVerified($verified));
    }

    public function testStartIsNoOp(): void
    {
        $v = new TotpVerificator();
        $identity = new IdentityStub(['totp_secret' => 'BASE32SECRET']);

        // Must not throw
        $v->start($this->req(), $identity);
        $this->assertTrue(true);
    }

    public function testVerifyFailsOnEmptyCode(): void
    {
        $v = new TotpVerificator();
        $identity = new IdentityStub(['totp_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ']);

        $this->assertFalse($v->verify($this->req(), $identity, ['code' => '']));
    }

    public function testVerifyFailsOnNonDigitCode(): void
    {
        $v = new TotpVerificator();
        $identity = new IdentityStub(['totp_secret' => 'GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ']);

        $this->assertFalse($v->verify($this->req(), $identity, ['code' => 'abcdef']));
    }

    public function testVerifyFailsWithEmptySecret(): void
    {
        $v = new TotpVerificator();
        $identity = new IdentityStub(['totp_secret' => '']);

        $this->assertFalse($v->verify($this->req(), $identity, ['code' => '287082']));
    }

    private function req(): ServerRequestInterface
    {
        return $this->createStub(ServerRequestInterface::class);
    }
}
