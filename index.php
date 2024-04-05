<?php

require __DIR__.'/vendor/autoload.php';

$app = Flight::app();

Flight::route('/hey', function () { echo 'hi!'; });
Flight::post('/post', function () { echo 'posted!'; });

Flight::start();