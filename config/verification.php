<?php
declare(strict_types=1);

use CakeVerification\Transport\Sms\Driver\DummyTransport;
use CakeVerification\Verificator\Driver\EmailOtpVerificator;
use CakeVerification\Verificator\Driver\EmailVerifyVerificator;
use CakeVerification\Verificator\Driver\SmsOtpVerificator;
use CakeVerification\Verificator\Driver\TotpVerificator;

/**
 * salines/verification — default configuration.
 *
 * Notes:
 * - The plugin ships no UI/templates; Application (App) provides controllers/views/emails.
 * - Type normalization that is not trivial (CSV→array, int casts) is done in the plugin bootstrap.
 * - All comments are in English as per project rules.
 * - Default routes target Users controller (override in app_local.php if needed).
 * - Drivers use ALIAS → COLUMN mapping (left side is the plugin’s logical alias, right side is your DB column).
 */

return [
    'Verification' => [
        // Master toggle
        'enabled' => filter_var(env('VERIFICATION_ENABLED', true), FILTER_VALIDATE_BOOLEAN),

        // Ordered verification setup steps. App can override via app_local.php or VERIFICATION_REQUIRED_SETUP_STEPS (CSV).
        'requiredSetupSteps' => ['emailVerify', 'totp', 'smsOtp'],

        // Global OTP settings
        'otp' => [
            'length' => env('VERIFICATION_OTP_LENGTH', 6),
        ],

        // Routing targets (logical destinations). App must define actual routes/controllers/views.
        'routing' => [
            'nextRoute' => [
                'plugin' => false,
                'prefix' => env('VERIFICATION_ROUTE_PREFIX', false),
                'controller' => env('VERIFICATION_ROUTE_CONTROLLER', 'Users'),
                'action' => env('VERIFICATION_ROUTE_ACTION', 'verify'),
            ],
            'pendingRoute' => [
                'plugin' => false,
                'prefix' => env('VERIFICATION_PENDING_PREFIX', false),
                'controller' => env('VERIFICATION_PENDING_CONTROLLER', 'Users'),
                'action' => env('VERIFICATION_PENDING_ACTION', 'pending'),
            ],
            'onVerifiedRoute' => [
                'plugin' => false,
                'prefix' => env('VERIFICATION_VERIFIED_PREFIX', false),
                'controller' => env('VERIFICATION_VERIFIED_CONTROLLER', 'Users'),
                'action' => env('VERIFICATION_VERIFIED_ACTION', 'index'),
            ],
            'enrollRoute' => [
                'plugin' => false,
                'prefix' => env('VERIFICATION_ENROLL_PREFIX', false),
                'controller' => env('VERIFICATION_ENROLL_CONTROLLER', 'Users'),
                'action' => env('VERIFICATION_ENROLL_ACTION', 'enroll'),
            ],
            'enrollPhoneRoute' => [
                'plugin' => false,
                'prefix' => env('VERIFICATION_ENROLL_PHONE_PREFIX', false),
                'controller' => env('VERIFICATION_ENROLL_PHONE_CONTROLLER', 'Users'),
                'action' => env('VERIFICATION_ENROLL_PHONE_ACTION', 'enrollPhone'),
            ],
            'chooseVerificationRoute' => [
                'plugin' => false,
                'prefix' => env('VERIFICATION_CHOOSE_PREFIX', false),
                'controller' => env('VERIFICATION_CHOOSE_CONTROLLER', 'Users'),
                'action' => env('VERIFICATION_CHOOSE_ACTION', 'chooseVerification'),
            ],
        ],

        // Identity mapping for OTP storage keys.
        'identity' => [
            'fields' => [
                'id' => env('VERIFICATION_ID_FIELD', 'id'),
            ],
        ],

        // Database mapping for Users table: the plugin will read/write these columns when applicable.
        // App can remap any column name to match its schema.
        'db' => [
            'users' => [
                'columns' => [
                    // Contact channels
                    'email' => env('VERIFICATION_DB_COL_EMAIL', 'email'),
                    'phone' => env('VERIFICATION_DB_COL_PHONE', 'phone'),

                    // TOTP provisioning
                    'totpSecret' => env('VERIFICATION_DB_COL_TOTP_SECRET', 'totp_secret'),

                    // Status flags/timestamps (set by the plugin once a step has been verified)
                    'emailVerifiedAt' => env('VERIFICATION_DB_COL_EMAIL_VERIFIED_AT', 'email_verified_at'), // datetime
                    'phoneVerifiedAt' => env('VERIFICATION_DB_COL_PHONE_VERIFIED_AT', 'phone_verified_at'), // datetime
                    'totpVerifiedAt' => env('VERIFICATION_DB_COL_TOTP_VERIFIED_AT', 'totp_verified_at'),
                    'phoneVerifiedFlag' => env('VERIFICATION_DB_COL_PHONE_VERIFIED', 'phone_verified'),

                    // User's chosen OTP driver (JSON column)
                    'verificationPreferences' => env('VERIFICATION_DB_COL_PREFS', 'verification_preferences'),
                ],
            ],
        ],

        // Storage and throttling (values normalized to int in bootstrap)
        'storage' => [
            'cacheConfig' => env('VERIFICATION_CACHE_CONFIG', 'verification'),
            'stepCacheConfig' => [],
            'maxAttempts' => env('VERIFICATION_MAX_ATTEMPTS', 5),
            'lockoutSeconds' => env('VERIFICATION_LOCKOUT', 900),
            'resendCooldown' => env('VERIFICATION_RESEND_COOLDOWN', 60),
            'burst' => env('VERIFICATION_OTP_BURST', 0),
            'periodSeconds' => env('VERIFICATION_OTP_PERIOD', 0),
        ],

        // Crypto: flat config consumed by CryptoFactory::create().
        // driver: 'aes-gcm' (default) or 'sodium'
        // key:    raw binary key; use base64_decode() around the env value.
        //         Generate a key: bin2hex(random_bytes(32)) or use CryptoFactory::generateKey('aes-gcm').
        'crypto' => [
            'driver' => env('VERIFICATION_CRYPTO_DRIVER', 'aes-gcm'),
            'key' => base64_decode((string)env('VERIFICATION_CRYPTO_KEY', '')),
        ],

        /**
         * Verificators (logic only; no views)
         *
         * Each driver defines:
         * - enabled: feature toggle
         * - className: concrete driver class
         * - fields:  ALIAS → COLUMN mapping used by the driver
         * - options: driver-specific options (length/ttl/etc.)
         *
         * Aliases are stable across apps; only the right-hand column names change per schema.
         */
        'drivers' => [
            'emailVerify' => [
                'enabled' => filter_var(env('VERIFICATION_EMAIL_VERIFY_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
                'className' => EmailVerifyVerificator::class,
                'fields' => [
                    'email' => env('VERIFICATION_EMAIL_FIELD', 'email'),
                    'emailVerified' => env('VERIFICATION_DB_COL_EMAIL_VERIFIED_AT', 'email_verified_at'),
                ],
                'options' => [
                    'delivery' => null,
                ],
            ],

            'emailOtp' => [
                'enabled' => filter_var(env('VERIFICATION_EMAIL_OTP_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
                'className' => EmailOtpVerificator::class,
                'fields' => [
                    // Identity / DB column names for email step
                    'email' => env('VERIFICATION_EMAIL_FIELD', 'email'),
                    'emailVerified' => env('VERIFICATION_DB_COL_EMAIL_VERIFIED_AT', 'emailVerifiedAt'),
                ],
                'options' => [
                    'ttl' => env('VERIFICATION_EMAIL_TTL', 600),
                ],
            ],

            'smsOtp' => [
                'enabled' => filter_var(env('VERIFICATION_SMS_OTP_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
                'className' => SmsOtpVerificator::class,
                'fields' => [
                    // Identity / DB column names for SMS step
                    'phone' => env('VERIFICATION_PHONE_FIELD', 'phone'),
                    'phoneVerifiedAt' => env('VERIFICATION_DB_COL_PHONE_VERIFIED_AT', 'phone_verified_at'),
                ],
                'options' => [
                    'ttl' => env('VERIFICATION_SMS_TTL', 300),
                    'messageTemplate' => env(
                        'VERIFICATION_SMS_MESSAGE',
                        'Your verification code is {code}. It expires in {ttl} minutes.',
                    ),
                    'senderId' => env('VERIFICATION_SMS_SENDER', 'YourApp'),
                    'normalizeE164' => filter_var(env('VERIFICATION_SMS_E164', false), FILTER_VALIDATE_BOOLEAN),
                    'defaultCountryCode' => env('VERIFICATION_SMS_COUNTRY_CODE', ''),
                ],
            ],

            'totp' => [
                'enabled' => filter_var(env('VERIFICATION_TOTP_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
                'className' => TotpVerificator::class,
                'fields' => [
                    // Identity / DB column names for TOTP step
                    'totpSecret' => env('VERIFICATION_DB_COL_TOTP_SECRET', 'totp_secret'),
                    'totpVerified' => env('VERIFICATION_DB_COL_TOTP_VERIFIED_AT', 'totp_verified_at'), // optional; if absent, presence of secret may be treated as verified
                ],
                'options' => [
                    'digits' => env('VERIFICATION_TOTP_DIGITS', 6),
                    'period' => env('VERIFICATION_TOTP_PERIOD', 30),
                    'algorithm' => env('VERIFICATION_TOTP_ALGO', 'sha1'),
                    'drift' => env('VERIFICATION_TOTP_DRIFT', 1),
                ],
            ],
        ],

        // Driver class map used by VerificationService to resolve step names to driver classes.
        'driverMap' => [
            'emailVerify' => EmailVerifyVerificator::class,
            'emailOtp' => EmailOtpVerificator::class,
            'smsOtp' => SmsOtpVerificator::class,
            'totp' => TotpVerificator::class,
        ],

        // SMS transports (plugin ships DummyTransport; real transports added by App)
        'sms' => [
            'defaultTransport' => env('VERIFICATION_SMS_TRANSPORT', 'default'),
            'transports' => [
                'default' => [
                    'className' => DummyTransport::class,
                ],
            ],
        ],

    ],
];
