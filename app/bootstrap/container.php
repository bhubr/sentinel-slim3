<?php
require 'db_settings.php';

$container = $app->getContainer();

// Register Twig
$container['view'] = function ($container) {
    $view = new \Slim\Views\Twig( __DIR__.'/../views', [
        'cache' => false, //__DIR__.'/../cache',
    ]);

    // Instantiate and add Slim specific extension
    $basePath = rtrim(str_ireplace('index.php', '', $container['request']->getUri()->getBasePath()), '/');
    $view->addExtension(new Slim\Views\TwigExtension($container['router'], $basePath));
    return $view;
};

// Register Eloquent configuration
// See Slim3 cookbook: https://www.slimframework.com/docs/cookbook/database-eloquent.html
$container['db'] = function ($container) {
    $capsule = new \Illuminate\Database\Capsule\Manager;
    $capsule->addConnection([
        'driver' => 'mysql',
        'host' => DB_HOST,
        'database' => DB_NAME,
        'username' => DB_USER,
        'password' => DB_PASS,
        'charset' => 'utf8',
        'collation' => 'utf8_unicode_ci',
    ]);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    return $capsule;
};

$container['sentinel'] = function($container) {
    return (new \Cartalyst\Sentinel\Native\Facades\Sentinel())->getSentinel();
};
