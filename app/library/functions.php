<?php

// We need vendors for some helpers
set_include_path(get_include_path() . PATH_SEPARATOR . '..');
require_once('vendor/autoload.php');

/**
 * Function helper to login into Unifi
 */
function loginUnifi() {
     // Unifi configuration
    $controller_url = getenv('UNIFI_URL');
    $controller_user = getenv('UNIFI_USER');
    $controller_password = getenv('UNIFI_PASSWD');
    $site_id = getenv('UNIFI_SITE_ID');
    $controller_version = getenv('UNIFI_VERSION');

    // Firstly we will check if the unifi controller and database are online
    list(,$controller_server,$controller_port) = explode(':', str_replace('/', '', $controller_url));

    try {
        // Check if the controller is online
        if(isset($controller_server) && isset($controller_port) && !check_service_online($controller_server, $controller_port)) {
            throw new Exception('The Unifi Controller is not online');
        }

        $unifi_connection = new UniFi_API\Client($controller_user, $controller_password, $controller_url, $site_id, $controller_version, false);
        $unifi_login      = $unifi_connection->login();
        return $unifi_connection;
    } catch (Exception $e) {
        die('We can not connect to the Unifi Controller');
        return false;
    }
}

/**
 * Function helper to connect to a database
 */
function pdoConnectDb() {
    $dbCharset             = getenv('CONFIG_DB_ENCODING');
    $dbDriver              = getenv('CONFIG_DB_ENGINE');
    $dbServer              = getenv('CONFIG_DB_HOST');
    $dbPort                = getenv('CONFIG_DB_PORT');
    $dbUser                = getenv('CONFIG_DB_USER');
    $dbPasswd              = getenv('CONFIG_DB_PASSWD');
    $dbName                = getenv('CONFIG_DB_NAME');
    
    
    $dsn = sprintf('%s:host=%s;port=%s;dbname=%s;charset=%s', $dbDriver, $dbServer, $dbPort, $dbName, $dbCharset);

    try {
        if (!check_service_online($dbServer, $dbPort)) {
            throw new Exception('The database is offline.');
        }

        $pdo = new PDO(
            $dsn,
            $dbUser, 
            $dbPasswd, 
            array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::MYSQL_ATTR_FOUND_ROWS => true
            ));
    } catch (Exception $e) {
        echo $e->getMessage();
        die ('We can not connect to database.');
        return false;
    }

    return $pdo;
}

/**
 * Get the a check attribute value for given user and attribute
 */
function get_check_attribute_value($username, $attribute) {

    global $pdo;
    
    $tbl_radcheck      = getenv('CONFIG_DB_TBL_RADCHECK');
    $tbl_radusergroup  = getenv('CONFIG_DB_TBL_RADUSERGROUP');
    $tbl_radgroupcheck = getenv('CONFIG_DB_TBL_RADGROUPCHECK');
    
    $sql_user = sprintf('SELECT value FROM %s
        WHERE username=? AND attribute=?', $tbl_radcheck);

    
    $stm = $pdo->prepare($sql_user);
    $stm -> execute([$username, $attribute]);

    if ($stm->rowCount() === 0) {
        // Get the value for the attribute we configured (the result will be priorized)
        $sql_group = "SELECT ${tbl_radgroupcheck}.value FROM radgroupcheck
                        RIGHT JOIN ${tbl_radusergroup}
                            ON ${tbl_radusergroup}.groupname = ${tbl_radgroupcheck}.groupname
                    WHERE ${tbl_radusergroup}.username = ?
                        AND ${tbl_radgroupcheck}.attribute = ?
                    ORDER BY ${tbl_radusergroup}.priority DESC LIMIT 1";

        $stm = $pdo->prepare($sql_group);
        $stm->execute([$username, $attribute]);
    }

    $result = $stm->fetchColumn();
    $stm = null;

	return $result;
}

/**
 * Get Reply attributes for a given user
 */
function get_reply_attribute_values_for_user($username) {

    global $pdo;
    
    $tbl_radreply      = getenv('CONFIG_DB_TBL_RADREPLY');
    $tbl_radusergroup  = getenv('CONFIG_DB_TBL_RADUSERGROUP');
    $tbl_radgroupreply = getenv('CONFIG_DB_TBL_RADGROUPREPLY');
    
    $sql_user = "SELECT attribute,value FROM ${tbl_radreply} WHERE username=?";

    
    $stm_usr = $pdo->prepare($sql_user);
    $stm_usr -> execute([$username]);

    // Get the value for the attribute we configured (the result will be priorized)
    $sql_group = "SELECT ${tbl_radgroupreply}.groupname,
                        ${tbl_radgroupreply}.attribute,
                        ${tbl_radgroupreply}.value 
                    FROM ${tbl_radgroupreply}
                    RIGHT JOIN ${tbl_radusergroup}
                        ON ${tbl_radusergroup}.groupname = ${tbl_radgroupreply}.groupname
                WHERE ${tbl_radusergroup}.username = ?
                ORDER BY ${tbl_radusergroup}.priority DESC";

    $stm_group = $pdo->prepare($sql_group);
    $stm_group ->execute([$username]);


    $result = array_merge_recursive($stm_usr->fetchAll(), $stm_group->fetchAll());
    $stm_group = null;
    $stm_usr   = null;

	return array_values($result);
}

function filter_attribute($attr, $arr_attrs) {
    return array_values(array_filter($arr_attrs, function ($item) use ($attr) {

        if (strtolower($item['attribute']) == strtolower($attr)) {
            return $item;
        }

        return false;
    }));
}

/**
 * Check if a device was previously banned
 */
/*
function was_device_speed_changed($mac) {

    global $pdo;

    $tbl_radacctexceededperiod = getenv('CONFIG_DB_TBL_EXCEEDEDPERIOD');
    $sql_device = "SELECT COUNT(callingstationid) FROM ${tbl_radacctexceededperiod} 
                    WHERE callingstationid = ?";

    $stm = $pdo->prepare($sql_device);
    $stm->execute([$mac]);

    $result = $stm->fetchColumn();
    $stm    = null;

    return $result;
}
//*/

/**
 * Function to check the user traffic limits
 * @var $username string
 * @var $max_speed_download_data int
 * @var $max_speed_upload_data int
 * @var $max_speed_total_data int
 * @return boolean true if the user has overpassed the data limits for specified period
 */
function check_user_limits($username, 
                    $max_speed_download_data=0,
                    $max_speed_upload_data=0,
                    $max_speed_total_data=0,
                    $max_speed_period='daily') {

    global $pdo; // PDO Connection to MySQL
    
    // Get the table name
    $tbl_radacctperiod = getenv('CONFIG_DB_TBL_RADACCTPERIOD');

    // Check if there is a valid period if not the user has not overpassed any limit
    $max_speed_period = strtolower($max_speed_period);
    if(!in_array($max_speed_period, array('hourly', 'daily', 'weekly', 'monthly', 'yearly'))) {
        return false;
    }

    // Make the sql for the user and stablished period

    $sql = "SELECT IFNULL(SUM(acctinputoctets),0) as uploaddata, 
                IFNULL(SUM(acctoutputoctets),0) as downloaddata 
            FROM ${tbl_radacctperiod}  WHERE username = ? AND 
                UNIX_TIMESTAMP(acctstarttime) >= UNIX_TIMESTAMP(GET_TIMESTAMP_PERIOD( ? , NOW()))";

    /*/
    $sql = "SELECT IFNULL(SUM(acctinputoctets),0) as uploaddata, 
                    IFNULL(SUM(acctoutputoctets),0) as downloaddata 
                FROM ${tbl_radacctperiod} 
                WHERE username = ?
                AND startperiod >= GET_TIMESTAMP_PERIOD( ? , NOW())";
    //*/
    $stm = $pdo->prepare($sql);
    $stm -> execute([$username, $max_speed_period]);

    $result = $stm->fetch();
    $stm    = null;

    // Getting the data downloaded/uploaded and total
    $downloaded_data = (int)$result['downloaddata'];
    $uploaded_data   = (int)$result['uploaddata'];
    $total_data      = $downloaded_data + $uploaded_data;

    // Check if the maximums are right before check them
    $max_speed_download_data = empty($max_speed_download_data) ? 0 : (int)$max_speed_download_data;
    $max_speed_upload_data   = empty($max_speed_upload_data) ? 0 : (int)$max_speed_upload_data;
    $max_speed_total_data    = empty($max_speed_total_data) ? 0 : (int)$max_speed_total_data;

    // Check if the user has overpassed the limits
    // We will check if the maximum is distinc of 0 because we do not want a false
    // positive if there is no limit for any kind of traffic
    $check_download = $max_speed_download_data > 0 && $max_speed_download_data < $downloaded_data;
    $check_upload   = $max_speed_upload_data > 0 && $max_speed_upload_data < $uploaded_data;
    $check_total    = $max_speed_total_data > 0 && $max_speed_total_data < $total_data;

    return  $check_download || $check_upload || $check_total;
}

/**
 * Get the connected users devices in the freeradius
 * @return Array of mac addresses
 */
function get_freeradius_connected_users() {

    global $pdo;

    $tbl_radacct = getenv('CONFIG_DB_TBL_RADACCT');
    $sql_connected = sprintf('SELECT callingstationid
        FROM %s WHERE (AcctStopTime = \'0000-00-00 00:00:00\' OR AcctStopTime IS NULL)', $tbl_radacct);
    
    $stm = $pdo->prepare($sql_connected);
    $stm->execute();
    $result = $stm->fetchAll();
    $stm = null;

    // We can configre how the mac addresses are retrieved and showed by unifi controller
    return array_map(function ($item) { return $item['callingstationid']; }, $result);
}

/**
 * Check the clients in unifi that are not identified in Freeradius. Mean, if
 * there is any device which has not done a logon by a user.
 * @var $client the Unifi client object
 * @return boolean
 */
function get_clients_no_auth_by_users($client) {
    global $freeradius_clients;

    // The network which we will only want authenticated users devices
    $radius_network = strtolower(trim(getenv('RADIUS_NETWORK_CHECK')));
    $client_essid   = isset($client->essid)?strtolower(trim($client->essid)):'';
    $client_network = isset($client->network)?strtolower(trim($client->network)):'';
    $client_mac     = strtoupper(trim(str_replace(':', '-', $client->mac))); // We can configure how controller
                // give to us the mac address, because of that we want it in the same format of the freeradius
                // to compare it

    // Get all clients for the network we want to check

    return (in_array($radius_network, array($client_essid, $client_network))
        && !in_array($client_mac, $freeradius_clients));
}

/**
 * Check the clients that are not in the radius network
 * @var $client The Unifi client
 * @return boolean
 */
function check_clients_no_radius_network($client) {
    $radius_network = strtolower(trim(getenv('RADIUS_NETWORK_CHECK')));

    $client_essid   = isset($client->essid)?strtolower(trim($client->essid)):'';
    $client_network = isset($client->network)?strtolower(trim($client->network)):'';

    return strlen($client_essid) > 0 && $client_essid !== $radius_network
        || strlen($client_network) > 0 && $client_network !== $radius_network;
}

/**
 * Function to filter an arrar of arrays by value of a given key.
 * If the variable does not exists then it takes the value "null".
 * @var $array Array of arrays to search
 * @var $key the subindex key to compare with $value
 * @var $value the expected value
 * @var $op the operator to compare with. It should be as string
 * @var $cb Callback for the value of the $item[$key]
 * @return boolean
 */
function filter_array_key_value($array, $key, $value, $op='default', $cb = null) {
    return array_values(array_filter($array, function ($item) use($key, $value, $op){

        $item_cmp = isset($item[$key]) ? $item[$key] : null;

        if (isset($cb) && is_string($cb) && is_callable($cb)) {
            $item_cmp = call_user_func($cb, $item_cmp);
        } else if (isset($cb) && is_callable($cb)) {
            $item_cmp = $cb($item_cmp);
        }

        switch($op) {
            case '===':
                return $item_cmp === $value;
                break;
            case '==':
                return $item_cmp == $value;
                break;
            case '!=':
                return $item_cmp != $value;
                break;
            case '!==':
                return $item_cmp !== $value;
                break;
            case '>':
                return $item_cmp > $value;
                break;
            case '<':
                return $item_cmp < $value;
                break;
            case 'in_array':
                return in_array($item_cmp, $value);
            default:
                return strtolower($item_cmp) == strtolower($value);
        }
    }));
}

/**
 * Function to filter an arrar of arrays by value of a given key. If the param does not exists it takes the value NULL.
 * The $cb is applied if it is set and it is a callable function
 * @var $array Array of arrays to search
 * @var $param the object attribute to compare with $value
 * @var $value the expected value
 * @var $op the operator to compare with. It should be as string
 * @var $cb A callback for the value of the $param value in the object (of the array)
 * @return boolean
 */
function filter_array_object_value($array, $param, $value, $op='default', $cb = null) {
    return array_values(array_filter($array, function ($item) use($param, $value, $op){

        $item_cmp = isset($item->{$param}) ? $item->{$param} : null;

        if (isset($cb) && is_string($cb) && is_callable($cb)) {
            $item_cmp = call_user_func($cb, $item_cmp);
        } else if (isset($cb) && is_callable($cb)) {
            $item_cmp = $cb($item_cmp);
        }

        

        switch($op) {
            case '!==':
                return $item_cmp !== $value;
                break;
            case '!=':
                return $item_cmp != $value;
                break;
            case '===':
                return $item_cmp === $value;
                break;
            case '==':
                return $item_cmp == $value;
                break;
            case '>':
                return $item_cmp > $value;
                break;
            case '<':
                return $item_cmp < $value;
                break;
            case 'in_array':
                return in_array($item_cmp, $value);
                break;
            default:
                return strtolower($item_cmp) == strtolower($value);
        }
    }));
}

/**
 * Function to check the availability of a service
 * in a given ip or fqdn
 */
function check_service_online($ipaddr, $port) {

    $socket = @fsockopen($ipaddr, $port, $errno, $errstr, 2);
    $online = ($socket !== false);
    
    if ($online) {
        @fclose($socket);
    }

    return $online;
}


function modify_auto_increment_behaviour() {
    // Fix auto increment id. It is not a bug, is a expected behaviour but we do not want
    // to increment the auto_increment value (id) every time we call this script.
    // If we execute this script every minute with a high number of antennas could be a problem
    //
    // More about this:
    //      https://stackoverflow.com/questions/14383503/on-duplicate-key-update-same-as-insert

    global $pdo;

    $sql = sprintf('SELECT IFNULL((SELECT IFNULL(NULLIF(id,0),1) as nextid FROM %s ORDER BY id DESC LIMIT 1),1)', getenv('CONFIG_DB_TBL_RADNAS'));

    $stm = $pdo->query($sql);
    $nextId = $stm->fetchColumn(0);

    $sql = sprintf('ALTER TABLE %s AUTO_INCREMENT=%s', getenv('CONFIG_DB_TBL_RADNAS'), $nextId);
    $pdo->query($sql);

    return;
}