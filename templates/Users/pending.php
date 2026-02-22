<?php
/**
 * Default pending template (override in app).
 *
 * Variables (optional):
 * - $verification: Verification\Value\VerificationResult
 */
/**
 * @var \App\View\AppView $this
 * @var \Verification\Value\VerificationResult|null $verification
 */
$this->assign('title', __d('verification', 'Verification Pending'));

$pending = [];
if ($verification instanceof \Verification\Value\VerificationResult) {
    $pending = $verification->pendingSteps();
}
?>

<?php
$verifyUrl = $this->Url->build(['action' => 'verify']);
?>
<div class="verification pending">
    <h1><?= __d('verification', 'Verification pending') ?></h1>

    <?php if ($pending === []) : ?>
        <p><?= __d('verification', 'No pending steps.') ?></p>
    <?php else : ?>
        <p><?= __d('verification', 'Pending steps:') ?></p>
        <ul>
            <?php foreach ($pending as $step) : ?>
                <li><?= h((string)$step) ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <p>
        <a href="<?= $verifyUrl ?>"><?= __d('verification', 'Continue verification') ?></a>
    </p>
</div>
