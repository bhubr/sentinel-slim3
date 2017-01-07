<?php
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once __DIR__.'/../vendor/autoload.php';

$app = new \Slim\App();

//register bindings
include_once __DIR__.'/../app/bootstrap/container.php';

include_once __DIR__.'/../app/routes.php';

$app->run();
