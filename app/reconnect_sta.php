<?php

/**
 * This script will reconnect a given mac address
 * It could also rewrites AA-BB... mac format
 * to vail aa:ff... format
 * If error it will be written to STDERR
 * 
 *          gabi.io
 * 
 * @author Gabriel Trabanco Llano <gtrabanco@fwok.org>
 */


set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__);

require_once('vendor/autoload.php');
require_once('library/functions.php');

$dotenv = Dotenv\Dotenv::create(join(array(__DIR__, 'config'), DIRECTORY_SEPARATOR));
$dotenv->load();

// Get necessary params if not print the help
if ($argc < 2) {

    echo "The usage is\n";
    echo "\t" .$argv[0] ." <client_mac_addr>\n";
    exit;
} else {
    $mac = str_replace('-',':',strtolower($argv[1]));
}

$unifi_connection = loginUnifi();

// The lisf of the devices
if(!$unifi_connection->reconnect_sta($mac)) {
    fwrite(STDERR, "$mac could not be reconnected\n");
}
exit; //This is a binary file