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
    echo "\nUsage: {$argv[0]} <prefix> <amount_of_users> [<password_len>  [<string_of_validchars>]]\n";
    echo "The prefix should be lower than the total len of user.\n";
    echo "This script will output a list to import in the daloRadius\n";
    exit;
}

$prefix = $argv[1];
$amount_users = $argv[2];

$passwordlen = isset($argv[3]) && $argv[3] ? $argv[3] : $passwordlen;
$chars = isset($argv[4]) && $argv[4] ? $argv[4] : $chars;

function generateRandomString($len, $chars) {
    $randomstring = '';
    for($i=0; $i<$len; $i++) {
        $rchar = rand(0, strlen($chars)-1);
        $randomstring .= $chars[$rchar];
    }

    return $randomstring;
}

function fill_user($number, $chars=3) {
    return sprintf("%0${chars}.0d", $number);
}


$users = array();
do {

    array_push($users, array( 
        ($prefix . fill_user($amount_users,4)),
        generateRandomString($passwordlen, $chars)
    ));

    --$amount_users;
} while($amount_users > 0);


foreach($users as $user) {
    echo join($user, $strSeparator);
    echo "\n";
}