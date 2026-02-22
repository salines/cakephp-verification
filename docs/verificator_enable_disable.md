# Enabling and Disabling Individual Steps

Every step in `requiredSetupSteps` and the `login` flow can be enabled or disabled
without removing it from the configuration. This is useful for feature flags, A/B
testing, or gradual rollouts.

## Disabling a setup step

Add `'enabled' => false` to the driver definition:

```php
'drivers' => [
    'smsOtp' => [
        'enabled' => false,   // step is skipped entirely
        'options' => ['ttl' => 300],
    ],
],
```

When a step is disabled:

- `VerificationService::getDriver('smsOtp')` returns `null`.
- `VerificationService::getSteps()` skips it.
- It is never listed in `VerificationResult::pendingSteps()`.
- Users are not redirected to it.

## Disabling the login flow (step-up 2FA)

To skip 2FA on every login, disable the plugin entirely via the master toggle:

```php
// config/verification.php
'enabled' => false,
```

Or disable only the OTP driver the user enrolled in:

```php
'drivers' => [
    'totp' => ['enabled' => false],
],
```

When a driver is disabled, the login-time OTP step for users who enrolled in that
driver is skipped.

## Removing a step from `requiredSetupSteps`

Simply omit it from the array:

```php
// Only email verification is required; TOTP and SMS are optional.
'requiredSetupSteps' => ['emailVerify'],
```

## Runtime enable / disable (env-based)

You can drive `enabled` from an environment variable:

```php
'drivers' => [
    'smsOtp' => [
        'enabled' => (bool)env('FEATURE_SMS_OTP', false),
    ],
],
```

## Step order

Steps in `requiredSetupSteps` are evaluated in the order listed:

```php
'requiredSetupSteps' => ['emailVerify', 'totp', 'smsOtp'],
```

1. `emailVerify` is always evaluated first and blocks all other steps until complete.
2. `totp` is evaluated next.
3. `smsOtp` is evaluated last.

Disabled steps are excluded before the order is applied, so removing `totp` does
not shift the position of `smsOtp`.

## Checking the effective step list

```php
// Returns only enabled steps in configured order.
$steps = $this->Verification->getService()->getSteps();
```

## Example: disable TOTP in development

```php
// config/app_local.php  (dev overrides)
Configure::write('Verification.drivers.totp.enabled', false);
```

Or use a conditional in `config/verification.php`:

```php
'drivers' => [
    'totp' => [
        'enabled' => !Configure::read('debug'),
        'options' => ['issuer' => 'MyApp'],
    ],
],
```

---

## Documentation

| Topic | File |
|---|---|
| README | [../README.md](../README.md) |
| Verification flows (setup, login, OTP choice) | [verification_flow.md](verification_flow.md) |
| Installation | [installation.md](installation.md) |
| Configuration reference | [configuration.md](configuration.md) |
| Environment variables | [env.md](env.md) |
| UsersController actions | [users_controller.md](users_controller.md) |
| VerificationComponent | [verification_component.md](verification_component.md) |
| VerificationHelper | [verification_helper.md](verification_helper.md) |
| Email verification & Email OTP | [email_verification.md](email_verification.md) |
| SMS OTP | [sms_verification.md](sms_verification.md) |
| TOTP | [totp_verification.md](totp_verification.md) |
| Enable / disable individual steps | [verificator_enable_disable.md](verificator_enable_disable.md) |
| API reference | [api/index.md](api/index.md) |
