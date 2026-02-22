# Verification Flows

This document explains when and how the plugin applies verification, and
describes every flow a user can go through.

---

## When verification runs

The `VerificationComponent` listens on `Controller.startup` (configurable).
On every request it checks the authenticated identity against the configured
`requiredSetupSteps`. If any step is pending the user is redirected
automatically — they cannot reach any other controller action until all steps
are complete.

Actions that must be reachable without a completed verification (e.g. the
verify form itself) are whitelisted with `allowUnverified()`:

```php
$this->Verification->allowUnverified([
    'login', 'logout', 'register',
    'verify', 'enroll', 'enrollPhone',
    'pending', 'verifyEmail', 'resendEmailVerification',
    'chooseVerification',
]);
```

---

## Setup flow (runs once after registration)

This flow runs the first time a user accesses the app after registering.

```
register()
  └─ save user
  └─ afterRegister()        ← sends email verification link; user NOT logged in
  └─ redirect to login page

login()
  └─ user submits credentials
  └─ Authentication identifies user
  └─ requiresNextStep() → true
  └─ redirect → next pending step
```

### Step 1 — email verification (`emailVerify`)

`emailVerify` always runs first and blocks all other steps until confirmed.

```
User receives email with a signed link
  └─ clicks link → verifyEmail()
       └─ saves email_verified_at
       └─ user is NOT logged in → redirect to login page

User logs in
  └─ next pending step resolved
```

### Step 2 — OTP enrollment

After email is confirmed, the plugin resolves the next pending OTP step.

**Single OTP driver configured** (`requiredSetupSteps = ['emailVerify', 'emailOtp']`):

```
login()
  └─ redirect → verify() with emailOtp step
       └─ code sent automatically on GET
       └─ user enters code → marked as enrolled
       └─ redirect → onVerifiedRoute (app)
```

**Multiple OTP drivers configured** (`requiredSetupSteps = ['emailVerify', 'emailOtp', 'totp']`):

```
login()
  └─ redirect → chooseVerification()   ← user picks their preferred method
       └─ saves chosen driver to verification_preferences
       └─ redirect → next pending step for chosen driver

  ┌─ emailOtp chosen ──────────────────────────────────────────┐
  │  verify()                                                   │
  │    └─ OTP sent by email                                     │
  │    └─ user enters code → enrolled → onVerifiedRoute        │
  └─────────────────────────────────────────────────────────────┘

  ┌─ smsOtp chosen ────────────────────────────────────────────┐
  │  enrollPhone()                                              │
  │    └─ user enters phone number → saved                      │
  │  verify()                                                   │
  │    └─ OTP sent by SMS                                       │
  │    └─ user enters code → enrolled → onVerifiedRoute        │
  └─────────────────────────────────────────────────────────────┘

  ┌─ totp chosen ──────────────────────────────────────────────┐
  │  enroll()                                                   │
  │    └─ TOTP secret generated, QR code displayed             │
  │    └─ user scans QR with authenticator app                  │
  │    └─ user enters first code → enrolled → onVerifiedRoute  │
  └─────────────────────────────────────────────────────────────┘
```

---

## Login flow (runs on every subsequent login)

After the setup flow is complete the user has a chosen OTP method stored in
`verification_preferences`. On every login the plugin checks this and requires
a fresh OTP code.

```
login()
  └─ Authentication identifies user
  └─ requiresNextStep() → true (login-time OTP pending)
  └─ redirect → verify() (or enroll() / enrollPhone() if needed)
       └─ user enters OTP code
       └─ verified → redirect → onVerifiedRoute (app)
```

The login OTP step uses the same driver the user enrolled in during setup.
If the user somehow has no driver stored (e.g. data was cleared), the plugin
falls back to redirecting to `chooseVerification` again.

---

## OTP method selection (`chooseVerification`)

This screen appears when:

- `requiredSetupSteps` contains **more than one OTP driver** (e.g.
  `['emailVerify', 'emailOtp', 'smsOtp', 'totp']`), **and**
- the user has not yet chosen a method.

The available choices shown to the user are exactly the OTP drivers listed in
`requiredSetupSteps`. The user picks one; the choice is saved to
`verification_preferences` in your `users` table and all other OTP drivers are
skipped for this user from that point on.

```php
// config/verification.php — offer three methods, user picks one
'requiredSetupSteps' => ['emailVerify', 'emailOtp', 'smsOtp', 'totp'],
```

```php
// UsersController
public function chooseVerification(): ?Response
{
    return $this->Verification->handleChooseVerification();
}
```

The `handleChooseVerification()` handler sets `$availableDrivers` and
`$selectedDriver` on the view so you can render the selection form. See
[users_controller.md](users_controller.md) for the full action and template
example.

---

## Summary table

| Situation | Redirect destination |
|---|---|
| Email not yet confirmed | `pendingRoute` ("check your inbox") |
| Multiple OTP drivers, none chosen | `chooseVerificationRoute` |
| `smsOtp` chosen, no phone saved | `enrollPhoneRoute` |
| `totp` chosen, no secret generated | `enrollRoute` |
| OTP pending (setup or login) | `nextRoute` (verify form) |
| All steps complete | `onVerifiedRoute` (your app) |

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
