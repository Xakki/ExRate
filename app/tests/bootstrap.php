<?php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (class_exists(DG\BypassFinals::class)) {
    DG\BypassFinals::enable();
}

$dotenv = new Dotenv();
if (file_exists(dirname(__DIR__).'/.env')) {
    $dotenv->usePutenv()->bootEnv(dirname(__DIR__).'/.env');
} elseif (file_exists(dirname(__DIR__).'/.env_dist')) {
    $dotenv->usePutenv()->bootEnv(dirname(__DIR__).'/.env_dist');
}

// Force APP_ENV to test
$_SERVER['APP_ENV'] = 'test';
$_ENV['APP_ENV'] = 'test';

if ($_SERVER['APP_DEBUG']) {
    umask(0000);
}
