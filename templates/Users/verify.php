<?php
/**
 * Default verification template (override in app).
 *
 * Variables (optional):
 * - $verification: Verification\Value\VerificationResult
 */
/**
 * @var \App\View\AppView $this
 * @var \Verification\Value\VerificationResult|null $verification
 */
$this->assign('title', __d('verification', 'Additional Verification'));

$step = (string)$this->getRequest()->getParam('pass.0');
?>

<?php
$stepLabel = $step ?: __d('verification', 'verification');
$stepHtml = '<strong>' . h($stepLabel) . '</strong>';
?>
<div class="verification">
    <h1><?= __d('verification', 'Additional verification required') ?></h1>

    <p><?= __d('verification', 'Please enter the verification code for: {0}', $stepHtml) ?></p>

    <?= $this->Form->create() ?>
    <?= $this->Form->control('code', ['label' => __d('verification', 'Verification code')]) ?>
    <?php if ($step !== '') : ?>
        <?= $this->Form->control('step', ['type' => 'hidden', 'value' => $step]) ?>
    <?php endif; ?>
    <?= $this->Form->button(__d('verification', 'Verify')) ?>
    <?= $this->Form->end() ?>
</div>
