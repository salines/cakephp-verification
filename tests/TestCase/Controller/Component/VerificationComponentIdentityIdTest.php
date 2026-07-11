<?php
declare(strict_types=1);

namespace CakeVerification\Test\TestCase\Controller\Component;

use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Http\ServerRequest;
use Cake\TestSuite\TestCase;
use CakeVerification\Controller\Component\VerificationComponent;
use CakeVerification\Test\TestSuite\Stub\IdentityStub;
use ReflectionMethod;

/**
 * Tests for VerificationComponent::identityId().
 *
 * The identity primary key must pass through unchanged for both integer
 * and string (UUID) keys, and fall back to 0 when missing.
 */
final class VerificationComponentIdentityIdTest extends TestCase
{
    private function makeComponent(): VerificationComponent
    {
        Configure::write('App.encoding', 'UTF-8');
        $controller = new Controller(new ServerRequest());

        return new VerificationComponent($controller->components());
    }

    private function callIdentityId(IdentityStub $identity): int|string
    {
        $method = new ReflectionMethod(VerificationComponent::class, 'identityId');

        return $method->invoke($this->makeComponent(), $identity);
    }

    public function testIntegerIdIsReturnedAsIs(): void
    {
        $this->assertSame(5, $this->callIdentityId(new IdentityStub(['id' => 5])));
    }

    public function testUuidStringIdIsReturnedAsIs(): void
    {
        $uuid = '0198f2a4-7c3b-7d8e-9f10-1234567890ab';

        $this->assertSame($uuid, $this->callIdentityId(new IdentityStub(['id' => $uuid])));
    }

    public function testArrayIdentifierWithUuidIsUnwrapped(): void
    {
        $uuid = '0198f2a4-7c3b-7d8e-9f10-1234567890ab';
        $identity = new IdentityStub(['id' => ['id' => $uuid]]);

        $this->assertSame($uuid, $this->callIdentityId($identity));
    }

    public function testMissingIdFallsBackToZero(): void
    {
        $this->assertSame(0, $this->callIdentityId(new IdentityStub()));
    }

    public function testEmptyStringIdFallsBackToZero(): void
    {
        $this->assertSame(0, $this->callIdentityId(new IdentityStub(['id' => ''])));
    }
}
