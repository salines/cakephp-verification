<?php
/**
 * @var \App\View\AppView $this
 * @var string $verifyUrl
 */
use function Cake\I18n\__d;
?>
<?= __d('verification', 'Please verify your email by visiting the link below:'); ?>

<?= $verifyUrl;
