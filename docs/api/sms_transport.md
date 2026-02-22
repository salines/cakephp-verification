# SMS Transport API

Reference for implementing a custom SMS transport.

For the basic SMS OTP setup (configuration, controller actions, phone enrollment)
see [sms_verification.md](../sms_verification.md).

---

## TransportInterface

Every SMS transport must implement `Verification\Transport\Sms\TransportInterface`:

```php
namespace Verification\Transport\Sms;

interface TransportInterface
{
    public function send(Message $message): Result;
}
```

### `Message`

| Property | Type | Description |
|---|---|---|
| `$recipient` | `string` | Destination phone number (E.164 recommended) |
| `$body` | `string` | Full message text including the OTP code |
| `$sender` | `string\|null` | Sender ID or number (optional) |

### `Result`

| Property | Type | Description |
|---|---|---|
| `$success` | `bool` | `true` if the message was accepted by the provider |
| `$error` | `string\|null` | Error description on failure |
| `$message` | `Message\|null` | The original message |
| `$providerId` | `string\|null` | Provider-assigned message ID |
| `$statusCode` | `int\|null` | HTTP or provider status code |
| `$retryAfter` | `int\|null` | Seconds to wait before retrying (rate-limit hint) |

---

## Implementing a custom transport

```php
namespace App\Sms;

use Verification\Transport\Sms\Message;
use Verification\Transport\Sms\Result;
use Verification\Transport\Sms\TransportInterface;

class TwilioTransport implements TransportInterface
{
    public function __construct(private array $options = []) {}

    public function send(Message $message): Result
    {
        $result = new Result();

        // call Twilio API with $message->recipient and $message->body ...

        $result->success = true;
        $result->providerId = 'twilio-message-id';

        return $result;
    }
}
```

If the transport constructor requires no arguments, it is instantiated directly.
If it requires one argument, the `options` array from the transport config is passed.

---

## Registering the transport

```php
// config/verification.php
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

---

## DummyTransport (development)

The plugin ships a `DummyTransport` that logs the message and stores it in the
CakePHP `default` cache instead of sending a real SMS. Use it during development:

```php
'sms' => [
    'defaultTransport' => 'dummy',
],
```

`VerificationHelper::lastSmsCode()` reads the cached message and returns the
numeric code — convenient for checking the OTP without a real phone.
See [../verification_helper.md](../verification_helper.md).

---

## Documentation

| Topic | File |
|---|---|
| README | [../../README.md](../../README.md) |
| Verification flows (setup, login, OTP choice) | [../verification_flow.md](../verification_flow.md) |
| Installation | [../installation.md](../installation.md) |
| Configuration reference | [../configuration.md](../configuration.md) |
| Environment variables | [../env.md](../env.md) |
| UsersController actions | [../users_controller.md](../users_controller.md) |
| VerificationComponent | [../verification_component.md](../verification_component.md) |
| VerificationHelper | [../verification_helper.md](../verification_helper.md) |
| Email verification & Email OTP | [../email_verification.md](../email_verification.md) |
| SMS OTP | [../sms_verification.md](../sms_verification.md) |
| TOTP | [../totp_verification.md](../totp_verification.md) |
| Enable / disable individual steps | [../verificator_enable_disable.md](../verificator_enable_disable.md) |
| API reference index | [index.md](index.md) |
