<?php

set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__);

require_once('vendor/autoload.php');
require_once('library/functions.php');

$dotenv = Dotenv\Dotenv::create(join(array(__DIR__, 'config'), DIRECTORY_SEPARATOR));
$dotenv->load();

// Connect to database and Unifi
$pdo = pdoConnectDb();
$unifi_connection = loginUnifi();

// Freeradius username and 
$FRUsername = str_replace('"', '', getenv('USER_NAME'));
$device_mac = str_replace('"', '', getenv('CALLING_STATION_ID')); // Unifi can search a mac with format
                    // AA-BB or AABB or AA:BB for the mac address


//*
// Unifi connected devices and freeradius users
$freeradius_clients = get_freeradius_connected_users();
$unifi_missed_connected_clients = array_values(array_filter($unifi_connection->list_clients(), 'get_clients_no_auth_by_users'));

if(count($unifi_missed_connected_clients) > 0) {
    array_map(function($client){
        $unifi_connection->reconnect_sta($client->mac);
    }, $unifi_missed_connected_clients);
}

$unifi_connect = null;
$pdo = null;

// Add following content to daloRadius crontab, disable and enable it
// File of crontab: /var/www/daloradius/contrib/scripts/dalo-crontab
// Add the second wihtout "#"
# Every 5 minutes check if there is unauthorized devices in network
#*/5 * * * * /usr/bin/php $DALO_DIR/maintenance/vendor/unifi-freeradius/app/disconnect_no_auth_clients.php 2>&1 >/dev/null
