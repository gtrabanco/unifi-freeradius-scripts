<?php

/**
 * This scripts is thinked to be a crontab every X minutes.
 * This script will check if there is any new NAS (Access Point or antenna)
 * and if there is any new add it to the database and restart freeradius
 * server.
 */


set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__);

require_once('vendor/autoload.php');
require_once('library/functions.php');

$dotenv = Dotenv\Dotenv::create(join(array(__DIR__, 'config'), DIRECTORY_SEPARATOR));
$dotenv->load();

// Connect to database and Unifi
//$pdo = pdoConnectDb();
$unifi_connection = loginUnifi();

/**
 * This is for getting the password for a given wlan by API instead of enviroment var
 */
/*/
//Get the wlan configuration to know the radius profile
$wlan_conf = $unifi_connection->list_wlanconf(); // check [i]->name === getenv('UNIFI_RADIUS_WLAN')
$v = filter_array_object_value($wlan_conf, 'name', getenv('UNIFI_RADIUS_WLAN'));
if(count($v) > 0) {
    $radiusprofile_id = $v[0]->radiusprofile_id;
} else {
    die('Not possible to find the radiusprofile for the given network.');
}

// Get the radius configuration to get the radius password
$radius_conf = filter_array_object_value($unifi_connection->list_radius_profiles(), '_id', $radiusprofile_id);

if(count($radius_conf) > 0) {
    $auth_servers = filter_array_object_value($radius_conf->auth_servers, 'server', getenv('RADIUS_SERVER_IP'));

    if (count($auth_servers) > 0) {
        $radpass = $auth_servers[0]->x_secret
    } else {
        die('There are no auth servers');
    }
} else {
    die('Radius configuration could not be found.');
}
//*/

$radpass = getenv('RADIUS_PASSWD');

$devices = $unifi_connection->list_devices();

function device_map($device) {
    $sql = 'SELECT * FROM ' . getenv('CONFIG_DB_TBL_RADNAS') . ' WHERE nasname = ?';
}

// $device->name // nas shortname
// $device->config_network->ip // nas address
// $device->mac // to nas description

