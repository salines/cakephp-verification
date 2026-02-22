<?php
/**
 * @var \App\View\AppView $this
 * @var string $verifyUrl
 */
use function Cake\I18n\__d;
?>
<p><?= __d('verification', 'Please verify your email by visiting the link below:'); ?></p>
<p><a href="<?= h($verifyUrl) ?>"><?= h($verifyUrl) ?></a></p>
