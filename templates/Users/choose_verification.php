<?php
/**
 * Default choose-verification template (override in app).
 *
 * Variables:
 * - $availableDrivers: list of enabled OTP driver names
 * - $selectedDriver: currently selected driver or null
 */
/**
 * @var \App\View\AppView $this
 * @var array<int, string> $availableDrivers
 * @var string|null $selectedDriver
 */
$this->assign('title', __d('verification', 'Choose Verification Method'));

$labels = [
    'emailOtp' => __d('verification', 'Email (one-time code)'),
    'smsOtp' => __d('verification', 'SMS (one-time code)'),
    'totp' => __d('verification', 'Authenticator app (TOTP)'),
];

$options = [];
foreach ($availableDrivers as $driver) {
    $options[$driver] = $labels[$driver] ?? h((string)$driver);
}
?>

<div class="verification choose form content">
    <h1><?= __d('verification', 'Choose your verification method') ?></h1>

    <p><?= __d('verification', 'Select how you want to verify your identity on each login.') ?></p>

    <?= $this->Form->create(null) ?>
    <?= $this->Form->radio('verification_preferences.otp_driver', $options, ['value' => $selectedDriver]) ?>
    <?= $this->Form->button(__d('verification', 'Save')) ?>
    <?= $this->Form->end() ?>
</div>
