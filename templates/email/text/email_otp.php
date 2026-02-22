<?php
/**
 * @var \App\View\AppView $this
 * @var string $code
 */
use function Cake\I18n\__d;

echo __d('verification', 'Your verification code is: {0}', $code);
