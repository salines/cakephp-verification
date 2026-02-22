# UsersController — Required Actions

The plugin ships no controllers. You must implement the following actions in
your `UsersController`. The component provides high-level handlers so each
action stays thin.

See [verification_component.md](verification_component.md) for `AppController`
and `UsersController::initialize()` setup.

---

## login

Handles credential check. After a valid login, if the user still has pending
verification steps (email confirm, OTP setup, or login-time 2FA) the component
redirects them automatically.

```php
public function login()
{
    $this->request->allowMethod(['get', 'post']);
    $result = $this->Authentication->getResult();
    if ($result->isValid()) {
        if ($this->Verification->requiresNextStep()) {
            return $this->redirect($this->Verification->getNextUrl() ?? '/');
        }
        return $this->redirect($this->Authentication->getLoginRedirect() ?? '/');
    }
    if ($this->request->is('post') && !$result->isValid()) {
        $this->Flash->error(__('Invalid credentials.'));
    }
}
```

---

## register

Creates the user and sends the email verification link. The user is **not**
logged in after registration — they must click the link and then log in.

```php
public function register()
{
    $user = $this->Users->newEmptyEntity();
    if ($this->request->is('post')) {
        $user = $this->Users->patchEntity($user, $this->request->getData());
        if ($this->Users->save($user)) {
            $this->Verification->afterRegister($user); // sends email verify link
            $this->Flash->success(__('Check your email to confirm your address.'));
            return $this->redirect(['action' => 'login']);
        }
        $this->Flash->error(__('Registration failed. Please try again.'));
    }
    $this->set(compact('user'));
}
```

`afterRegister(EntityInterface $user)` — sends the email verification link
without logging the user in. It is a no-op if the `emailVerify` driver is
disabled.

---

## verifyEmail

Consumes the token from the link. Marks `email_verified_at` in the database.
Redirects to login if the user is not yet logged in, or to the next pending
step if they are.

```php
public function verifyEmail(string $token)
{
    $now = \Cake\I18n\DateTime::now();
    $user = $this->Users->find()
        ->where([
            'email_verification_token' => $token,
            'email_verification_token_expires >=' => $now,
        ])
        ->firstOrFail();

    $user->email_verified_at = $now;
    $user->email_verification_token = null;
    $user->email_verification_token_expires = null;

    if ($this->Users->save($user)) {
        $this->Flash->success(__('Email confirmed.'));
        return $this->Verification->redirectAfterEmailVerify($user);
    }

    $this->Flash->error(__('Confirmation failed.'));
    return $this->redirect(['action' => 'login']);
}
```

`redirectAfterEmailVerify(EntityInterface $user)` — if the user is logged in,
refreshes the identity with the login-flow flag set and redirects to the next
step. If the user is not logged in, redirects to the login page.

---

## pending

Shows a "check your inbox" page while `email_verified_at` is still null.
Redirects away if email is already verified.

```php
public function pending()
{
    $identity = $this->request->getAttribute('identity');
    if (!$identity instanceof \Authentication\IdentityInterface) {
        return $this->redirect(['action' => 'login']);
    }
    $verification = $this->Verification->result();
    if (!in_array('email_verify', $verification->pendingSteps(), true)) {
        return $this->redirect($this->Verification->getNextUrl() ?? '/');
    }
    $this->set(compact('verification'));
}
```

---

## resendEmailVerification

Re-sends the email verification link (clears the old token first).

```php
public function resendEmailVerification()
{
    $this->request->allowMethod(['post']);
    $this->Verification->resendEmailVerificationLink();
    $this->Flash->success(__('A new verification link has been sent.'));
    return $this->redirect($this->Verification->getNextUrl() ?? '/');
}
```

---

## verify

Universal OTP verification action. Handles `emailOtp`, `smsOtp`, and `totp`
steps. The step is passed as a URL argument (`/users/verify/email-otp`, etc.).

```php
public function verify(?string $step = null)
{
    $this->request->allowMethod(['get', 'post']);
    $response = $this->Verification->handleVerify($step);
    if ($response !== null) {
        return $response;
    }
    // View variables set automatically: $verification, $step
}
```

`handleVerify` on GET auto-sends the OTP code for `emailOtp`/`smsOtp`.
On POST it verifies the submitted code, marks the step, and redirects.

---

## enroll

TOTP enrollment. Generates a secret, displays a QR code, and verifies the
first code from the authenticator app.

```php
public function enroll()
{
    $this->request->allowMethod(['get', 'post']);
    $response = $this->Verification->handleEnroll();
    if ($response !== null) {
        return $response;
    }
    // View variables set automatically: $qrData, $secret
}
```

---

## enrollPhone

Phone number enrollment (required before `smsOtp` can be used).

```php
public function enrollPhone()
{
    $this->request->allowMethod(['get', 'post', 'put', 'patch']);
    $response = $this->Verification->handleEnrollPhone();
    if ($response !== null) {
        return $response;
    }
    // View variable set automatically: $user
}
```

---

## chooseVerification

Shown when `requiredSetupSteps` contains more than one OTP driver and the user
has not yet chosen which one to use. Called once after the first login
following email confirmation.

```php
public function chooseVerification()
{
    $this->request->allowMethod(['get', 'post']);
    $response = $this->Verification->handleChooseVerification();
    if ($response !== null) {
        return $response;
    }
    // View variables set automatically: $availableDrivers, $selectedDriver
}
```

The view receives:
- `$availableDrivers` — `array<string>` of step keys (`['emailOtp', 'smsOtp', 'totp']`)
- `$selectedDriver` — currently saved preference, or `null`

After saving, the choice is persisted to the `verification_preferences` JSON
column and the user is redirected to the next step.

---

## logout

```php
public function logout()
{
    $this->Authentication->logout();
    return $this->redirect(['action' => 'login']);
}
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
