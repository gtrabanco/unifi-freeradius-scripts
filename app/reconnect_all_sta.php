<?php

/**
 * This scripts is thinked to be a crontab every X minutes.
 * This script will reconnect all clients connected to specified
 * network.
 * 
 * Usage:
 *      /usr/bin/php reconnect_all_sta.php NETWORK
 * 
 * 
 *          gabi.io
 * 
 * @author Gabriel Trabanco Llano <gtrabanco@fwok.org>
 */


set_time_limit(30); //Shoul be enought, it should take no more
            //than 2/3 seconds reconnect all clients

set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__);

require_once('vendor/autoload.php');
require_once('library/functions.php');

$dotenv = Dotenv\Dotenv::create(join(array(__DIR__, 'config'), DIRECTORY_SEPARATOR));
$dotenv->load();

// Get necessary params if not print the help
if ($argc < 2) {

    echo "The usage is\n";
    echo "\t" .$argv[0] ." <network>\n";
    exit;
} else {
    $network = $argv[1];
}

// Connect to unifi
$unifi_connection = loginUnifi();
$unifi_connection->set_debug(false);

// The lisf of the devices
$clients = $unifi_connection->list_clients();

// Reconnect all clients in the network
array_filter($clients, function ($client) use ($network, $unifi_connection) {
    

    $client_essid   = isset($client->essid)?strtolower(trim($client->essid)):'';
    $client_network = isset($client->network)?strtolower(trim($client->network)):'';

    if( strlen($client_essid) > 0 && $client_essid !== $network
        || strlen($client_network) > 0 && $client_network !== $network) {
            if(!$unifi_connection->reconnect_sta($client->mac)) {
                $mac = $client->mac;
                fwrite(STDERR, "Could not reconnect $mac\n");
                $mac = null; //To avoid errors, it should not happen but...
            }
    }
});
exit; // This is a binary