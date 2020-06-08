<?php

require __DIR__ . '/../vendor/autoload.php';
require 'db_config.php';
require 'models.php';

use models\User;
use Norm\DB;

$d = DB::make_query('SHOW TABLES');

var_dump($d);

// $user = new User(array(
//     'username'=> 'Jahidul Hamid',
//     'password'=> 'fjdsfljadf;sdf',
//     'email'=> 'fdslfjds@fdsl.com',
// ));

// var_dump($user);

// $user->_save();

// $stmt = User::_select();

// while($user=$stmt->fetch()){
//     var_dump($user);
//     // $user->username = 'john_doe_'.random_int(1, 34);
//     // $user->_save();
//     // var_dump($user);
//     break;
// }
