# Email Verification & Email OTP

Two email-based verification methods are available:

| Key | Type | Description |
|---|---|---|
| `emailVerify` | Setup only | Send a signed link by email; user clicks to confirm ownership |
| `emailOtp` | Setup / Login | Send a numeric one-time code by email |

---

## Database columns

### `emailVerify`

| Column | Type | Notes |
|---|---|---|
| `email` | VARCHAR | Required |
| `email_verification_token` | VARCHAR(191), nullable | Token stored by the plugin |
| `email_verification_token_expires` | DATETIME, nullable | Token expiry, stored by the plugin |
| `email_verified_at` | DATETIME, nullable | Set by the plugin when verified |

### `emailOtp`

| Column | Notes |
|---|---|
| `email` | Required — where the code is sent |

The OTP code itself is **not** stored in the database; it lives in the CakePHP
Cache (see [otp_storage.md](api/otp_storage.md)).

---

## Configuration

```php
// config/verification.php
'requiredSetupSteps' => ['emailVerify', 'emailOtp'],

'drivers' => [
    'emailVerify' => [
        'enabled' => true,
        'options'  => [
            'delivery' => null,   // null = use App\Mailer\UserMailer (see below)
        ],
    ],
    'emailOtp' => [
        'enabled' => true,
        'options'  => [
            'ttl'      => 600,    // code valid for 10 minutes
            'delivery' => null,   // null = use App\Mailer\UserMailer (see below)
        ],
    ],
],
```

Custom column names:

```php
'drivers' => [
    'emailVerify' => ['fields' => ['email' => 'user_email', 'emailVerified' => 'email_confirmed_at']],
    'emailOtp'    => ['fields' => ['email' => 'user_email', 'emailVerified' => 'email_confirmed_at']],
],
```

---

## `emailVerify` flow

### How it works

The plugin handles token generation, storage, and email delivery automatically.
Your app only needs to:

1. Call `$this->Verification->afterRegister($user)` after saving the user in `register()`.
2. Implement `verifyEmail()` to consume the token and call `$this->Verification->redirectAfterEmailVerify($user)`.

`afterRegister()` generates the token, saves it to the `users` table, and
sends the verification email. The user is **not** logged in at this point.

### Email delivery

The plugin calls `App\Mailer\UserMailer::emailVerify($user, $verifyUrl)` automatically
if the class exists. No extra configuration needed.

```php
// src/Mailer/UserMailer.php
public function emailVerify(object $user, string $verifyUrl): void
{
    $this->setTo($user->email)
         ->setSubject(__('Confirm your email address'))
         ->setViewVars(compact('user', 'verifyUrl'));
}
```

To use a custom delivery instead, set a callable under `drivers.emailVerify.options.delivery`:

```php
'emailVerify' => [
    'options' => [
        'delivery' => function (
            \Psr\Http\Message\ServerRequestInterface $request,
            \Authentication\IdentityInterface $identity,
            array $config,
        ): void {
            // token is already saved; send the email your own way
        },
    ],
],
```

### Controller actions

**`register()`**

```php
public function register(): ?Response
{
    $this->request->allowMethod(['get', 'post']);
    $user = $this->Users->newEmptyEntity();

    if ($this->request->is('post')) {
        $user = $this->Users->patchEntity($user, $this->request->getData());

        if ($this->Users->save($user)) {
            // Plugin generates token, saves it, sends verification email
            $this->Verification->afterRegister($user);
            $this->Flash->success(__('Check your email to complete registration.'));

            return $this->redirect(['action' => 'login']);
        }
        $this->Flash->error(__('Could not save the account.'));
    }

    $this->set(compact('user'));
}
```

**`verifyEmail()`**

```php
public function verifyEmail(string $token): ?Response
{
    $this->request->allowMethod('get');

    $user = $this->Users->find()
        ->where(['email_verification_token' => $token])
        ->firstOrFail();

    // Plugin validates expiry, saves email_verified_at, clears token
    return $this->Verification->redirectAfterEmailVerify($user);
}
```

`redirectAfterEmailVerify()` handles two cases:
- User is **logged in** → sets the login-flow flag and redirects to the next pending step.
- User is **not logged in** → redirects to the login page.

**`pending()`** — "check your inbox" page

```php
public function pending(): ?Response
{
    $identity = $this->request->getAttribute('identity');
    if ($identity === null) {
        return $this->redirect(['action' => 'login']);
    }

    $verification = $this->Verification->result();
    if (!$verification->hasStep('email_verify')) {
        return $this->redirect($this->Verification->getNextUrl() ?? '/');
    }

    $this->set(compact('verification'));
}
```

**`resendEmailVerification()`** — optional

```php
public function resendEmailVerification(): ?Response
{
    $this->request->allowMethod('post');
    $identity = $this->request->getAttribute('identity');
    if ($identity === null) {
        return $this->redirect(['action' => 'login']);
    }

    // Plugin clears old token, generates new one, resends email
    $this->Verification->resendEmailVerificationLink();
    $this->Flash->success(__('Verification email resent.'));

    return $this->redirect($this->Verification->getNextUrl() ?? '/');
}
```

---

## `emailOtp` flow

### How it works

The component auto-sends the code on the GET request to `verify()` and
verifies the submitted code on POST. Your controller action is a single call:

```php
public function verify(?string $step = null): ?Response
{
    $this->request->allowMethod(['get', 'post']);
    $response = $this->Verification->handleVerify($step);
    if ($response !== null) {
        return $response;
    }
    // view variables $verification and $step are set automatically
}
```

### Email delivery

The plugin calls `App\Mailer\UserMailer::emailOtp($user, $code)` automatically
if the class exists.

```php
// src/Mailer/UserMailer.php
public function emailOtp(object $user, string $code): void
{
    $this->setTo($user->email)
         ->setSubject(__('Your login code'))
         ->setViewVars(compact('user', 'code'));
}
```

To use a custom delivery instead:

```php
'emailOtp' => [
    'options' => [
        'ttl'      => 600,
        'delivery' => function (
            \Psr\Http\Message\ServerRequestInterface $request,
            \Authentication\IdentityInterface $identity,
            string $code,
            array $config,
        ): void {
            $user = $identity->getOriginalData();
            // send $code to $user->email your own way
        },
    ],
],
```

---

## Email templates

Place templates in your app to override the plugin defaults:

```
templates/email/text/email_verify.php
templates/email/html/email_verify.php
templates/email/text/email_otp.php
templates/email/html/email_otp.php
```

In your mailer, point the view builder at the `Verification` plugin to use the
bundled templates:

```php
$this->viewBuilder()
     ->setPlugin('CakeVerification')
     ->setTemplate('email_otp');
```

Omit `setPlugin()` to use your own templates instead.

---

## Notes

- `emailVerify` always runs first and blocks all other setup steps until confirmed.
- `emailOtp` does **not** mark `email_verified_at`; it is a standalone OTP step.
  If you want email OTP to double as email ownership proof, map
  `drivers.emailOtp.fields.emailVerified` to the `email_verified_at` column.
- Rate limiting (resend cooldown, lockout) applies to `emailOtp`.
  See [otp_storage.md](api/otp_storage.md) for details.

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
