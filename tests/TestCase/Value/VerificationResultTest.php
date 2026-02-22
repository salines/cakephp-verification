<?php
declare(strict_types=1);

namespace CakeVerification\Test\TestCase\Value;

use Cake\Http\ServerRequestFactory;
use Cake\Routing\Router;
use Cake\TestSuite\TestCase;
use CakeVerification\Test\TestSuite\Stub\IdentityStub;
use CakeVerification\Value\VerificationResult;

final class VerificationResultTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Fresh router context for each test
        Router::reload();
        Router::fullBaseUrl('https://app.test');
        $routes = Router::createRouteBuilder('/');
        $routes->connect('/:controller/:action/*');
    }

    public function testFromRequestNormalizesStepsAndBuildsAbsoluteUrl(): void
    {
        $identity = new IdentityStub(['id' => 1]);
        $request = ServerRequestFactory::fromGlobals();

        $res = VerificationResult::fromRequest(
            $identity,
            ['  Email_OTP ', 'TOTP', 'email_otp', '', 'sms_otp', 'SMS_OTP'],
            ['controller' => 'Users', 'action' => 'verify', 'plugin' => null, 'prefix' => false],
            $request,
        );

        $this->assertSame($identity, $res->identity());
        $this->assertSame(['email_otp', 'totp', 'sms_otp'], $res->pendingSteps(), 'Steps should be trimmed, lowercased and unique (order preserved).');
        $this->assertSame(
            ['controller' => 'Users', 'action' => 'verify'],
            $res->nextRoute(),
        );
        $this->assertSame('', $res->nextUrl());
        $this->assertFalse($res->isVerified(), 'Has pending steps, should not be verified.');
        $this->assertSame('email_otp', $res->firstPendingStep());
    }

    public function testIsVerifiedWhenNoPendingSteps(): void
    {
        $request = ServerRequestFactory::fromGlobals();
        $res = VerificationResult::fromRequest(
            null,
            [],
            ['controller' => 'Users', 'action' => 'dashboard', 'plugin' => null, 'prefix' => false],
            $request,
        );

        $this->assertTrue($res->isVerified());
        $this->assertNull($res->firstPendingStep());
    }

    public function testHasStep(): void
    {
        $request = ServerRequestFactory::fromGlobals();
        $res = VerificationResult::fromRequest(
            null,
            ['Email_OTP', 'TOTP'],
            ['controller' => 'Users', 'action' => 'verify', 'plugin' => null, 'prefix' => false],
            $request,
        );

        $this->assertTrue($res->hasStep('email_otp'));
        $this->assertTrue($res->hasStep('TOTP'));
        $this->assertFalse($res->hasStep('sms_otp'));
    }

    public function testWithoutStepReturnsNewInstanceAndDoesNotMutateOriginal(): void
    {
        $request = ServerRequestFactory::fromGlobals();
        $original = VerificationResult::fromRequest(
            null,
            ['email_otp', 'totp', 'sms_otp'],
            ['controller' => 'Users', 'action' => 'verify', 'plugin' => null, 'prefix' => false],
            $request,
        );

        $modified = $original->withoutStep('TOTP');

        // Original remains unchanged
        $this->assertSame(['email_otp', 'totp', 'sms_otp'], $original->pendingSteps());

        // New instance with removed step (order preserved for remaining)
        $this->assertSame(['email_otp', 'sms_otp'], $modified->pendingSteps());

        // Removing non-existing step keeps list as-is
        $again = $modified->withoutStep('non_existing');
        $this->assertSame(['email_otp', 'sms_otp'], $again->pendingSteps());
    }

    public function testWithNextRouteRecalculatesUrlAndKeepsImmutability(): void
    {
        $request = ServerRequestFactory::fromGlobals();
        $res = VerificationResult::fromRequest(
            null,
            ['totp'],
            ['controller' => 'Users', 'action' => 'verify', 'plugin' => null, 'prefix' => false],
            $request,
        );

        $nextRoute = ['controller' => 'Users', 'action' => 'pending', 'plugin' => null, 'prefix' => false];
        $changed = $res->withNextRoute($nextRoute, $request);

        // Original unchanged
        $this->assertSame(['controller' => 'Users', 'action' => 'verify'], $res->nextRoute());
        $this->assertSame('', $res->nextUrl());

        // New instance updated
        $this->assertSame(['controller' => 'Users', 'action' => 'pending'], $changed->nextRoute());
        $this->assertSame('', $changed->nextUrl());
    }

    public function testToArrayAndJsonSerialize(): void
    {
        $request = ServerRequestFactory::fromGlobals();
        $res = VerificationResult::fromRequest(
            null,
            ['sms_otp'],
            ['controller' => 'Users', 'action' => 'verify', 'plugin' => null, 'prefix' => false],
            $request,
        );

        $arr = $res->toArray();
        $this->assertSame(
            [
                'verified' => false,
                'first_pending_step' => 'sms_otp',
                'pending_steps' => ['sms_otp'],
                'next_route' => ['controller' => 'Users', 'action' => 'verify'],
                'next_url' => '',
            ],
            $arr,
        );

        $json = json_encode($res, JSON_THROW_ON_ERROR);
        $this->assertJson($json);
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($arr, $decoded);
    }
}
