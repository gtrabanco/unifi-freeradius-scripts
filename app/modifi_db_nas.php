<?php

/**
 * This scripts is thinked to be a crontab every X minutes.
 * This script will check if there is any new NAS (Access Point or antenna)
 * and if there is any new add it to the database and restart freeradius
 * server.
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

// Connect to database and Unifi
$pdo = pdoConnectDb();
$unifi_connection = loginUnifi();

// The lisf of the devices
$devices = $unifi_connection->list_devices();

// We want a list of all NAS mac address
$unifi_devices_mac_addresses = array_values(array_map(function($device) use ($pdo) {
    //return $device->config_network->ip; //IP
    return $pdo->quote(str_replace(':', '-', strtoupper(trim($device->mac))), PDO::PARAM_STR);
}, $devices));

// First we want to delete all NAS that are not active
$unifi_ip_address = getenv('CAPTIVE_PORTAL_NAS_IP');

$sql = 'DELETE FROM %s WHERE macaddress NOT IN (%s)';

$sql = sprintf('DELETE FROM %s WHERE macaddress NOT IN (%s)', getenv('CONFIG_DB_TBL_RADNAS'), join(',', $unifi_devices_mac_addresses));
if (!empty($unifi_ip_address)) {
    $sql .= sprintf(' AND nasname <> ?', $pdo->quote($unifi_ip_address, PDO::PARAM_STR));

    // Adding the Unifi controller as device
    $devices[] = [
        'config_network' => ['ip' => $unifi_ip_address],
        'name' => 'UNIFI CONTROLLER',
        'mac'  => getenv('CAPTIVE_PORTAL_NAS_MAC')
    ];
}

$stm = $pdo->prepare($sql);
$stm -> execute([join(",",$unifi_devices_mac_addresses)]);
$restartFRService = (bool) $stm->rowCount(); // If this value is bigger than 0 we should reset Freeradius Service


// Insert or modify the NAS
$sql = 'INSERT INTO ' . getenv('CONFIG_DB_TBL_RADNAS') . ' SET ';
$sql .= ' macaddress = :mac, nasname = :ipaddr, shortname = :name, secret = :secret, type = :type ';
$sql .= ' ON DUPLICATE KEY UPDATE nasname = :ipaddr, shortname = :name, secret = :secret, type = :type';

try {
    $pdo->beginTransaction();
    $stm = $pdo->prepare($sql);

    foreach ($devices as $device) {
        modify_auto_increment_behaviour(); // If is there any previous wrong behaviour, modify it
        $stm->execute(array(
            'ipaddr' => $device->config_network->ip,
            'name'   => $device->name,
            'secret' => getenv('RADIUS_PASSWD'),
            'type'   => getenv('RADIUS_NASTYPE'),
            'mac'    => str_replace(':', '-', strtoupper(trim($device->mac)))
        ));

        // Check if it had or, if not, it has to restart the FR Service
        $restartFRService = !$restartFRService ?
            ((bool) $stm->rowCount() > 1 || (int)$pdo->lastInsertId() > 0):
            $restartFRService;
    }

    $pdo->commit();

    
} catch (PDOException $e) {
    $pdo->rollback();
    //var_dump($e);
}


//* When development this should be commented
if ($restartFRService > 0) {
    @exec('/usr/sbin/service freeradius restart');
}
//*/
