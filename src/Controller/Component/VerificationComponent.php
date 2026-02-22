<?php
declare(strict_types=1);

namespace Verification\Controller\Component;

use App\Mailer\UserMailer;
use Authentication\Controller\Component\AuthenticationComponent;
use Authentication\Identity;
use Authentication\IdentityInterface;
use Cake\Controller\Component;
use Cake\Controller\Controller;
use Cake\Core\Configure;
use Cake\Datasource\EntityInterface;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Cake\I18n\DateTime;
use Cake\Log\Log;
use Cake\ORM\Table;
use Cake\Routing\Exception\MissingRouteException;
use Cake\Routing\Router;
use Cake\Utility\Inflector;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Verification\Security\CryptoFactory;
use Verification\Security\CryptoInterface;
use Verification\Service\VerificationService;
use Verification\Service\VerificationServiceInterface;
use Verification\Value\VerificationResult;
use Verification\Verificator\VerificationVerificatorInterface;

class VerificationComponent extends Component
{
    private ?VerificationServiceInterface $service = null;
    /**
     * @var array<int, string>
     */
    private array $unverifiedActions = [];
    private ?CryptoInterface $crypto = null;

    /**
     * @param array<string, mixed> $config Component config
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->unverifiedActions = (array)($config['unverifiedActions'] ?? []);
        $this->setConfig([
            'verificationCheckEvent' => $config['verificationCheckEvent'] ?? 'Controller.startup',
        ]);

        $this->getController()->getEventManager()->on(
            'Authentication.afterIdentify',
            function (EventInterface $event): void {
                $this->afterIdentify($event);
            },
        );
    }

    /**
     * @param \Cake\Event\EventInterface $event Event
     * @return void
     */
    public function afterIdentify(EventInterface $event): void
    {
        $identity = $event->getData('identity');
        if (!$identity instanceof IdentityInterface) {
            return;
        }

        $emailVerifyDriver = $this->getDriver('emailVerify');
        $verified = $emailVerifyDriver !== null && $emailVerifyDriver->isVerified($identity);
        if ($verified) {
            $this->startLoginFlow($identity);
        } else {
            $this->startEmailVerify($identity);
        }
    }

    /**
     * @return array<string, string>
     */
    public function implementedEvents(): array
    {
        return [
            'Controller.initialize' => 'beforeFilter',
            'Controller.startup' => 'startup',
        ];
    }

    /**
     * Before filter callback.
     *
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event Event
     * @return \Cake\Http\Response|null
     */
    public function beforeFilter(EventInterface $event): ?Response
    {
        if ($this->getConfig('verificationCheckEvent') === 'Controller.initialize') {
            return $this->doVerificationCheck($event);
        }

        return null;
    }

    /**
     * Startup callback.
     *
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event Event
     * @return \Cake\Http\Response|null
     */
    public function startup(EventInterface $event): ?Response
    {
        if ($this->getConfig('verificationCheckEvent') === 'Controller.startup') {
            return $this->doVerificationCheck($event);
        }

        return null;
    }

    /**
     * @param \Verification\Service\VerificationServiceInterface $service Service instance
     * @return void
     */
    public function setService(VerificationServiceInterface $service): void
    {
        $this->service = $service;
    }

    /**
     * @return \Verification\Service\VerificationServiceInterface
     */
    public function getService(): VerificationServiceInterface
    {
        if ($this->service === null) {
            $config = (array)(Configure::read('Verification') ?? []);
            $emailOtpOpts = $config['drivers']['emailOtp']['options'] ?? null;
            if (isset($emailOtpOpts) && is_array($emailOtpOpts)) {
                $config['drivers']['emailOtp']['delivery'] = $emailOtpOpts['delivery'] ?? null;
            }
            $emailVerifyOpts = $config['drivers']['emailVerify']['options'] ?? null;
            if (isset($emailVerifyOpts) && is_array($emailVerifyOpts)) {
                $config['drivers']['emailVerify']['delivery'] = $emailVerifyOpts['delivery'] ?? null;
            }
            $this->service = new VerificationService($config);
        }

        return $this->service;
    }

    /**
     * @param string $name Driver name
     * @return \Verification\Verificator\VerificationVerificatorInterface|null
     */
    public function getDriver(string $name): ?VerificationVerificatorInterface
    {
        return $this->getService()->getDriver($name);
    }

    /**
     * @param string $name Driver name
     * @param \Authentication\IdentityInterface $identity Identity
     * @param \Psr\Http\Message\ServerRequestInterface|null $request Request
     * @return void
     */
    public function start(string $name, IdentityInterface $identity, ?ServerRequestInterface $request = null): void
    {
        $driver = $this->getDriver($name);
        if ($driver === null) {
            return;
        }

        $driver = $this->applyDefaultOtpDelivery($name, $driver);
        $req = $request ?? $this->getController()->getRequest();
        if ($driver->canStart($identity)) {
            $driver->start($req, $identity);
        }
    }

    /**
     * Verify code.
     *
     * @param string $name Driver name
     * @param \Authentication\IdentityInterface $identity Identity
     * @param array<string, mixed> $data Data
     * @param \Psr\Http\Message\ServerRequestInterface|null $request Request
     * @return bool
     */
    public function verify(
        string $name,
        IdentityInterface $identity,
        array $data,
        ?ServerRequestInterface $request = null,
    ): bool {
        $driver = $this->getDriver($name);
        if ($driver === null) {
            return false;
        }

        $req = $request ?? $this->getController()->getRequest();
        if ($this->normalizeStep($name) === 'totp') {
            $identity = $this->decryptTotpIdentity($identity);
        }

        return $driver->verify($req, $identity, $data);
    }

    /**
     * @param \Psr\Http\Message\ServerRequestInterface|null $request Request
     * @return \Verification\Value\VerificationResult
     */
    public function result(?ServerRequestInterface $request = null): VerificationResult
    {
        $req = $request ?? $this->getController()->getRequest();

        return $this->getService()->verify($req);
    }

    /**
     * Select verification step.
     *
     * @param string|null $step Step name
     * @param \Verification\Value\VerificationResult $verification Result
     * @return string
     */
    public function selectStep(?string $step, VerificationResult $verification): string
    {
        $selected = $this->normalizeStep($step);
        if ($selected === '' && $verification->firstPendingStep() !== null) {
            $selected = $this->normalizeStep($verification->firstPendingStep());
        }

        return $selected;
    }

    /**
     * Check if auto start should be skipped.
     *
     * @param string $selectedStep Selected step
     * @return bool
     */
    public function shouldSkipAutoStart(string $selectedStep): bool
    {
        $request = $this->getController()->getRequest();
        if (!$request->is('get') || !in_array($selectedStep, ['emailOtp', 'smsOtp'], true)) {
            return false;
        }

        $identity = $request->getAttribute('identity');
        if (!$identity instanceof IdentityInterface) {
            return false;
        }
        $orig = $identity->getOriginalData();

        return is_object($orig)
            && !empty($orig->_verification_login_required)
            && !empty($orig->_verification_login_code_sent);
    }

    /**
     * Start verification if needed.
     *
     * @param string $selectedStep Selected step
     * @param \Authentication\IdentityInterface $identity Identity
     * @param \Authentication\IdentityInterface|null $resolved Resolved identity
     * @return void
     */
    public function startIfNeeded(
        string $selectedStep,
        IdentityInterface $identity,
        ?IdentityInterface $resolved = null,
    ): void {
        $request = $this->getController()->getRequest();
        if (!$request->is('get') || !in_array($selectedStep, ['emailOtp', 'smsOtp'], true)) {
            return;
        }

        $driver = $this->getDriver($selectedStep);
        if ($driver === null) {
            return;
        }

        $driver = $this->applyDefaultOtpDelivery($selectedStep, $driver);
        $target = $resolved ?? $identity;
        if (!$driver->canStart($target)) {
            return;
        }

        try {
            $driver->start($request, $target);
            $orig = $identity->getOriginalData();
            if (is_object($orig) && !empty($orig->_verification_login_required)) {
                $orig->_verification_login_code_sent = true;
                $auth = $this->authenticationComponent($this->getController());
                if ($auth !== null) {
                    $auth->setIdentity(new Identity($orig));
                }
            }
        } catch (RuntimeException) {
        }
    }

    /**
     * Check if next step is required.
     *
     * @return bool
     */
    public function requiresNextStep(): bool
    {
        return !$this->result()->isVerified();
    }

    /**
     * Get next URL for verification flow.
     *
     * @return string|null
     */
    public function getNextUrl(): ?string
    {
        $result = $this->result();
        $nextUrl = $result->nextUrl();
        if ($nextUrl !== '') {
            $pendingSteps = $result->pendingSteps();
            if (in_array('email_verify', $pendingSteps, true)) {
                $pendingRoute = (array)Configure::read('Verification.routing.pendingRoute');
                $pendingUrl = $this->routeToUrl($pendingRoute);
                if ($pendingUrl !== '') {
                    $nextUrl = $pendingUrl;
                }
            }
            $pending = $this->normalizeStep($result->firstPendingStep());

            // User must choose their OTP method before proceeding.
            if ($pending === 'chooseVerification') {
                $chooseUrl = $this->routeToUrl((array)Configure::read('Verification.routing.chooseVerificationRoute'));
                if ($chooseUrl !== '') {
                    $nextUrl = $chooseUrl;
                }
            }

            $resolved = $this->resolveIdentity($this->getController()->getRequest()->getAttribute('identity'));
            $user = $resolved?->getOriginalData();
            if (is_object($user) && $pending !== '' && $pending !== 'chooseVerification') {
                $columns = (array)Configure::read('Verification.db.users.columns');
                if ($pending === 'smsOtp') {
                    $phoneField = $this->fieldFromDrivers(['smsOtp'], 'phone')
                        ?? ($columns['phone'] ?? 'phone');
                    if (($user->{$phoneField} ?? null) === null || (string)$user->{$phoneField} === '') {
                        $nextUrl = $this->routeToUrl((array)Configure::read('Verification.routing.enrollPhoneRoute'));
                    }
                } elseif ($pending === 'totp') {
                    $totpSecretField = $this->fieldFromDrivers(['totp'], 'totpSecret')
                        ?? ($columns['totpSecret'] ?? 'totp_secret');
                    if (($user->{$totpSecretField} ?? null) === null || (string)$user->{$totpSecretField} === '') {
                        $nextUrl = $this->routeToUrl((array)Configure::read('Verification.routing.enrollRoute'));
                    }
                }
            }
        }
        if ($nextUrl === '' && $result->isVerified()) {
            $nextUrl = $this->routeToUrl((array)Configure::read('Verification.routing.onVerifiedRoute'));
        }
        Log::debug('Verification nextUrl ' . json_encode([
            'next_route' => $result->nextRoute(),
            'next_url' => $nextUrl,
        ]));

        return $nextUrl !== '' ? $nextUrl : null;
    }

    /**
     * Send email verification link if not already sent this session.
     *
     * @param \Authentication\IdentityInterface $identity Identity
     * @return void
     */
    public function startEmailVerify(IdentityInterface $identity): void
    {
        $driver = $this->getDriver('emailVerify');
        if ($driver === null) {
            return;
        }
        $resolved = $this->resolveIdentity($identity);
        $target = $resolved ?? $identity;
        if ($driver->isVerified($target)) {
            return;
        }
        $user = $target->getOriginalData();
        if (is_object($user) && !empty($user->email_verification_token)) {
            return;
        }
        $driver = $this->applyDefaultEmailVerifyDelivery($driver);
        if (!$driver->canStart($target)) {
            return;
        }
        $request = $this->getController()->getRequest();
        try {
            $driver->start($request, $target);
        } catch (RuntimeException) {
        }
    }

    /**
     * Resend email verification link — clears existing token and sends a new one.
     *
     * @return void
     */
    public function resendEmailVerificationLink(): void
    {
        $driver = $this->getDriver('emailVerify');
        if ($driver === null) {
            return;
        }
        $identity = $this->getController()->getRequest()->getAttribute('identity');
        $resolved = $this->resolveIdentity($identity);
        $target = $resolved ?? $identity;
        $user = $target->getOriginalData();
        if (!is_object($user)) {
            return;
        }

        $controller = $this->getController();
        $users = $this->usersTable($controller);
        $userEntity = $users->get((int)($user->id ?? 0));
        $userEntity->email_verification_token = null;
        $userEntity->email_verification_token_expires = null;
        $users->saveOrFail($userEntity);

        $auth = $this->authenticationComponent($controller);
        if ($auth !== null) {
            $auth->setIdentity(new Identity($userEntity));
        }

        $this->startEmailVerify(new Identity($userEntity));
    }

    /**
     * Send email verification link after registration (user is not yet logged in).
     *
     * @param \Cake\Datasource\EntityInterface $user Freshly saved user entity
     * @return void
     */
    public function afterRegister(EntityInterface $user): void
    {
        $driver = $this->getDriver('emailVerify');
        if ($driver === null) {
            return;
        }
        $driver = $this->applyDefaultEmailVerifyDelivery($driver);
        $identity = new Identity($user);
        if (!$driver->canStart($identity)) {
            return;
        }
        $request = $this->getController()->getRequest();
        try {
            $driver->start($request, $identity);
        } catch (RuntimeException) {
        }
    }

    /**
     * Start login verification flow.
     *
     * @param \Authentication\IdentityInterface|null $identity Identity
     * @return void
     */
    public function startLoginFlow(?IdentityInterface $identity = null): void
    {
        $controller = $this->getController();
        $request = $controller->getRequest();
        $identity = $identity ?? $this->resolveIdentity($request->getAttribute('identity'));
        if (!$identity instanceof IdentityInterface) {
            return;
        }
        $orig = $identity->getOriginalData();
        if (!is_object($orig)) {
            return;
        }

        $id = $identity->getIdentifier();
        if (is_array($id)) {
            $id = $id['id'] ?? '';
        }
        $existingId = (string)($orig->_verification_login_triggered_id ?? '');
        if ($existingId !== '' && $existingId === (string)$id) {
            return;
        }

        $orig->_verification_login_required = true;
        $orig->_verification_login_code_sent = false;
        $orig->_verification_login_triggered_id = (string)$id;

        $auth = $this->authenticationComponent($controller);
        if ($auth !== null) {
            $auth->setIdentity(new Identity($orig));
        }
    }

    /**
     * Apply default OTP delivery callback.
     *
     * @param string $step Step name
     * @param \Verification\Verificator\VerificationVerificatorInterface $driver Driver
     * @return \Verification\Verificator\VerificationVerificatorInterface
     */
    private function applyDefaultOtpDelivery(
        string $step,
        VerificationVerificatorInterface $driver,
    ): VerificationVerificatorInterface {
        $step = $this->normalizeStep($step);
        if ($step !== 'emailOtp') {
            return $driver;
        }
        $config = $driver->getConfig();
        if (is_callable($config['delivery'] ?? null)) {
            return $driver;
        }
        if (!class_exists(UserMailer::class)) {
            return $driver;
        }

        $delivery = function (
            ServerRequestInterface $request,
            IdentityInterface $identity,
            string $code,
        ): void {
            $resolved = $this->resolveIdentity($identity);
            $user = $resolved?->getOriginalData();
            if (!is_object($user)) {
                return;
            }

            (new UserMailer())->send('emailOtp', [$user, $code]);
        };

        return $driver->withConfig(['delivery' => $delivery]);
    }

    /**
     * Apply default email-verify delivery callback.
     *
     * @param \Verification\Verificator\VerificationVerificatorInterface $driver Driver
     * @return \Verification\Verificator\VerificationVerificatorInterface
     */
    private function applyDefaultEmailVerifyDelivery(
        VerificationVerificatorInterface $driver,
    ): VerificationVerificatorInterface {
        $config = $driver->getConfig();
        if (is_callable($config['delivery'] ?? null)) {
            return $driver;
        }
        if (!class_exists(UserMailer::class)) {
            return $driver;
        }

        $delivery = function (
            ServerRequestInterface $request,
            IdentityInterface $identity,
            array $driverConfig,
        ): void {
            $resolved = $this->resolveIdentity($identity);
            $user = $resolved?->getOriginalData();
            if (!is_object($user)) {
                return;
            }

            $token = bin2hex(random_bytes(32));
            $expires = new DateTime('+24 hours');
            $emailField = (string)($driverConfig['fields']['email'] ?? 'email');
            $email = (string)($user->{$emailField} ?? '');
            if ($email === '') {
                return;
            }

            $controller = $this->getController();
            $users = $this->usersTable($controller);
            $userEntity = $users->get((int)($user->id ?? 0));
            $userEntity->email_verification_token = $token;
            $userEntity->email_verification_token_expires = $expires;
            $users->saveOrFail($userEntity);

            $verifyUrl = Router::url(
                ['controller' => 'Users', 'action' => 'verifyEmail', $token],
                true,
            );
            (new UserMailer())->send('emailVerify', [$userEntity, $verifyUrl]);
        };

        return $driver->withConfig(['delivery' => $delivery]);
    }

    /**
     * Redirect after email verification.
     *
     * @param \Cake\Datasource\EntityInterface $user User entity
     * @return \Cake\Http\Response
     */
    public function redirectAfterEmailVerify(EntityInterface $user): Response
    {
        $controller = $this->getController();
        $request = $controller->getRequest();
        // Session cleanup no longer needed; token state tracked on identity object.
        $identity = $request->getAttribute('identity');
        $auth = $this->authenticationComponent($controller);
        if ($identity instanceof IdentityInterface && $auth instanceof AuthenticationComponent) {
            $user->_verification_login_required = true;
            $user->_verification_login_code_sent = false;
            $user->_verification_login_triggered_id = (string)($user->id ?? '');
            $auth->setIdentity(new Identity($user));

            $response = $controller->redirect($this->getNextUrl() ?? '/');

            return $response ?? $controller->getResponse();
        }

        $response = $controller->redirect(['action' => 'login']);

        return $response ?? $controller->getResponse();
    }

    /**
     * Allow actions without verification.
     *
     * @param array<string> $actions Action list
     * @return static
     */
    public function allowUnverified(array $actions): static
    {
        $this->unverifiedActions = $actions;

        return $this;
    }

    /**
     * Perform verification check.
     *
     * @param \Cake\Event\EventInterface<\Cake\Controller\Controller> $event Event
     * @return \Cake\Http\Response|null
     */
    private function doVerificationCheck(EventInterface $event): ?Response
    {
        if (!(bool)Configure::read('Verification.enabled', false)) {
            return null;
        }

        $controller = $this->getController();
        $request = $controller->getRequest();
        $action = (string)$request->getParam('action');
        if (in_array($action, $this->unverifiedActions, true)) {
            return null;
        }

        $result = $this->result($request);
        if ($result->isVerified()) {
            return null;
        }

        $nextUrl = $this->getNextUrl() ?? $result->nextUrl() ?: '/';

        $response = $controller->redirect($nextUrl);
        $event->stopPropagation();
        $event->setResult($response);

        return $response;
    }

    /**
     * Normalize step name.
     *
     * @param string|null $step Step name
     * @return string
     */
    private function normalizeStep(?string $step): string
    {
        $value = trim((string)$step);
        if ($value === '') {
            return '';
        }
        $value = str_replace('-', '_', $value);
        $value = Inflector::camelize($value);

        return lcfirst($value);
    }

    /**
     * Handle verification action.
     *
     * @param string|null $step Step name
     * @return \Cake\Http\Response|null
     */
    public function handleVerify(?string $step = null): ?Response
    {
        $controller = $this->getController();
        $request = $controller->getRequest();
        $identity = $request->getAttribute('identity');

        $verification = $this->result();
        $selectedStep = $this->selectStep($step, $verification);
        $pendingSteps = $verification->pendingSteps();
        if (
            $selectedStep !== ''
            && in_array('email_verify', $pendingSteps, true)
            && $this->normalizeStep($selectedStep) === 'emailVerify'
        ) {
            $pendingRoute = (array)Configure::read('Verification.routing.pendingRoute');

            return $controller->redirect($this->routeToUrl($pendingRoute) ?: '/');
        }
        if ($selectedStep !== '' && $pendingSteps !== []) {
            $selectedKey = strtolower(Inflector::underscore($selectedStep));
            if (!in_array($selectedKey, $pendingSteps, true)) {
                return $controller->redirect($this->getNextUrl() ?? '/');
            }
        }
        if ($this->shouldSkipAutoStart($selectedStep)) {
            $controller->set([
                'verification' => $verification,
                'step' => $step,
            ]);

            return null;
        }

        $resolved = $this->resolveIdentity($identity);
        if ($identity instanceof IdentityInterface) {
            $this->startIfNeeded($selectedStep, $identity, $resolved);
        }

        if ($request->is('post') && $selectedStep !== '' && $identity instanceof IdentityInterface) {
            if ((string)$request->getData('resend') === '1') {
                try {
                    $this->start($selectedStep, $identity);
                    $controller->Flash->success(__('Code has been resent.'));
                } catch (RuntimeException) {
                    $controller->Flash->error(__('Resend is not available.'));
                }

                return $controller->redirect($this->getNextUrl() ?? '/');
            }

            $ok = $this->verify($selectedStep, $identity, $request->getData());
            if ($ok) {
                $this->markVerifiedStep($selectedStep, $identity);
                $controller->Flash->success(__('Verification successful.'));

                return $controller->redirect($this->getNextUrl() ?? '/');
            }
            $controller->Flash->error(__('Invalid code.'));
        }

        $controller->set([
            'verification' => $verification,
            'step' => $step,
        ]);

        return null;
    }

    /**
     * Handle TOTP enrollment.
     *
     * @return \Cake\Http\Response|null
     */
    public function handleEnroll(): ?Response
    {
        $controller = $this->getController();
        $request = $controller->getRequest();
        $identity = $request->getAttribute('identity');
        if (!$identity instanceof IdentityInterface) {
            return null;
        }
        if (in_array('email_verify', $this->result()->pendingSteps(), true)) {
            return $controller->redirect($this->getNextUrl() ?? '/');
        }

        $users = $this->usersTable($controller);
        $user = $users->get($this->identityId($identity));
        $secretField = $this->totpSecretField();
        $storedSecret = (string)($user->{$secretField} ?? '');
        $plainSecret = '';
        if ($storedSecret === '') {
            $plainSecret = $this->generateTotpSecret();
            $user->{$secretField} = $this->encryptSecret($plainSecret);
            $users->saveOrFail($user);
            $auth = $this->authenticationComponent($controller);
            if ($auth instanceof AuthenticationComponent) {
                $auth->setIdentity(new Identity($user));
            }
        } else {
            $plainSecret = $this->decryptSecret($storedSecret);
            if ($plainSecret === '' || $plainSecret === $storedSecret) {
                if ($this->isBase32Secret($storedSecret)) {
                    $plainSecret = $storedSecret;
                } else {
                    $plainSecret = $this->generateTotpSecret();
                    $user->{$secretField} = $this->encryptSecret($plainSecret);
                    $users->saveOrFail($user);
                    $auth = $this->authenticationComponent($controller);
                    if ($auth instanceof AuthenticationComponent) {
                        $auth->setIdentity(new Identity($user));
                    }
                }
            }
        }

        if ($request->is(['post', 'put', 'patch'])) {
            $ok = $this->verify('totp', new Identity($user), $request->getData());
            if ($ok) {
                $this->markVerifiedStep('totp', $identity);
                $controller->Flash->success(__('TOTP enabled.'));

                return $controller->redirect($this->getNextUrl() ?? '/');
            }
            $controller->Flash->error(__('Invalid code.'));
        }

        $columns = (array)Configure::read('Verification.db.users.columns');
        $emailField = $this->fieldFromDrivers(['emailVerify', 'emailOtp'], 'email')
            ?? ($columns['email'] ?? 'email');
        $email = (string)$user->get($emailField);
        $issuer = (string)Configure::read('App.name', 'MyApp');
        $qrData = sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s',
            rawurlencode($issuer),
            rawurlencode($email),
            rawurlencode($plainSecret),
            rawurlencode($issuer),
        );

        $controller->set([
            'qrData' => $qrData,
            'secret' => $plainSecret,
        ]);

        return null;
    }

    /**
     * Handle phone enrollment.
     *
     * @return \Cake\Http\Response|null
     */
    public function handleEnrollPhone(): ?Response
    {
        $controller = $this->getController();
        $request = $controller->getRequest();
        $identity = $request->getAttribute('identity');
        if (!$identity instanceof IdentityInterface) {
            return null;
        }
        if (in_array('email_verify', $this->result()->pendingSteps(), true)) {
            return $controller->redirect($this->getNextUrl() ?? '/');
        }

        $users = $this->usersTable($controller);
        $user = $users->get($this->identityId($identity));
        if ($request->is(['post', 'put', 'patch'])) {
            $user = $users->patchEntity($user, $request->getData());
            if ($users->save($user)) {
                $auth = $this->authenticationComponent($controller);
                if ($auth instanceof AuthenticationComponent) {
                    $auth->setIdentity(new Identity($user));
                }

                return $controller->redirect($this->getNextUrl() ?? '/');
            }
        }

        $controller->set(compact('user'));

        return null;
    }

    /**
     * Handle OTP driver selection by the user.
     *
     * @return \Cake\Http\Response|null
     */
    public function handleChooseVerification(): ?Response
    {
        $controller = $this->getController();
        $request = $controller->getRequest();
        $identity = $request->getAttribute('identity');
        if (!$identity instanceof IdentityInterface) {
            return null;
        }

        $availableDrivers = $this->getService()->getAvailableOtpDrivers();
        $columns = (array)Configure::read('Verification.db.users.columns');
        $prefsField = $columns['verificationPreferences'] ?? 'verification_preferences';

        if ($request->is(['post', 'put', 'patch'])) {
            $chosen = (string)($request->getData('verification_preferences.otp_driver') ?? '');
            if ($chosen === '' || !in_array($chosen, $availableDrivers, true)) {
                $controller->Flash->error(__('Please select a valid verification method.'));
            } else {
                $users = $this->usersTable($controller);
                $user = $users->get($this->identityId($identity));
                $user->{$prefsField} = ['otp_driver' => $chosen];
                $users->saveOrFail($user);

                $auth = $this->authenticationComponent($controller);
                if ($auth instanceof AuthenticationComponent) {
                    $auth->setIdentity(new Identity($user));
                }

                return $controller->redirect($this->getNextUrl() ?? '/');
            }
        }

        $origData = $identity->getOriginalData();
        if (is_object($origData)) {
            $prefs = $origData->{$prefsField} ?? null;
        } elseif (is_array($origData)) {
            $prefs = $origData[$prefsField] ?? null;
        } else {
            $prefs = null;
        }
        if (is_string($prefs)) {
            $prefs = json_decode($prefs, true);
        }
        $selectedDriver = is_array($prefs) ? ($prefs['otp_driver'] ?? null) : null;

        $controller->set([
            'availableDrivers' => $availableDrivers,
            'selectedDriver' => $selectedDriver,
        ]);

        return null;
    }

    /**
     * Mark step as verified.
     *
     * @param string $step Step name
     * @param \Authentication\IdentityInterface $identity Identity
     * @return void
     */
    private function markVerifiedStep(string $step, IdentityInterface $identity): void
    {
        $controller = $this->getController();
        $users = $this->usersTable($controller);
        $user = $users->get($this->identityId($identity));
        $now = DateTime::now();
        $columns = (array)Configure::read('Verification.db.users.columns');

        $emailVerifiedAt = $this->fieldFromDrivers(['emailVerify', 'emailOtp'], 'emailVerified')
            ?? ($columns['emailVerifiedAt'] ?? 'email_verified_at');
        $phoneVerifiedAt = $this->fieldFromDrivers(['smsOtp'], 'phoneVerifiedAt')
            ?? ($columns['phoneVerifiedAt'] ?? 'phone_verified_at');
        $totpVerifiedAt = $this->fieldFromDrivers(['totp'], 'totpVerified')
            ?? ($columns['totpVerifiedAt'] ?? 'totp_verified_at');

        $phoneVerifiedFlag = $columns['phoneVerifiedFlag'] ?? 'phone_verified';

        if ($step === 'emailVerify' || $step === 'emailOtp') {
            if ($user->{$emailVerifiedAt} === null) {
                $user->{$emailVerifiedAt} = $now;
            }
        }
        if ($step === 'smsOtp') {
            if ($user->{$phoneVerifiedAt} === null && !$user->{$phoneVerifiedFlag}) {
                $user->{$phoneVerifiedAt} = $now;
                $user->{$phoneVerifiedFlag} = true;
            }
        }
        if ($step === 'totp') {
            if ($user->{$totpVerifiedAt} === null) {
                $user->{$totpVerifiedAt} = $now;
            }
        }

        $users->saveOrFail($user);
        $auth = $this->authenticationComponent($controller);
        if ($auth instanceof AuthenticationComponent) {
            $user->_verification_login_required = false;
            $user->_verification_login_code_sent = false;
            $user->_verification_login_triggered_id = null;
            $auth->setIdentity(new Identity($user));
        }
    }

    /**
     * Get field name from driver config.
     *
     * @param array<string> $drivers Driver names
     * @param string $fieldKey Field key
     * @return string|null
     */
    private function fieldFromDrivers(array $drivers, string $fieldKey): ?string
    {
        foreach ($drivers as $driver) {
            $value = Configure::read('Verification.drivers.' . $driver . '.fields.' . $fieldKey);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * Get TOTP secret field name.
     *
     * @return string
     */
    private function totpSecretField(): string
    {
        $columns = (array)Configure::read('Verification.db.users.columns');

        return $this->fieldFromDrivers(['totp'], 'totpSecret')
            ?? ($columns['totpSecret'] ?? 'totp_secret');
    }

    /**
     * Convert route array to URL.
     *
     * @param array<string, mixed> $route Route array
     * @return string
     */
    private function routeToUrl(array $route): string
    {
        if ($route === []) {
            return '';
        }

        try {
            return Router::url($route + ['_full' => true], true);
        } catch (MissingRouteException) {
            return '';
        }
    }

    /**
     * Resolve identity from database.
     *
     * @param \Authentication\IdentityInterface|null $identity Identity
     * @return \Authentication\IdentityInterface|null
     */
    private function resolveIdentity(?IdentityInterface $identity): ?IdentityInterface
    {
        if (!$identity instanceof IdentityInterface) {
            return null;
        }

        $original = $identity->getOriginalData();
        if (is_object($original)) {
            return $identity;
        }

        $id = $this->identityId($identity);
        if ($id === 0) {
            return $identity;
        }

        $controller = $this->getController();
        $users = $this->usersTable($controller);
        $user = $users->get($id);

        return new Identity($user);
    }

    /**
     * Decrypt TOTP secret in identity.
     *
     * @param \Authentication\IdentityInterface $identity Identity
     * @return \Authentication\IdentityInterface
     */
    private function decryptTotpIdentity(IdentityInterface $identity): IdentityInterface
    {
        $original = $identity->getOriginalData();
        if (!is_object($original)) {
            return $identity;
        }

        $field = $this->totpSecretField();
        $stored = (string)($original->{$field} ?? '');
        if ($stored === '') {
            return $identity;
        }

        $plain = $this->decryptSecret($stored);
        if ($plain === $stored || $plain === '') {
            return $identity;
        }

        $clone = $original instanceof EntityInterface ? clone $original : clone $original;
        $clone->{$field} = $plain;

        return new Identity($clone);
    }

    /**
     * Encrypt secret.
     *
     * @param string $secret Plain secret
     * @return string
     */
    private function encryptSecret(string $secret): string
    {
        $crypto = $this->getCrypto();
        if ($crypto === null) {
            return $secret;
        }

        return $crypto->encrypt($secret);
    }

    /**
     * Decrypt secret.
     *
     * @param string $payload Encrypted payload
     * @return string
     */
    private function decryptSecret(string $payload): string
    {
        $crypto = $this->getCrypto();
        if ($crypto === null) {
            return $payload;
        }

        try {
            return $crypto->decrypt($payload);
        } catch (RuntimeException) {
            return $payload;
        }
    }

    /**
     * Get crypto driver.
     *
     * @return \Verification\Security\CryptoInterface|null
     */
    private function getCrypto(): ?CryptoInterface
    {
        if ($this->crypto !== null) {
            return $this->crypto;
        }

        $config = (array)Configure::read('Verification.crypto');
        $this->crypto = CryptoFactory::create($config);

        return $this->crypto;
    }

    /**
     * Generate TOTP secret.
     *
     * @return string
     */
    private function generateTotpSecret(): string
    {
        $bytes = random_bytes(20);
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = '';
        foreach (str_split($bytes) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $secret = '';
        foreach (str_split($bits, 5) as $chunk) {
            if (strlen($chunk) < 5) {
                break;
            }
            $index = (int)bindec($chunk);
            if (!isset($alphabet[$index])) {
                continue;
            }
            $secret .= $alphabet[$index];
        }

        return $secret;
    }

    /**
     * Check if value is a valid Base32 secret.
     *
     * @param string $value Value to check
     * @return bool
     */
    private function isBase32Secret(string $value): bool
    {
        $length = strlen($value);
        if ($length < 16 || $length > 64) {
            return false;
        }

        return (bool)preg_match('/^[A-Z2-7]+$/', $value);
    }

    /**
     * @param \Cake\Controller\Controller $controller Controller
     * @return \Cake\ORM\Table
     */
    private function usersTable(Controller $controller): Table
    {
        return $controller->fetchTable('Users');
    }

    /**
     * @param \Cake\Controller\Controller $controller Controller
     * @return \Authentication\Controller\Component\AuthenticationComponent|null
     */
    private function authenticationComponent(Controller $controller): ?AuthenticationComponent
    {
        if (!$controller->components()->has('Authentication')) {
            return null;
        }

        $component = $controller->components()->get('Authentication');

        return $component instanceof AuthenticationComponent ? $component : null;
    }

    /**
     * @param \Authentication\IdentityInterface $identity Identity
     * @return int
     */
    private function identityId(IdentityInterface $identity): int
    {
        $id = $identity->getIdentifier();
        if (is_array($id)) {
            $id = $id['id'] ?? 0;
        }

        return (int)$id;
    }
}
