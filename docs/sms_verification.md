# SMS OTP Verification

`smsOtp` sends a numeric one-time code by SMS. It can be used as:

- a **setup step** (list it in `requiredSetupSteps`) — verifies the phone number once
- a **login step** (set `login.step = 'smsOtp'`) — 2FA on every login

## Prerequisites

See [installation.md](installation.md) for the full setup guide.

## Database fields

| Column | Type | Notes |
|---|---|---|
| `phone` | VARCHAR(32), nullable | Phone number to send OTP to |
| `phone_verified_at` | DATETIME, nullable | Set after first successful OTP |
| `phone_verified` | TINYINT(1), default 0 | Optional boolean flag |

## Configuration

```php
// config/verification.php
<?php
return [
    'Verification' => [
        // as a setup step:
        'requiredSetupSteps' => ['emailVerify', 'smsOtp'],

        // OR as the login 2FA step:
        'login' => [
            'enabled' => true,
            'step'    => 'smsOtp',
        ],

        'drivers' => [
            'smsOtp' => [
                'options' => [
                    'ttl'                => 300,    // code TTL in seconds
                    'normalizeE164'      => true,   // normalize phone to E.164
                    'defaultCountryCode' => '+385', // used when normalizing
                ],
            ],
        ],

        'sms' => [
            'defaultTransport' => 'dummy',
            'transports' => [
                'dummy' => [
                    'className' => \Verification\Transport\Sms\Driver\DummyTransport::class,
                ],
            ],
        ],

        'storage' => [
            'maxAttempts'    => 5,
            'lockoutSeconds' => 900,
            'resendCooldown' => 60,
        ],
    ],
];
```

### Custom field names

```php
'drivers' => [
    'smsOtp' => [
        'fields' => [
            'phone'           => 'mobile',
            'phoneVerifiedAt' => 'mobile_verified_at',
        ],
    ],
],
```

---

## Controller actions

The component exposes `handleVerify()` and `handleEnrollPhone()` to keep your
controller thin.

### AppController

```php
public function initialize(): void
{
    parent::initialize();
    $this->loadComponent('Authentication.Authentication');
    $this->loadComponent('Verification.Verification', ['requireVerified' => true]);
    $this->Verification->allowUnverified(['login', 'logout', 'pending']);
}
```

### enrollPhone

Collect the phone number before OTP verification. Required when `smsOtp` is in
`requiredSetupSteps` and the user has no phone on file.

```php
public function enrollPhone(): void
{
    $this->request->allowMethod(['get', 'post', 'put', 'patch']);
    $response = $this->Verification->handleEnrollPhone();
    if ($response !== null) {
        return $response;
    }
    // $user is set on the view automatically
}
```

The component:
1. Loads the user from the database.
2. On POST/PUT/PATCH: patches and saves the user, refreshes the Authentication identity,
   then redirects to the next pending step.
3. On GET: sets `$user` on the view.

Your template should have a form with a `phone` field (or whatever your mapped field is).

### verify

Handles OTP entry for both setup and login flows.

```php
public function verify(?string $step = null): void
{
    $this->request->allowMethod(['get', 'post']);
    $response = $this->Verification->handleVerify($step);
    if ($response !== null) {
        return $response;
    }
    // $verification and $step are set on the view automatically
}
```

The component auto-sends the OTP code on the first GET request and handles:
- POST with correct code → marks verified, redirects to next step
- POST with `resend=1` → re-sends the code (subject to cooldown)
- Wrong code → flash error, stays on the verify page

---

## Phone number handling

If `normalizeE164` is enabled (recommended for production), the driver formats
the stored phone number as `+<country><national>` before handing it to the
transport. Example: `0912345678` + `+385` → `+385912345678`.

If you already store E.164 numbers in the database, set `normalizeE164 => false`.

---

## SMS transports

SMS delivery is abstracted via `TransportInterface`. Register transports under
`Verification.sms.transports`.

### DummyTransport (development only)

Stores the last message in the CakePHP cache — does **not** send a real SMS.
Read the last code in a template (development only):

```php
// templates/Users/verify.php
if (\Cake\Core\Configure::read('debug')) {
    echo $this->Verification->lastSmsCode();
}
```

### Custom transport

Implement `Verification\Transport\Sms\TransportInterface`:

```php
namespace App\Sms;

use Verification\Transport\Sms\Message;
use Verification\Transport\Sms\Result;
use Verification\Transport\Sms\TransportInterface;

final class TwilioTransport implements TransportInterface
{
    public function __construct(private readonly array $options = []) {}

    public function send(Message $message): Result
    {
        // $message->recipient  — destination phone number (E.164 if normalized)
        // $message->body       — message text containing the OTP code

        // Example using the Twilio SDK:
        // $client = new \Twilio\Rest\Client($this->options['sid'], $this->options['token']);
        // $client->messages->create($message->recipient, [
        //     'from' => $this->options['from'],
        //     'body' => $message->body,
        // ]);

        $result = new Result();
        $result->success = true;
        $result->message = $message;

        return $result;
    }
}
```

Register it in your config:

```php
'sms' => [
    'defaultTransport' => 'twilio',
    'transports' => [
        'twilio' => [
            'className' => \App\Sms\TwilioTransport::class,
            'options'   => [
                'sid'   => env('TWILIO_SID'),
                'token' => env('TWILIO_TOKEN'),
                'from'  => env('TWILIO_FROM'),
            ],
        ],
    ],
],
```

`TransportFactory` passes the `options` array to the transport constructor when
the constructor has at least one required parameter.

---

## Notes

- Rate limiting (resend cooldown, lockout) applies to `smsOtp` like all OTP steps.
  See [otp_storage.md](api/otp_storage.md).
- When `smsOtp` is both a setup step and the login step, completing the setup step
  satisfies `isVerified()` for that step permanently. The login flow triggers
  separately via the session on each new login.

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
