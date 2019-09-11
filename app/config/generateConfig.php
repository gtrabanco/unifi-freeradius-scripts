<?php

/**
 * Configuration generator for unifi-scripts-freeradius
 * 
 * This scripts gets the configuration from daloRadius to apply it
 * to this scripts configuration.
 * 
 * @author Gabriel Trabanco Llano <gtrabanco@fwok.org>
 */


if ($argc < 2) {
    $script = $argv[0];
    echo "\nUsage php $script <path_to_daloradius.conf.php>";
    echo "\n";
    exit;
}

$configFile = isset($argv[1])?realpath($configFile):null;

if (!$configFile || !file_not_exists(realpath($configFile))) {
    die("The file configuration you provided not exits\n");
}

require_once $configFile;

/*
$configValues['CONFIG_DB_HOST'] = 'localhost';
$configValues['CONFIG_DB_PORT'] = '3306';
$configValues['CONFIG_DB_USER'] = 'freeradius_usr';
$configValues['CONFIG_DB_PASS'] = 'freRad1.vs';
$configValues['CONFIG_DB_NAME'] = 'radius';
$configValues['CONFIG_DB_TBL_RADCHECK'] = 'radcheck';
$configValues['CONFIG_DB_TBL_RADREPLY'] = 'radreply';
$configValues['CONFIG_DB_TBL_RADGROUPREPLY'] = 'radgroupreply';
$configValues['CONFIG_DB_TBL_RADGROUPCHECK'] = 'radgroupcheck';
$configValues['CONFIG_DB_TBL_RADUSERGROUP'] = 'radusergroup';
$configValues['CONFIG_DB_TBL_RADNAS'] = 'nas';
$configValues['CONFIG_DB_TBL_RADHG'] = 'radhuntgroup';
$configValues['CONFIG_DB_TBL_RADPOSTAUTH'] = 'radpostauth';
$configValues['CONFIG_DB_TBL_RADACCT'] = 'radacct';
$configValues['CONFIG_DB_TBL_RADIPPOOL'] = 'radippool';
$configValues['CONFIG_DB_TBL_DALOOPERATORS'] = 'operators';
*/

/*
echo "# MySQL Configuration\n";
echo "CONFIG_DB_ENGINE               = \"mysql\"";
echo "CONFIG_DB_HOST                 = \"localhost\"";
echo "CONFIG_DB_PORT                 = \"3306\"";
CONFIG_DB_USER                 = "radius"
CONFIG_DB_PASSWD               = "radius"
CONFIG_DB_NAME                 = "radius"
CONFIG_DB_ENCODING             = "utf8mb4"
CONFIG_DB_TBL_RADCHECK         = "radcheck"
CONFIG_DB_TBL_RADREPLY         = "radreply"
CONFIG_DB_TBL_RADGROUPREPLY    = "radgroupreply"
CONFIG_DB_TBL_RADGROUPCHECK    = "radgroupcheck"
CONFIG_DB_TBL_RADUSERGROUP     = "radusergroup"
CONFIG_DB_TBL_RADACCT          = "radacct"
CONFIG_DB_TBL_RADACCTPERIOD    = "radacctperiod"
CONFIG_DB_TBL_EXCEEDEDPERIOD   = "radacctexceededperiod"

# FreeRadius Attributes
MAX_SPEED_UPLOAD_DATA          = "Customunifi-Max-Speed-Upload-Data"
MAX_SPEED_DOWNLOAD_DATA        = "Customunifi-Max-Speed-Download-Data"
MAX_SPEED_TOTAL_DATA           = "Customunifi-Max-Speed-Total-Data"
REDUCED_SPEED_USERGROUP        = "Customunifi-Reduced-Speed-User-Group"
REDUCED_SPEED_RESET_PERIOD     = "Customunifi-Speed-Control-Reset-Period"
SPEED_USERGROUP                = "Customunifi-Speed-User-Group"

# Unifi Configuration
UNIFI_URL                      = "https://127.0.0.1:8443"
UNIFI_USER                     = "ubnt"
UNIFI_PASSWD                   = "ubnt"
UNIFI_SITE_ID                  = "default"
UNIFI_VERSION                  = "5.26.10"

# Network to check if there are valid devices (could be an wireless or non wireless)
RADIUS_NETWORK_CHECK           = "Internet"

# Password for RADIUS to add automatically the antennas
UNIFI_RADIUS_WLAN              = "radius"
RADIUS_SERVER_IP               = "X.X.X.X" # Not used
RADIUS_PASSWD                  = "radpass"
RADIUS_NASTYPE                 = "other"
*/