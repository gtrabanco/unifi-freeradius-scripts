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
$clients = $unifi_connection->list_clients();
$freeradius_clients = get_freeradius_connected_users();
$unifi_missed_connected_clients = array_values(array_filter($clients, 'get_clients_no_auth_by_users'));

if(count($unifi_missed_connected_clients) > 0) {
    array_map(function($client) use ($unifi_connection) {
        $unifi_connection->reconnect_sta($client->mac);
    }, $unifi_missed_connected_clients);
}

// Get the bad users in other networks and reset they speed limit
$speed_groups_reset = explode(',', getenv('RADIUS_BAD_USERS_SPEED'));

$unifi_bad_users_other_network = array_values(array_filter($clients, 'check_clients_no_radius_network'));

if(count($unifi_bad_users_other_network) > 0) {
    array_map(function($client) use ($unifi_connection) {
        $unifi_connection->set_usergroup($client->_id, '');
    });
}

$clients = null;
$unifi_connect = null;
$pdo = null;

// Add following content to daloRadius crontab, disable and enable it
// File of crontab: /var/www/daloradius/contrib/scripts/dalo-crontab
// Add the second wihtout "#"
# Every minute check if there is unauthorized devices in network
#* * * * * /usr/bin/php $DALO_DIR/maintenance/vendor/unifi-freeradius/app/disconnect_no_auth_clients.php 2>&1 >/dev/null
