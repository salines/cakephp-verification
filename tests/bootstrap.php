<?php
declare(strict_types=1);

use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Routing\Router;
use CakeVerification\CakeVerificationPlugin;
use Composer\Autoload\ClassLoader;

$pluginRoot = dirname(__DIR__);
$rootVendorAutoload = dirname($pluginRoot, 2) . '/vendor/autoload.php';
$pluginVendorAutoload = $pluginRoot . '/vendor/autoload.php';
$vendorDir = null;

if (file_exists($pluginVendorAutoload)) {
    $loader = require $pluginVendorAutoload;
    $vendorDir = dirname($pluginVendorAutoload);
} elseif (file_exists($rootVendorAutoload)) {
    $loader = require $rootVendorAutoload;
    $vendorDir = dirname($rootVendorAutoload);
} else {
    throw new RuntimeException('No Composer autoload.php found for plugin tests.');
}

if (isset($loader) && $loader instanceof ClassLoader) {
    $loader->addPsr4('CakeVerification\\Test\\', $pluginRoot . '/tests/');
}

if (!function_exists('env')) {
    require $vendorDir . '/cakephp/cakephp/src/Core/functions_global.php';
}

if (!function_exists('__')) {
    function __(string $singular, mixed ...$args): string
    {
        return $args ? sprintf($singular, ...$args) : $singular;
    }
}

if (!defined('CONFIG')) {
    define('CONFIG', $pluginRoot . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR);
}

// Register plugin under its CakePHP name so Configure::load('Verification.verification') works
Plugin::getCollection()->add(new CakeVerificationPlugin(['path' => $pluginRoot . '/']));

// Minimal Cake bootstrap for plugin tests
require $pluginRoot . '/config/bootstrap.php';

// Ensure a default app namespace
if (!Configure::check('App.namespace')) {
    Configure::write('App.namespace', 'App');
}

if (!Cache::getConfig('_cake_translations_')) {
    Cache::setConfig('_cake_translations_', [
        'className' => 'Array',
        'prefix' => 'verification_tests_',
    ]);
}
if (!Cache::getConfig('default')) {
    Cache::setConfig('default', [
        'className' => 'Array',
        'prefix' => 'verification_tests_default_',
    ]);
}

Router::reload();
$routes = Router::createRouteBuilder('/');
$routes->connect('/:controller/:action/*');
