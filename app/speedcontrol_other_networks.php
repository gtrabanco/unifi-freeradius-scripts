<?php

/**
 * This script change the speed usergroup of unifi for those clients
 * that have changed their default usergroup due to radius control
 * 
 * 
 *              gabi.io
 * 
 * @author Gabriel Trabanco Llano <gtrabanco@fwok.org>
 */

set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__);

require_once('vendor/autoload.php');
require_once('library/functions.php');

$dotenv = Dotenv\Dotenv::create(join(array(__DIR__, 'config'), DIRECTORY_SEPARATOR));
$dotenv->load();

// Connect to database and Unifi
$unifi_connection = loginUnifi();

// Unifi connected devices and freeradius users
$clients = $unifi_connection->list_clients();

// Get the bad users in other networks and reset they speed limit
$speed_groups_reset = explode(',', getenv('RADIUS_BAD_USERS_SPEED'));
$speed_groups_reset = is_array($speed_groups_reset) ? $speed_groups_reset : array($speed_groups_reset);

$reset_usegroups_unifi = array_map(function ($group) {
    return $group->_id;
}, filter_array_object_value($unifi_connection->list_usergroups(), 'name', $speed_groups_reset, 'in_array'));

// Row per row exploring modified usergroup in other networks to reset their speed
array_map(function($client) use ($unifi_connection, $reset_usegroups_unifi) {
    $radius_network = strtolower(trim(getenv('RADIUS_USED_USERGROUPS')));

    $client_essid   = isset($client->essid)?strtolower(trim($client->essid)):'';
    $client_network = isset($client->network)?strtolower(trim($client->network)):'';

    if((strlen($client_essid) > 0 && $client_essid !== $radius_network ||
        strlen($client_network) > 0 && $client_network !== $radius_network) && 
        isset($client->usergroup_id) && in_array($client->usergroup_id, $reset_usegroups_unifi)) {
        
        //Reset the usergroup
        $unifi_connection->set_usergroup($client->_id, '');
    }

    //$unifi_connection->set_usergroup($client->_id, '');
}, $clients);
//*/

$clients = null;
$unifi_connect = null;
$pdo = null;

// Add following content to daloRadius crontab, disable and enable it
// File of crontab: /var/www/daloradius/contrib/scripts/dalo-crontab
// Add the second wihtout "#"
# Every minute check if there is unauthorized devices in network
#* * * * * /usr/bin/php $DALO_DIR/maintenance/vendor/unifi-freeradius/app/speedcontrol_other_networks.php > /dev/null 2>&1