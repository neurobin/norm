<?php

require __DIR__ . '/vendor/autoload.php';

define('DB_DRIVER', 'mysql');
define('DB_HOST', 'localhost');
define('DB_USER', 'jahid');
define('DB_PASSWORD', 'jahid');
define('DB_NAME', 'test');
define('DB_CHARSET', 'utf8');


use Norm\DB;
use Norm\Model;


$d = DB::make_query("SHOW TABLES;");

var_dump($d);



class MysqlBase extends Model{

    public static $_col_created_at = 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP';
    public static $_col_updated_at = 'DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
}

class Users extends MysqlBase{

    public static $_col_username = 'varchar(262)';
    public static $_dfl_username = 'John Doe';

    public static $_col_aaa = 'varchar(635)';
    public static $_col_dfde = 'varchar(37)';
    public static $_col_dfcea = 'varchar(77)';
    public static $_col_query = 'varchar(34)';

    public static function _dfl_query(){
        return 'Something';
    }
}

Users::_change_or_create_();

$u = new Users();

$u->aaa = 'fdsfsdfd';
$u->_save();
$u->dfd = "some";
var_dump($u->_assoc());
$u->_save();

// var_dump(Users::_get_table_name_());
// var_dump($u->_get_table_name_());
// var_dump(Users::_get_properties_());

// var_dump(Users::_get_sql_create_());
// Users::_drop_();
// Users::_create_();
// Users::_change_or_create_();
// var_dump(Users::_get_sql_schema_('username'));
// var_dump(Users::_get_schema_('username'));

// var_dump(Users::_get_migration_current_filename_(''));

// var_dump(Users::_get_sql_change_());
// var_dump($u->_get_assoc());
// $u->_insert();

$stmt = Users::_select();

while($user=$stmt->fetch()){
    var_dump($user);
    $user->aaa = "fdsfdsfdsfdf";
    // $user->_insert();
    $user->_update();
    // $user->_delete();

    break;
}

// Users::_make_query_("select *");
