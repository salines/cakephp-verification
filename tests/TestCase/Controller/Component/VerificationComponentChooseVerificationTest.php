<?php
declare(strict_types=1);

namespace Verification\Test\TestCase\Controller\Component;

use Cake\Controller\ComponentRegistry;
use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Http\Response;
use Cake\Http\ServerRequest;
use Cake\ORM\Entity;
use Cake\ORM\Table;
use Cake\TestSuite\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Verification\Controller\Component\VerificationComponent;
use Verification\Service\VerificationServiceInterface;
use Verification\Test\TestSuite\Stub\IdentityStub;
use Verification\Value\VerificationResult;

/**
 * Tests for VerificationComponent::handleChooseVerification().
 *
 * Unit tests — no database, no HTTP stack.
 * The controller is a real instance with stubbed fetchTable().
 */
final class VerificationComponentChooseVerificationTest extends TestCase
{
    /**
     * Build a controller + component wired with the given request and mock service.
     *
     * @param \Cake\Http\ServerRequest $request
     * @param \Verification\Service\VerificationServiceInterface&\PHPUnit\Framework\MockObject\MockObject $service
     * @param \Cake\ORM\Table|null $usersTable Optional stub table (needed for POST save path)
     * @return \Verification\Controller\Component\VerificationComponent
     */
    private function makeComponent(
        ServerRequest $request,
        VerificationServiceInterface&MockObject $service,
        ?Table $usersTable = null,
    ): VerificationComponent {
        Configure::write('App.encoding', 'UTF-8');
        $stubTable = $usersTable;

        $controller = new class ($request) extends Controller {
            public ?Table $stubTable = null;

            public function fetchTable(?string $alias = null, array $options = []): Table
            {
                if ($this->stubTable !== null) {
                    return $this->stubTable;
                }

                return parent::fetchTable($alias, $options);
            }
        };

        $controller->stubTable = $stubTable;

        $controller->loadComponent('Flash');
        $registry = $controller->components();
        $component = new VerificationComponent($registry);
        $component->setService($service);

        return $component;
    }

    /**
     * Build a mock VerificationServiceInterface that reports the given available drivers.
     *
     * @param list<string> $drivers
     * @return \Verification\Service\VerificationServiceInterface&\PHPUnit\Framework\MockObject\MockObject
     */
    private function makeService(array $drivers): VerificationServiceInterface&MockObject
    {
        $service = $this->createMock(VerificationServiceInterface::class);
        $service->method('getAvailableOtpDrivers')->willReturn($drivers);

        return $service;
    }

    // -------------------------------------------------------------------------
    // GET — no identity
    // -------------------------------------------------------------------------

    public function testGetWithoutIdentityReturnsNull(): void
    {
        $request = new ServerRequest(['environment' => ['REQUEST_METHOD' => 'GET']]);
        $service = $this->makeService(['emailOtp', 'smsOtp']);
        $component = $this->makeComponent($request, $service);

        $result = $component->handleChooseVerification();

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // GET — identity present, no stored preference
    // -------------------------------------------------------------------------

    public function testGetSetsAvailableDriversAndNullSelectedDriver(): void
    {
        $identity = new IdentityStub(['id' => 1]);
        $request = (new ServerRequest(['environment' => ['REQUEST_METHOD' => 'GET']]))
            ->withAttribute('identity', $identity);

        $service = $this->makeService(['emailOtp', 'smsOtp']);
        $component = $this->makeComponent($request, $service);

        $result = $component->handleChooseVerification();

        $this->assertNull($result);

        $viewVars = $component->getController()->viewBuilder()->getVars();
        $this->assertSame(['emailOtp', 'smsOtp'], $viewVars['availableDrivers']);
        $this->assertNull($viewVars['selectedDriver']);
    }

    // -------------------------------------------------------------------------
    // GET — identity has stored preference
    // -------------------------------------------------------------------------

    public function testGetPreSelectsStoredDriver(): void
    {
        $identity = new IdentityStub([
            'id' => 1,
            'verification_preferences' => ['otp_driver' => 'smsOtp'],
        ]);
        $request = (new ServerRequest(['environment' => ['REQUEST_METHOD' => 'GET']]))
            ->withAttribute('identity', $identity);

        $service = $this->makeService(['emailOtp', 'smsOtp']);
        $component = $this->makeComponent($request, $service);

        $component->handleChooseVerification();

        $viewVars = $component->getController()->viewBuilder()->getVars();
        $this->assertSame('smsOtp', $viewVars['selectedDriver']);
    }

    // -------------------------------------------------------------------------
    // POST — invalid driver
    // -------------------------------------------------------------------------

    public function testPostInvalidDriverReturnsNull(): void
    {
        $identity = new IdentityStub(['id' => 1]);
        $request = new ServerRequest([
            'environment' => ['REQUEST_METHOD' => 'POST'],
            'post' => ['verification_preferences' => ['otp_driver' => 'invalidDriver']],
        ]);
        $request = $request->withAttribute('identity', $identity);

        $service = $this->makeService(['emailOtp', 'smsOtp']);
        $component = $this->makeComponent($request, $service);

        $result = $component->handleChooseVerification();

        // No redirect on invalid input
        $this->assertNull($result);
    }

    public function testPostEmptyDriverReturnsNull(): void
    {
        $identity = new IdentityStub(['id' => 1]);
        $request = new ServerRequest([
            'environment' => ['REQUEST_METHOD' => 'POST'],
            'post' => [],
        ]);
        $request = $request->withAttribute('identity', $identity);

        $service = $this->makeService(['emailOtp', 'smsOtp']);
        $component = $this->makeComponent($request, $service);

        $result = $component->handleChooseVerification();

        $this->assertNull($result);
    }

    // -------------------------------------------------------------------------
    // POST — valid driver — saves and redirects
    // -------------------------------------------------------------------------

    public function testPostValidDriverSavesPrefsAndRedirects(): void
    {
        $identity = new IdentityStub(['id' => 42]);

        $request = new ServerRequest([
            'environment' => ['REQUEST_METHOD' => 'POST', 'HTTP_HOST' => 'localhost'],
            'post' => ['verification_preferences' => ['otp_driver' => 'emailOtp']],
        ]);
        $request = $request->withAttribute('identity', $identity);

        $service = $this->makeService(['emailOtp', 'smsOtp']);

        // verify() is called by getNextUrl() internally; return a fully-verified result so redirect goes to '/'
        $service->method('verify')->willReturn(
            VerificationResult::fromRequest(null, [], [], $request),
        );

        // Stub Table: get() returns a plain entity, saveOrFail() accepts it
        $userEntity = new Entity(['id' => 42]);
        $usersTable = $this->createMock(Table::class);
        $usersTable->method('get')->with(42)->willReturn($userEntity);
        $usersTable->method('saveOrFail')->willReturn($userEntity);

        $component = $this->makeComponent($request, $service, $usersTable);

        $result = $component->handleChooseVerification();

        // Should redirect (Response object returned), not null
        $this->assertInstanceOf(Response::class, $result);

        // Entity must have the chosen driver stored correctly in the prefs field
        $this->assertSame(['otp_driver' => 'emailOtp'], $userEntity->get('verification_preferences'));
    }
}
