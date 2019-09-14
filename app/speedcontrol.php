<?php


set_include_path(get_include_path() . PATH_SEPARATOR . __DIR__);

require_once('vendor/autoload.php');
require_once 'library/functions.php';

// Load env vars
$dotenv = Dotenv\Dotenv::create(join(array(__DIR__, 'config'), DIRECTORY_SEPARATOR));
$dotenv->load();

// Env vars we need globally in the script
$FRUsername = str_replace('"', '', getenv('USER_NAME'));
$device_mac = str_replace('"', '', getenv('CALLING_STATION_ID')); // Unifi can search a mac with format
                    // AA-BB or AABB or AA:BB for the mac address

$attr_max_speed_upload_data    = getenv('MAX_SPEED_UPLOAD_DATA');
$attr_max_speed_download_data  = getenv('MAX_SPEED_DOWNLOAD_DATA');
$attr_max_speed_total_data     = getenv('MAX_SPEED_TOTAL_DATA');

$attr_reduced_speed_usergroup = getenv('REDUCED_SPEED_USERGROUP');
$attr_speed_reset_period = getenv('REDUCED_SPEED_RESET_PERIOD');

$attr_prevail_unifi = getenv('PREVAIL_UNIFI');

$reply_attribute_fixed_speed_usergroup = getenv('SPEED_USERGROUP');

//Connect to database and Unifi
$pdo = pdoConnectDB();
$unifi_connection = loginUnifi();

// Getting the params for the user
$user_reply_attributes = get_reply_attribute_values_for_user($FRUsername);

// Getting the info for the user device because
// unifi controller know about devices and not
// about users.
// Get the unifi speed groups (usergroups)
$unifi_usegroups = $unifi_connection->list_usergroups();

// Get the unifi data for the given mac address
$device_unifi_info = $unifi_connection->stat_client($device_mac);
$device_unifi_info = is_array($device_unifi_info) && count($device_unifi_info) > 0 ? $device_unifi_info[0]:array();

//$results          = $unifi_connection->unblock_sta($my_mac); // unblock / block device
//$results2         = $unifi_connection->reconnect_sta($my_mac); // reconnect a device
// To know the client usergroup:
//  stat_client($mac)->usergroup_id
// If it is empty user has the default group, we can also set the default group using
// empty group_id when setting device speed
//
// We will also use stat_client() because we do not want a list of all connected devices
// we just want the known mac address client
// If the user has a group different than default and is not max speed or reduced speed
// group from freeradius configuration we will finish because maybe the admin set
// a custom group for the device. We do not want to modifi Unifi behaviour.

// If we dont have device we finish
if (!$device_unifi_info) {
    //die('Not device to modify the speed: ' . $device_mac);

    echo "ok";
    exit(0);
}


// If the user has a specific usergroup for speed in Radius apply it and end
//$attr_speed_usergroup = filter_array_key_value($user_reply_attributes, $attr, $attr_name);
$attr_speed_usergroup = filter_array_key_value($user_reply_attributes, 'attribute', $reply_attribute_fixed_speed_usergroup);

if (count($attr_speed_usergroup) > 0) {
    // If we has setup a custom speed group for the user in the radius
    // just set it up because if we are here it was not setup previously
    // because we have checked previously if the user has a custom
    // usergroup in the Radius
    //
    // First get the usegroup_id for the given group; parenthesis is just to make it clearer
    $unifi_user_usergroup = filter_array_object_value($unifi_usegroups, 'name', ($attr_speed_usergroup[0]['value']))[0];

    print_r($unifi_user_usergroup);
    $user_id  = $device_unifi_info->_id;
    $group_id = $unifi_user_usergroup->_id;

    // Set the usegroup if it was not set beforte
    if ($device_unifi_info->usergroup_id !== $group_id) {
        $unifi_connection->set_usergroup($user_id, $group_id);
    }
    //End

    /**
     * Commented because if we want a user that can navigate fatest than other but with limitations
     * because if we want a user with unlimited traffic we can set the limits with value lower than 1
     */
    /*
    echo "ok";
    exit(0);
    //*/
}


/*********** HERE BEGIN THE POSSIBLE BANNED USERS *********/

// Control variable to know if the user has a fixed speed group
$group_id = ''; //Default group is an empty group

// If there is no any group for reduced speed skip the checks
$reduced_speed_group = filter_array_key_value($user_reply_attributes, 'attribute', $attr_reduced_speed_usergroup);
$speedcontrol_period = filter_array_key_value($user_reply_attributes, 'attribute', $attr_speed_reset_period);

// We will check if there is the minimum configuration for apply a reduced speed which is
// a group of reduced speed and period
if (isset($reduced_speed_group) && count($reduced_speed_group) < 1 && isset($speedcontrol_period) && count($speedcontrol_period) < 1) {
    //die('There is not enough configuration to apply the speed control by traffic consumption.');

    echo "ok";
    exit(0);
}

// Check if the reduced speed group exists in Unifi
// First we get the values
$usegroup_reduced_speed_name = $reduced_speed_group[0]['value'];
$unifi_reduced_usergroup = filter_array_object_value($unifi_usegroups, 'name', $usegroup_reduced_speed_name);

// If the group does not exist in Unifi, has no sense continue limiting by speed control
if (count($unifi_reduced_usergroup) < 1) {
    //die("You should configure your unifi with the given usegroup: ${usegroup_reduced_speed_name}");

    $group_id = '';

// This comment is for the else if
// Depending if Customunifi-Prevail-Unifi is defined and a value considered true by PHP then
// If the admin has defined a custom group for the device in the unifi we won't change
// the speed. But if the group is the reduced speed we will continue the process.
} else if(isset($attr_prevail_unifi)
  && $attr_prevail_unifi
  && isset($device_unifi_info->usergroup_id)
  && strlen($device_unifi_info->usergroup_id) > 0
  && $device_unifi_info->usergroup_id !== $unifi_reduced_usergroup[0]->_id) { // This last check if the
                                    // defined usergroup is different from the downspeed usergroup
                                    // Because if is the slower group we should check later if the
                                    // user must be in the default group. Because maybe we are in a
                                    // different billing period
    //die('The user has a custom group so we will not change the speed group for the device.');
    $group_id = $device_unifi_info->usergroup_id;
}

// Check if the user has any limit (upload, download or total)
$user_max_speed_upload_data   = get_check_attribute_value($FRUsername, $attr_max_speed_upload_data);
$user_max_speed_download_data = get_check_attribute_value($FRUsername, $attr_max_speed_download_data);
$user_max_speed_total_data    = get_check_attribute_value($FRUsername, $attr_max_speed_total_data);

$user_max_speed_period = strtolower(trim(filter_array_key_value($user_reply_attributes, 'attribute', $attr_speed_reset_period)[0]['value']));

// If any limit is overpass do something
$user_limits = check_user_limits($FRUsername, $user_max_speed_download_data, $user_max_speed_upload_data, $user_max_speed_total_data, $user_max_speed_period);

// Now if the data consumption is bigger than the maximum we change the user to slower group
if ( $user_limits ) {
    
    // User is over the limits
    // Set the group id for over the limits

    $group_id = $unifi_reduced_usergroup[0]->_id;
} 

// Set the speed for this user
$user_id  = $device_unifi_info->_id;
$unifi_connection->set_usergroup($user_id, $group_id);

$pdo = null; // Good bye MySQL!
echo "ok";
exit(0); // No one can include this file, it will be used as a binary
/**
 * Este script debe estar en la sección "accounting {}". Suponiendo que php esté en
 * "/usr/bin/php" y el script en 
 * "/var/www/daloradius/contrib/scripts/maintenance/vendor/unifi-speedcontrol/main.php"
 * Debemos agregar la siguiente configuración:
 * 
 *    update control {
 *        Tmp-Integer-0 = `/usr/bin/php -f /var/www/daloradius/contrib/scripts/maintenance/vendor/unifi-freeradius/speedcontrol.php`
 *    }
 * 
 * 
 * 
 * Ayuda exec de Freeradius:
 * https://networkradius.com/doc/3.0.10/raddb/mods-available/exec.html
 */


 /*
            start {
                update {
                    Tmp-Integer-0 = `/usr/bin/php -f /var/www/daloradius/contrib/scripts/maintenance/vendor/unifi-freeradius/speedcontrol.php`
                }
            }
            interim-update {
                update {
                    Tmp-Integer-0 = `/usr/bin/php -f /var/www/daloradius/contrib/scripts/maintenance/vendor/unifi-freeradius/speedcontrol.php`
                }
            }
 */