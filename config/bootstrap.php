<?php
declare(strict_types=1);

use Cake\Core\Configure;
use Cake\Core\Configure\Engine\PhpConfig;
use Cake\I18n\I18n;
use Cake\I18n\MessagesFileLoader;
use Cake\I18n\Package;

// Snapshot of any app-provided Verification config (loaded before the plugin boots).
$appConfig = (array)Configure::read('Verification');

try {
    Configure::config('default', new PhpConfig());
    // Load plugin defaults (false = overwrite), then restore app overrides on top.
    Configure::load('CakeVerification.verification', 'default', false);
} catch (Exception $e) {
    exit($e->getMessage() . "\n");
}

// Merge: plugin defaults are base, app config wins on every key.
$config = $appConfig
    ? array_replace_recursive((array)Configure::read('Verification'), $appConfig)
    : (array)Configure::read('Verification');

// requiredSetupSteps is an ordered list — array_replace_recursive would merge array keys.
// If app explicitly set it, use it as-is (not merged).
if (array_key_exists('requiredSetupSteps', $appConfig)) {
    $config['requiredSetupSteps'] = $appConfig['requiredSetupSteps'];
}

/**
 * 1) Setup steps: allow CSV override via VERIFICATION_REQUIRED_SETUP_STEPS; fallback to sane defaults.
 */
$stepsEnv = env('VERIFICATION_REQUIRED_SETUP_STEPS', null);
if ($stepsEnv !== null && $stepsEnv !== '') {
    $csv = array_map('trim', explode(',', (string)$stepsEnv));
    $csv = array_values(array_filter($csv, static fn($s) => $s !== ''));
    if ($csv) {
        $config['requiredSetupSteps'] = $csv;
    }
}
if (
    !isset($config['requiredSetupSteps'])
    || !is_array($config['requiredSetupSteps'])
    || !$config['requiredSetupSteps']
) {
    $config['requiredSetupSteps'] = ['email_verify', 'totp', 'sms_otp'];
}

/**
 * 1b) Global OTP length: apply to email/sms unless overridden per-driver.
 */
$otpLength = $config['otp']['length'] ?? null;
if ($otpLength !== null) {
    $otpLength = (int)$otpLength;
    $config['drivers']['emailOtp']['otp']['length'] = $otpLength;
    $config['drivers']['smsOtp']['otp']['length'] = $otpLength;
}

/**
 * 2) Integer casts for known numeric paths.
 */
$ints = [
    'storage.maxAttempts',
    'storage.lockoutSeconds',
    'storage.resendCooldown',
    'storage.burst',
    'storage.periodSeconds',
    'otp.length',
    'drivers.totp.options.digits',
    'drivers.totp.options.period',
    'drivers.totp.options.drift',
    'drivers.emailOtp.options.ttl',
];
foreach ($ints as $path) {
    $parts = explode('.', $path);
    $found = true;
    $ref = &$config;
    foreach ($parts as $part) {
        if (!is_array($ref) || !array_key_exists($part, $ref)) {
            $found = false;
            break;
        }
        $ref = &$ref[$part];
    }
    if ($found) {
        $ref = (int)$ref;
    }
    unset($ref);
}

/**
 * 3) Boolean normalization for known boolean paths.
 */
$bools = [
    'enabled',
    'drivers.totp.enabled',
    'drivers.emailOtp.enabled',
    'drivers.smsOtp.enabled',
    'drivers.emailVerify.enabled',
];
foreach ($bools as $path) {
    $parts = explode('.', $path);
    $found = true;
    $ref = &$config;
    foreach ($parts as $part) {
        if (!is_array($ref) || !array_key_exists($part, $ref)) {
            $found = false;
            break;
        }
        $ref = &$ref[$part];
    }
    if ($found) {
        $ref = filter_var($ref, FILTER_VALIDATE_BOOLEAN);
    }
    unset($ref);
}

/**
 * 4) Database mapping sanity defaults (ensure required keys exist).
 */
$config['db'] ??= [];
$config['db']['users'] ??= [];
$config['db']['users'] += [
    'table' => 'users',
    'primaryKey' => 'id',
    'columns' => [],
];

$config['db']['users']['columns'] += [
    'email' => 'email',
    'phone' => 'phone',
    'totpSecret' => 'totp_secret',
    'emailVerifiedAt' => 'email_verified_at',
    'phoneVerifiedAt' => 'phone_verified_at',
    'totpVerifiedAt' => 'totp_verified_at',
    'phoneVerifiedFlag' => 'phone_verified',
    'verificationPreferences' => 'verification_preferences',
];

Configure::write('Verification', $config);

/**
 * 5) Translations. The plugin is named CakeVerification but messages use the
 * "verification" domain, so CakePHP's plugin-name convention never finds the
 * shipped locale files on its own. Register a loader for the domain:
 * an app-level resources/locales/<locale>/verification.po wins, the plugin
 * files are the fallback.
 */
I18n::config('verification', function (string $domain, string $locale): Package {
    // MessagesFileLoader searches the app's resources/locales first and the
    // plugin's second, so an app-level verification.po overrides the shipped
    // file — the same convention CakePHP uses for plugin-named domains.
    $package = (new MessagesFileLoader('CakeVerification.verification', $locale))();

    return $package instanceof Package ? $package : new Package('default');
});
