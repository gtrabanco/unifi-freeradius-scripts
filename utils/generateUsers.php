<?php

$prefix       = '';
$amount_users = 1;
$userlen      = 12;
$passwordlen  = 12;
$chars        = 'abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ0123456789';
    // He quitado la "o" mayúscula porque genera dudas con el "0" (cero)
    // He quitado la "i" mayúscula y la "L" minúscula
$strSeparator = ',';

if ($argc < 3) {
    echo "\nUsage: {$argv[0]} <prefix> <amount_of_users> [<user_len> [<password_len>  [<string_of_validchars>]]]\n";
    echo "The prefix should be lower than the total len of user.\n";
    echo "This script will output a list to import in the daloRadius\n";
    exit;
}

$prefix = $argv[1];
$amount_users = $argv[2];
$userlen = isset($argv[3]) && $argv[3]? $argv[3]: $userlen;
$userlen = $userlen - strlen($prefix);

$passwordlen = isset($argv[4]) && $argv[4] ? $argv[4] : $passwordlen;
$chars = isset($argv[5]) && $argv[5] ? $argv[5] : $chars;

function generateRandomString($len, $chars) {
    $randomstring = '';
    for($i=0; $i<$len; $i++) {
        $rchar = rand(0, strlen($chars)-1);
        $randomstring .= $chars[$rchar];
    }

    return $randomstring;
}


$users = array();
do {

    array_push($users, array( 
        ($prefix . generateRandomString($userlen,$chars)),
        generateRandomString($passwordlen, $chars)
    ));

    --$amount_users;
} while($amount_users > 0);


foreach($users as $user) {
    echo join($user, $strSeparator);
    echo "\n";
}