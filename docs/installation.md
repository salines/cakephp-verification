# Installation

Full setup guide for the cakephp-verification plugin.

## Requirements

- PHP 8.2+
- CakePHP 5.3+
- cakephp/authentication ^4.0

## 1) Install the package

```bash
composer require salines/cakephp-verification
```

## 2) Load the plugin

Add it manually in `src/Application.php`:

```php
use CakeVerification\CakeVerificationPlugin;

// in bootstrap():
$this->addPlugin(CakeVerificationPlugin::class);
```

## 3) Publish config

```bash
bin/cake verification:install
```

This copies `config/verification.php` into your app. Open it and adjust to
your requirements.

## 4) Load the component in AppController

```php
// src/Controller/AppController.php
public function initialize(): void
{
    parent::initialize();
    $this->loadComponent('Flash');
    $this->loadComponent('Authentication.Authentication');
    $this->loadComponent('CakeVerification.Verification');
}
```

## 5) Database columns

The plugin does not ship migrations. Add the columns you need to your `users` table.

### Email verification (`emailVerify`)

| Column | Type | Notes |
|---|---|---|
| `email` | VARCHAR | Required |
| `email_verification_token` | VARCHAR(191), nullable | Token sent in link |
| `email_verification_token_expires` | DATETIME, nullable | Token expiry |
| `email_verified_at` | DATETIME, nullable | Set when verified |

### Email OTP (`emailOtp`)

| Column | Notes |
|---|---|
| `email` | Required (same column as above) |

### SMS OTP (`smsOtp`)

| Column | Type | Notes |
|---|---|---|
| `phone` | VARCHAR(32), nullable | Phone number |
| `phone_verified_at` | DATETIME, nullable | Set after OTP success |
| `phone_verified` | TINYINT(1), default 0 | Optional flag |

### TOTP

| Column | Type | Notes |
|---|---|---|
| `totp_secret` | VARCHAR(255), nullable | Store encrypted |
| `totp_verified_at` | DATETIME, nullable | Set after first successful verify |

### OTP driver preference

| Column | Type | Notes |
|---|---|---|
| `verification_preferences` | JSON, nullable | Stores the user's chosen OTP driver |

### Example migration

```php
use Migrations\AbstractMigration;

class AddVerificationFieldsToUsers extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('users');

        foreach ([
            ['email_verification_token',         'string',   ['limit' => 191, 'null' => true]],
            ['email_verification_token_expires',  'datetime', ['null' => true]],
            ['email_verified_at',                 'datetime', ['null' => true]],
            ['phone',                             'string',   ['limit' => 32,  'null' => true]],
            ['phone_verified_at',                 'datetime', ['null' => true]],
            ['phone_verified',                    'boolean',  ['default' => false]],
            ['totp_secret',                       'string',   ['limit' => 255, 'null' => true]],
            ['totp_verified_at',                  'datetime', ['null' => true]],
            ['verification_preferences',          'json',     ['null' => true]],
        ] as [$col, $type, $opts]) {
            if (!$table->hasColumn($col)) {
                $table->addColumn($col, $type, $opts);
            }
        }

        $table->update();
    }
}
```

Register the JSON type in your `UsersTable::initialize()`:

```php
public function initialize(array $config): void
{
    parent::initialize($config);
    // ...
    $this->getSchema()->setColumnType('verification_preferences', 'json');
}
```

## 6) Configure

Open `config/verification.php` and set `requiredSetupSteps` to the steps your
app needs. See [configuration.md](configuration.md) for the full reference.

## 7) UsersController actions

See [users_controller.md](users_controller.md) for the complete list of
actions you must implement and what each one does.

## Next steps

- [Configuration reference](configuration.md)
- [UsersController actions](users_controller.md)
- [VerificationComponent](verification_component.md)
- [Email verification & OTP](email_verification.md)
- [SMS OTP](sms_verification.md)
- [TOTP](totp_verification.md)
- [API reference](api/index.md)

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
