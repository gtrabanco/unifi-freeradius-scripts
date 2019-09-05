<?php

set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__);

require_once('vendor/autoload.php');
require_once('library/functions.php');

$dotenv = Dotenv\Dotenv::create(join(array(__DIR__, 'config'), DIRECTORY_SEPARATOR));
$dotenv->load();

$pdo = pdoConnectDb();
if($pdo) {
    echo "Sucessfully connected to database\n";
} else {
    echo "We can not connect to database.\n";
}

$pdo = null;


$unifi_connect = loginUnifi();

if ($unifi_connect) {
    echo "Sucessfully connected to the Unifi Controller.\n";
} else {
    echo "We can not connect to the Unifi Controller.\n";
}

$unifi_connect = null;
