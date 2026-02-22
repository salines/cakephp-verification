# Verification Component

`VerificationComponent` is the single entry point between your controllers and
the plugin. It listens on CakePHP events, intercepts every request, and
redirects unauthenticated or unverified users to the appropriate step
automatically — without any code in your individual controller actions.

Beyond the auto-redirect check, the component exposes high-level handler
methods (`handleVerify`, `handleEnroll`, `handleEnrollPhone`,
`handleChooseVerification`) that encapsulate all verification logic, so your
controller actions stay thin. It also manages OTP delivery (via
`App\Mailer\UserMailer` or a custom callable), token generation for email
verification, TOTP secret encryption/decryption, and writing verification
results back to the `users` table.

## Component options

| Option | Type | Default | Description |
|---|---|---|---|
| `verificationCheckEvent` | string | `'Controller.startup'` | CakePHP event on which the redirect check runs. Use `'Controller.initialize'` if you need the check to happen before `beforeFilter` callbacks. |
| `unverifiedActions` | array | `[]` | List of action names that are allowed through without completing verification. Equivalent to calling `allowUnverified()` in `initialize()`. |

## Component methods

| Method | Returns | Use in | Description |
|---|---|---|---|
| `handleVerify(?string $step)` | `?Response` | `verify()` | Sends OTP on GET, verifies code on POST, marks step and redirects on success |
| `handleEnroll()` | `?Response` | `enroll()` | Generates TOTP secret, displays QR, verifies first code on POST |
| `handleEnrollPhone()` | `?Response` | `enrollPhone()` | Saves phone number on POST, redirects to next step |
| `handleChooseVerification()` | `?Response` | `chooseVerification()` | Saves chosen OTP driver on POST, redirects to next step |
| `afterRegister(EntityInterface $user)` | `void` | `register()` | Generates token, saves it, sends email verification link |
| `redirectAfterEmailVerify(EntityInterface $user)` | `Response` | `verifyEmail()` | Redirects to next step (logged in) or login page (not logged in) |
| `resendEmailVerificationLink()` | `void` | `resendEmailVerification()` | Clears old token, generates new one, resends email |
| `requiresNextStep()` | `bool` | `login()` | Returns `true` if the user has pending verification steps |
| `getNextUrl()` | `?string` | `login()` | Returns the URL for the next pending step |
| `result()` | `VerificationResult` | anywhere | Returns the current verification state for the identity |
| `allowUnverified(array $actions)` | `static` | `initialize()` | Marks actions accessible without completed verification |

---

## Controller setup

### AppController

Load the component alongside `Authentication` in `AppController::initialize()`:

```php
// src/Controller/AppController.php
public function initialize(): void
{
    parent::initialize();
    $this->loadComponent('Flash');
    $this->loadComponent('Authentication.Authentication');
    $this->loadComponent('Verification.Verification');
}
```

### UsersController

In `UsersController::initialize()` declare which actions are accessible without
a completed authentication or verification:

```php
// src/Controller/UsersController.php
public function initialize(): void
{
    parent::initialize();

    $this->Authentication->allowUnauthenticated([
        'login',
        'register',
        'verifyEmail',
    ]);

    $this->Verification->allowUnverified([
        'login',
        'logout',
        'register',
        'verify',
        'enroll',
        'enrollPhone',
        'pending',
        'verifyEmail',
        'resendEmailVerification',
        'chooseVerification',
    ]);
}
```

See [users_controller.md](users_controller.md) for the full implementation of every required action.

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
