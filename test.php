<?php

require __DIR__ . '/vendor/autoload.php';
require 'db_config.php';

require 'models.php';
use Users;

$stmt = Users::_select();

while($user=$stmt->fetch()){
    var_dump($user);
    $user->username = 'john_doe_'.random_int(1, 34);
    $user->_save();
    var_dump($user);
    break;
}
