<?php

require_once __DIR__ . '/vendor/autoload.php';

use DialClient\Application;

$app = new Application($argv);
$app->run(true);
