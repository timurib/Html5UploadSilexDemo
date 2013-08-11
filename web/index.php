<?php
use Silex\Application;
use Timurib\Html5Upload\Provider\UploadControllerProvider;

call_user_func(function () {
    require_once __DIR__.'/../vendor/autoload.php';
    $app = new Application();
    $app['debug'] = true;
    $app->mount('/', new UploadControllerProvider());
    $app->run();
});