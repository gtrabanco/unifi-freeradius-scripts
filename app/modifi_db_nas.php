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
$pdo = pdoConnectDb();
$unifi_connection = loginUnifi();

$radpass = getenv('RADIUS_PASSWD');

$devices = $unifi_connection->list_devices();

$unifi_ip_addresses = array_values(array_map(function($device) use ($pdo) {
    //return $device->config_network->ip; //IP
    return $pdo->quote($device->mac, PDO::PARAM_STR);
}, $devices));


// First we want to delete all NAS that are not active
$sql = sprintf("DELETE FROM %s WHERE macaddress NOT IN (%s)", getenv('CONFIG_DB_TBL_RADNAS'), join(',', $unifi_ip_addresses));

$stm = $pdo->prepare($sql);
$stm -> execute([join(",",$unifi_ip_addresses)]);
$restartFRService = (bool) $stm->rowCount(); // If this value is bigger than 0 we should reset Freeradius Service


// Now we have to add the new NAS or update them if exists
$sql  = 'INSERT IGNORE INTO ' . getenv('CONFIG_DB_TBL_RADNAS') . ' SET ';
$sql .= ' macaddress = :mac, nasname = :ipaddr, shortname = :name, secret = :radpass ';
$sql .= ' ON DUPLICATE KEY UPDATE nasname = :ipaddr, shortname = :name, secret = :radpass;';

try {
    $pdo->beginTransaction();
    $stm = $pdo->prepare($sql);

    foreach ($devices as $device) {
        $stm->execute(array(
            'ipaddr' => $device->config_network->ip,
            'name'   => $device->name,
            'radpass'=> getenv('RADIUS_PASSWD'),
            'mac'    => str_replace(':', '-', strtoupper(trim($device->mac)))
        ));
    }

    $pdo->commit();

    // If we had not to restart FreeRadius Service previously, check if now we have to
    // If we should do we not perform any check with the rowCount
    $restartFRService = !$restartFRService ? (bool) $stm->rowCount(): $restartFRService;
} catch (PDOException $e) {
    $pdo->rollback();
}



if ($restartFRService > 0) {
    @exec('/usr/sbin/service freeradius restart');
}