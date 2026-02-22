<?php
declare(strict_types=1);

namespace CakeVerification\Service;

use Authentication\IdentityInterface;
use Cake\Datasource\EntityInterface;
use Cake\Utility\Inflector;
use CakeVerification\Transport\Sms\TransportFactory;
use CakeVerification\Transport\Sms\TransportInterface;
use CakeVerification\Value\VerificationResult;
use CakeVerification\Verificator\Driver\SmsOtpVerificator;
use CakeVerification\Verificator\VerificationVerificatorInterface;
use Psr\Http\Message\ServerRequestInterface;

final class VerificationService implements VerificationServiceInterface
{
    /**
     * @param array<string,mixed> $config
     */
    public function __construct(private array $config = [])
    {
        // 1) Allow nested config: ['Verification' => [...]]
        if (isset($this->config['Verification']) && is_array($this->config['Verification'])) {
            $this->config = $this->config['Verification'];
        }

        // 2) Defaults
        $this->config += [
            'enabled' => true,
            'requiredSetupSteps' => [],
            // legacy single route key (will be backfilled from routing below)
            'route' => null,
            'drivers' => [],
            'routing' => [],
        ];

        // 3) Normalize setup steps (accept snake_case or camelCase; keep order, drop duplicates)
        $rawSteps = $this->config['requiredSetupSteps'];
        if (is_string($rawSteps)) {
            $rawSteps = array_map('trim', explode(',', $rawSteps));
        }

        $normalized = array_values(array_filter(array_map([$this, 'normalizeStepName'], (array)$rawSteps)));
        $steps = [];
        foreach ($normalized as $step) {
            if (!in_array($step, $steps, true)) {
                $steps[] = $step;
            }
        }
        $this->config['requiredSetupSteps'] = $steps;

        // 4) Backfill route:
        // Prefer 'routing.nextRoute' → fallback 'route'
        $route = null;
        if (isset($this->config['routing']['nextRoute']) && is_array($this->config['routing']['nextRoute'])) {
            $route = $this->config['routing']['nextRoute'];
        } elseif (is_array($this->config['route'])) {
            $route = $this->config['route'];
        }
        // Absolute URL hint (_full) handled in VerificationResult::fromRequest()
        $route = $route ?? ['plugin' => false, 'prefix' => false, 'controller' => 'Users', 'action' => 'verify'];
        $this->config['route'] = $this->normalizeRoute($route);
    }

    /**
     * @inheritDoc
     */
    public function verify(ServerRequestInterface $request): VerificationResult
    {
        if (!($this->config['enabled'] ?? false)) {
            return VerificationResult::fromRequest($request->getAttribute('identity'), [], [], $request);
        }

        $identity = $request->getAttribute('identity');
        if (!$identity instanceof IdentityInterface) {
            return VerificationResult::fromRequest(null, [], [], $request);
        }

        $steps = $this->getSteps();
        $pending = $this->computePending($identity, $steps);

        if ($pending === []) {
            return VerificationResult::fromRequest($identity, [], [], $request);
        }

        $nextRoute = $this->normalizeRoute((array)$this->config['route']);
        $nextRoute[] = Inflector::dasherize($pending[0]);

        return VerificationResult::fromRequest($identity, $pending, $nextRoute, $request);
    }

    /**
     * @inheritDoc
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * @inheritDoc
     */
    public function getSteps(): array
    {
        $steps = (array)$this->config['requiredSetupSteps'];
        $enabled = array_values(array_filter($steps, fn(string $step): bool => $this->isStepEnabled($step)));

        /** @var array<int,string> */
        return $enabled;
    }

    /**
     * @inheritDoc
     */
    public function getDriver(string $name): ?VerificationVerificatorInterface
    {
        $drivers = (array)($this->config['drivers'] ?? []);
        $driverMap = (array)($this->config['driverMap'] ?? []);
        $normalizedName = $this->normalizeStepName($name);

        $definition = $drivers[$normalizedName] ?? $drivers[$name] ?? null;
        if (is_array($definition)) {
            if (array_key_exists('enabled', $definition) && $definition['enabled'] === false) {
                return null;
            }
            $class = $definition['className'] ?? null;
            $class = $this->normalizeDriverClass($class);
            if ($class === null) {
                return null;
            }

            $config = $this->buildDriverConfig($normalizedName, $definition);

            return $this->instantiateDriver($class, $config);
        }

        if (is_string($definition) && $definition !== '') {
            $class = $this->normalizeDriverClass($definition);
            if ($class === null) {
                return null;
            }

            return $this->instantiateDriver($class, []);
        }

        $mapped = $driverMap[$normalizedName] ?? $driverMap[$name] ?? null;
        if (is_string($mapped) && $mapped !== '') {
            $class = $this->normalizeDriverClass($mapped);
            if ($class === null) {
                return null;
            }

            return $this->instantiateDriver($class, []);
        }

        return null;
    }

    /**
     * @inheritDoc
     */
    public function hasPending(ServerRequestInterface $request): bool
    {
        $identity = $request->getAttribute('identity');
        if (!$identity instanceof IdentityInterface) {
            return false;
        }

        return $this->computePending($identity, $this->getSteps()) !== [];
    }

    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * @param string $step Raw step name
     * @return string
     */
    private function normalizeStepName(string $step): string
    {
        $s = trim($step);
        if ($s === '') {
            return '';
        }
        // snake_case → camelCase
        if (str_contains($s, '_')) {
            $parts = explode('_', strtolower($s));
            $s = array_shift($parts) . implode('', array_map('ucfirst', $parts));
        }
        // whitelist known steps (extend as needed)
        return match ($s) {
            'emailVerify', 'emailOtp', 'smsOtp', 'totp' => $s,
            default => $s, // allow custom steps to pass through
        };
    }

    /**
     * Decide which steps are still required for the identity.
     *
     * @param \Authentication\IdentityInterface $identity Identity
     * @param array<int,string> $orderedSteps Steps
     * @return array<int,string>
     */
    private function computePending(IdentityInterface $identity, array $orderedSteps): array
    {
        $data = $this->extractIdentityData($identity);
        $orig = $identity->getOriginalData();
        $loginRequired = is_object($orig) && !empty($orig->_verification_login_required);

        // OTP steps available to the user (all non-emailVerify steps in requiredSetupSteps).
        $otpSteps = array_values(array_filter($orderedSteps, fn($s) => $s !== 'emailVerify'));

        // User's chosen OTP driver from their preferences.
        $userOtpDriver = $this->getUserOtpDriver($identity, $otpSteps);

        $pending = [];
        foreach ($orderedSteps as $step) {
            $isOtpStep = $step !== 'emailVerify';

            // If multiple OTP drivers available and user hasn't chosen yet:
            // - During login flow: block and redirect to choose.
            // - During setup/browsing (no login flag): skip silently so app access is not blocked.
            if ($isOtpStep && count($otpSteps) > 1 && $userOtpDriver === null) {
                if ($loginRequired && !in_array('chooseVerification', $pending, true)) {
                    $pending[] = 'chooseVerification';
                }
                continue;
            }

            // If user has chosen a driver, only process their chosen OTP step; skip others.
            if ($isOtpStep && $userOtpDriver !== null && $step !== $userOtpDriver) {
                continue;
            }

            if (!$this->isStepComplete($step, $data)) {
                $pending[] = $step;
            } elseif ($loginRequired && $isOtpStep) {
                // Step is complete in DB but login-time verification is required — treat as pending.
                $pending[] = $step;
            }
        }

        return $pending;
    }

    /**
     * Returns the OTP driver chosen by the user, or null if not chosen / only one available.
     *
     * @param \Authentication\IdentityInterface $identity Identity
     * @param array<int,string> $otpSteps Available OTP steps
     * @return string|null
     */
    public function getUserOtpDriver(IdentityInterface $identity, array $otpSteps): ?string
    {
        // If only one OTP driver available, no choice needed.
        if (count($otpSteps) <= 1) {
            return $otpSteps[0] ?? null;
        }

        $columns = $this->config['db']['users']['columns'] ?? [];
        $prefsField = (string)($columns['verificationPreferences'] ?? 'verification_preferences');
        $data = $this->extractIdentityData($identity);
        $prefs = $data[$prefsField] ?? null;

        if (is_string($prefs)) {
            $prefs = json_decode($prefs, true);
        }

        $chosen = is_array($prefs) ? ($prefs['otp_driver'] ?? null) : null;
        if (!is_string($chosen) || $chosen === '') {
            return null;
        }

        $normalized = $this->normalizeStepName($chosen);

        return in_array($normalized, $otpSteps, true) ? $normalized : null;
    }

    /**
     * Returns all enabled OTP steps (non-emailVerify) from requiredSetupSteps.
     *
     * @return array<int,string>
     */
    public function getAvailableOtpDrivers(): array
    {
        $steps = $this->getSteps();

        return array_values(array_filter($steps, fn($s) => $s !== 'emailVerify'));
    }

    /**
     * Check if a verification step is complete for the given identity data.
     *
     * @param string $step Step name
     * @param array<string, mixed> $data Identity data
     * @return bool
     */
    private function isStepComplete(string $step, array $data): bool
    {
        $fields = $this->getStepFields($step);

        switch ($step) {
            case 'emailVerify':
            case 'emailOtp':
                $verifiedField = $fields['emailVerified'] ?? 'email_verified_at';

                return !empty($data[$verifiedField]);

            case 'smsOtp':
                $phoneField = $fields['phone'] ?? 'phone';
                $verifiedAtField = $fields['phoneVerifiedAt'] ?? 'phone_verified_at';
                $hasPhone = !empty($data[$phoneField]) || !empty($data['phone_number']);
                $phoneOk = !empty($data[$verifiedAtField]) || !empty($data['phone_verified']);

                return $hasPhone && $phoneOk;

            case 'totp':
                $secretField = $fields['totpSecret'] ?? 'totp_secret';
                $verifiedField = $fields['totpVerified'] ?? 'totp_verified_at';
                $hasSecret = !empty($data[$secretField]) || !empty($data['totpSecret']);
                $isVerified = !empty($data[$verifiedField]);

                return $hasSecret && $isVerified;

            default:
                // Custom steps always considered pending
                return false;
        }
    }

    /**
     * Get field mappings for a step from configuration.
     *
     * @param string $step Step name
     * @return array<string, string>
     */
    private function getStepFields(string $step): array
    {
        $drivers = (array)($this->config['drivers'] ?? []);
        $definition = $drivers[$step] ?? [];

        if (!is_array($definition)) {
            return [];
        }

        return (array)($definition['fields'] ?? []);
    }

    /**
     * @param array<string, mixed> $definition
     * @return array<string, mixed>
     */
    private function buildDriverConfig(string $name, array $definition): array
    {
        $options = (array)($definition['options'] ?? []);
        $identityField = (string)($this->config['identity']['fields']['id'] ?? 'id');

        $otpLength = (int)($definition['otp']['length']
            ?? $options['length']
            ?? $this->config['otp']['length']
            ?? 6);

        $config = [
            'fields' => (array)($definition['fields'] ?? []),
            'otp' => [
                'length' => $otpLength,
                'ttl' => (int)($options['ttl'] ?? 300),
            ],
            'storage' => (array)($this->config['storage'] ?? []),
            'identityField' => $identityField,
            'options' => $options,
        ];

        if ($name === 'emailOtp') {
            $config['delivery'] = $options['delivery'] ?? null;
        }

        return $config;
    }

    /**
     * Check if step is enabled.
     *
     * @param string $step Step name
     * @return bool
     */
    private function isStepEnabled(string $step): bool
    {
        $drivers = (array)($this->config['drivers'] ?? []);
        $normalized = $this->normalizeStepName($step);
        $definition = $drivers[$normalized] ?? $drivers[$step] ?? null;
        if (!is_array($definition)) {
            return true;
        }
        if (!array_key_exists('enabled', $definition)) {
            return true;
        }

        return $definition['enabled'] !== false;
    }

    /**
     * @param mixed $class
     * @return class-string<\CakeVerification\Verificator\VerificationVerificatorInterface>|null
     */
    private function normalizeDriverClass(mixed $class): ?string
    {
        if (!is_string($class) || $class === '') {
            return null;
        }
        if (!class_exists($class)) {
            return null;
        }
        if (!is_subclass_of($class, VerificationVerificatorInterface::class)) {
            return null;
        }

        return $class;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractIdentityData(IdentityInterface $identity): array
    {
        $orig = $identity->getOriginalData();
        if (is_array($orig)) {
            return $orig;
        }
        if ($orig instanceof EntityInterface) {
            /** @var array<string, mixed> $data */
            $data = $orig->toArray();
            foreach ($orig->getHidden() as $field) {
                if (!array_key_exists($field, $data)) {
                    $data[$field] = $orig->get($field);
                }
            }

            return $data;
        }

        /** @var array<string, mixed> $data */
        $data = (array)$orig;

        return $data;
    }

    /**
     * @param class-string<\CakeVerification\Verificator\VerificationVerificatorInterface> $class
     * @param array<string, mixed> $config
     */
    private function instantiateDriver(string $class, array $config): VerificationVerificatorInterface
    {
        if ($class === SmsOtpVerificator::class) {
            $transport = $this->createSmsTransport();

            return new $class($transport, $config);
        }

        return new $class($config);
    }

    /**
     * @return \CakeVerification\Transport\Sms\TransportInterface
     */
    private function createSmsTransport(): TransportInterface
    {
        $smsConfig = (array)($this->config['sms'] ?? []);
        $name = (string)($smsConfig['defaultTransport'] ?? 'default');
        $transports = (array)($smsConfig['transports'] ?? []);

        return TransportFactory::create($name, $transports);
    }

    /**
     * @param array<string, mixed> $route
     * @return array<string, mixed>
     */
    private function normalizeRoute(array $route): array
    {
        foreach (['plugin', 'prefix'] as $key) {
            if (!array_key_exists($key, $route)) {
                continue;
            }
            if ($route[$key] === null || $route[$key] === false || $route[$key] === '') {
                unset($route[$key]);
            }
        }

        return $route;
    }
}
