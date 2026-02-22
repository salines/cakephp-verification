<?php
/**
 * Default TOTP enrollment template (override in app).
 *
 * Variables (optional):
 * - $qrData: string
 * - $secret: string
 */
/**
 * @var \App\View\AppView $this
 * @var string|null $qrData
 * @var string|null $secret
 */
$this->assign('title', __d('verification', 'Enroll Authenticator'));
?>

<div class="verification enroll">
    <h1><?= __d('verification', 'Enroll authenticator') ?></h1>

    <p><?= __d('verification', 'Scan the QR code in your authenticator app and confirm with a code.') ?></p>

    <?php if (!empty($qrData)) : ?>
        <?= $this->Verification->qrCode((string)$qrData) ?>
    <?php else : ?>
        <p><em><?= __d('verification', 'QR data not available.') ?></em></p>
    <?php endif; ?>

    <?php if (!empty($secret)) : ?>
        <p><?= __d('verification', 'Manual secret: {0}', '<code>' . h((string)$secret) . '</code>') ?></p>
    <?php endif; ?>

    <?= $this->Form->create() ?>
    <?= $this->Form->control('code', ['label' => __d('verification', 'Authenticator code')]) ?>
    <?= $this->Form->button(__d('verification', 'Confirm')) ?>
    <?= $this->Form->end() ?>
</div>
