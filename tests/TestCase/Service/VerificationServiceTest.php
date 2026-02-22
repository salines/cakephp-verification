<?php
declare(strict_types=1);

namespace Verification\Test\TestCase\Service;

use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use Verification\Service\VerificationService;
use Verification\Test\TestSuite\Http\ServerRequestFactoryTrait;
use Verification\Test\TestSuite\Stub\AlwaysVerifiedVerificator;
use Verification\Test\TestSuite\Stub\IdentityStub;
use Verification\Test\TestSuite\Stub\NeverVerifiedVerificator;
use Verification\Value\VerificationResult;

final class VerificationServiceTest extends TestCase
{
    use ServerRequestFactoryTrait;

    public function testAllStepsAlreadyVerified(): void
    {
        $config = [
            'requiredSetupSteps' => ['email_otp'],
            'drivers' => [
                'emailOtp' => AlwaysVerifiedVerificator::class,
            ],
        ];
        $service = new VerificationService($config);

        $identity = new IdentityStub([
            'email_verified_at' => '2024-01-01 12:00:00',
        ]);
        $request = $this->makeRequestWithIdentity($identity);
        $result = $service->verify($request);

        $this->assertInstanceOf(VerificationResult::class, $result);
        $this->assertSame([], $result->pendingSteps());
        $this->assertTrue($result->isVerified());
        $this->assertSame('', $result->nextUrl());
        $this->assertSame([], $result->nextRoute());
    }

    public function testOneStepPending(): void
    {
        $config = [
            'requiredSetupSteps' => ['email_otp'],
            'drivers' => [
                'emailOtp' => NeverVerifiedVerificator::class,
            ],
            'routing' => [
                'nextRoute' => ['controller' => 'Users', 'action' => 'verify'],
            ],
        ];
        $service = new VerificationService($config);

        $result = $service->verify($this->makeRequestWithIdentity());

        $this->assertSame(['email_otp'], $result->pendingSteps());
        $this->assertFalse($result->isVerified());
        $this->assertSame(
            ['controller' => 'Users', 'action' => 'verify', 0 => 'email-otp'],
            $result->nextRoute(),
        );
    }

    public function testCsvStepsNormalization(): void
    {
        $config = [
            'requiredSetupSteps' => 'email_otp, sms_otp , email_otp', // ima i duplicata i razmaka
            'drivers' => [
                'emailOtp' => NeverVerifiedVerificator::class,
                'smsOtp' => NeverVerifiedVerificator::class,
            ],
        ];
        $service = new VerificationService($config);

        // getSteps() returns normalized camelCase step names with deduplication
        $this->assertSame(['emailOtp', 'smsOtp'], $service->getSteps());
    }

    // --- Novi testovi za dodatne metode sučelja ---

    public function testGetConfigReturnsArray(): void
    {
        $config = [
            'requiredSetupSteps' => ['totp'],
            'drivers' => ['totp' => AlwaysVerifiedVerificator::class],
            'routing' => ['nextRoute' => ['controller' => 'Users', 'action' => 'verify']],
        ];
        $service = new VerificationService($config);

        $expected = $config + [
            'enabled' => true,
            'requiredSetupSteps' => ['totp'],
            'route' => ['controller' => 'Users', 'action' => 'verify'],
        ];
        $this->assertSame($expected, $service->getConfig());
    }

    public function testGetStepsFromArrayAndDeDup(): void
    {
        $service = new VerificationService([
            'requiredSetupSteps' => ['sms_otp', 'sms_otp', 'email_otp'],
        ]);

        $this->assertSame(['smsOtp', 'emailOtp'], $service->getSteps());
    }

    public function testGetStepsFromCsvTrimmed(): void
    {
        $service = new VerificationService([
            'requiredSetupSteps' => ' sms_otp ,  email_otp ',
        ]);

        $this->assertSame(['smsOtp', 'emailOtp'], $service->getSteps());
    }

    public function testGetDriverReturnsInstance(): void
    {
        $service = new VerificationService([
            'requiredSetupSteps' => ['email_otp'],
            'drivers' => ['emailOtp' => NeverVerifiedVerificator::class],
        ]);

        $driver = $service->getDriver('email_otp');
        $this->assertInstanceOf(NeverVerifiedVerificator::class, $driver);
    }

    public function testGetDriverReturnsNullForUnknown(): void
    {
        $service = new VerificationService([
            'requiredSetupSteps' => ['email_otp'],
            'drivers' => ['emailOtp' => NeverVerifiedVerificator::class],
        ]);

        $this->assertNull($service->getDriver('nope'));
    }

    public function testHasPendingTrue(): void
    {
        $service = new VerificationService([
            'requiredSetupSteps' => ['email_otp'],
            'drivers' => ['emailOtp' => NeverVerifiedVerificator::class],
        ]);

        $this->assertTrue($service->hasPending($this->makeRequestWithIdentity()));
    }

    public function testHasPendingFalse(): void
    {
        $service = new VerificationService([
            'requiredSetupSteps' => ['email_otp'],
            'drivers' => ['emailOtp' => AlwaysVerifiedVerificator::class],
        ]);

        $identity = new IdentityStub([
            'email_verified_at' => '2024-01-01 12:00:00',
        ]);
        $this->assertFalse($service->hasPending($this->makeRequestWithIdentity($identity)));
    }

    public function testVerifyWithoutIdentityReturnsVerified(): void
    {
        $service = new VerificationService([
            'requiredSetupSteps' => ['email_otp'],
            'drivers' => ['emailOtp' => NeverVerifiedVerificator::class],
        ]);

        $request = new ServerRequest();
        $result = $service->verify($request);

        $this->assertInstanceOf(VerificationResult::class, $result);
        $this->assertTrue($result->isVerified());
        $this->assertSame([], $result->pendingSteps());
    }

    public function testHasPendingReturnsFalseWithoutIdentity(): void
    {
        $service = new VerificationService([
            'requiredSetupSteps' => ['email_otp'],
            'drivers' => ['emailOtp' => NeverVerifiedVerificator::class],
        ]);

        $request = new ServerRequest();
        $this->assertFalse($service->hasPending($request));
    }

    public function testGetDriverWithArrayDefinition(): void
    {
        $service = new VerificationService([
            'requiredSetupSteps' => ['email_otp'],
            'drivers' => [
                'emailOtp' => [
                    'className' => NeverVerifiedVerificator::class,
                ],
            ],
        ]);

        $driver = $service->getDriver('emailOtp');
        $this->assertInstanceOf(NeverVerifiedVerificator::class, $driver);
    }

    public function testGetDriverReturnsNullWhenDisabled(): void
    {
        $service = new VerificationService([
            'requiredSetupSteps' => ['email_otp'],
            'drivers' => [
                'emailOtp' => [
                    'className' => NeverVerifiedVerificator::class,
                    'enabled' => false,
                ],
            ],
        ]);

        $this->assertNull($service->getDriver('emailOtp'));
    }

    public function testGetStepsSkipsDisabledDrivers(): void
    {
        $service = new VerificationService([
            'requiredSetupSteps' => ['email_otp', 'totp'],
            'drivers' => [
                'emailOtp' => ['className' => NeverVerifiedVerificator::class, 'enabled' => false],
                'totp' => NeverVerifiedVerificator::class,
            ],
        ]);

        $this->assertSame(['totp'], $service->getSteps());
    }
}
