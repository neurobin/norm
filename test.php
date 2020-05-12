<?php

define('DB_DRIVER', 'mysql');
define('DB_HOST', 'localhost');
define('DB_USER', 'jahid');
define('DB_PASSWORD', 'jahid');
define('DB_NAME', 'test');
define('DB_CHARSET', 'utf8');
require_once 'DB.php';


$d = DB::mquery("SHOW TABLES;");

var_dump($d);

abstract class Model{

    public static $_migration_index_length = 6;
    public static $_migration_base_dir = __DIR__.'/migrations';

    public static $id = 'INT PRIMARY KEY AUTO_INCREMENT';

    public static function make_query($sql){
        return DB::get_instance()->query($sql);
    }

    public static function get_table_name(){
        return static::class;
    }

    public static function _get_migration_dir(){
        return static::$_migration_base_dir."/".static::get_table_name();
    }

    public static function _get_migration_previous_file(){
        $table_name = static::get_table_name();
        $dir = static::_get_migration_dir();
        if(!is_dir($dir)){
            mkdir($dir, 0755, true);
        }
        $pat = "$dir/{$table_name}_*.json";
        $file = '';
        foreach(glob($pat) as $file){}
        return $file;
    }

    public static function _get_migration_current_filename($previous_filename){
        $m = preg_match('/0*(\d+)[\D]*$/s', $previous_filename, $matches);
        if($m){
            $number = (int)$matches[1] + 1;
        }else{
            $number = 1;
        }
        $n = strlen("$number");
        $l = static::$_migration_index_length-$n;
        $new_number = '';
        for($i=0;$i<$l;$i++){
            $new_number .= '0';
        }
        $new_number .= "$number";
        if($m){
            $new_filename = preg_replace('/(\d+)([\D]*)$/s', "$new_number$2", $previous_filename);
        }else{
            $new_filename = static::get_table_name()."_$new_number.json";
        }
        return $new_filename;
    }

    public static function _get_migration_current_file($filename){
        return static::_get_migration_dir()."/". $filename;
    }

    public static function _save_migration_current($json){
        $prev_file = static::_get_migration_previous_file();
        $new_filename = static::_get_migration_current_filename(basename($prev_file));
        $new_file = static::_get_migration_current_file($new_filename);
        file_put_contents($new_file, json_encode($json));
    }

    public static function _get_migration_previous_json(){
        //null means no previous migration files.
        $filename = static::_get_migration_previous_file();
        if(empty($filename)){
            return null;
        }else{
            $str = file_get_contents($filename);
            return json_decode($str, true);
        }
    }

    public static function _get_migration_default_json(){
        return [
            'table_name'=> static::get_table_name(),
            'properties'=> [],
        ];
    }

    public static function get_properties(){
        $class = static::class;
        $chain = array_reverse(class_parents($class), true) + [$class => $class];
        $props = [];
        foreach($chain as $class){
            $cprops = (new ReflectionClass($class))->getDefaultProperties();
            foreach($cprops as $name => $prop){
                if($name[0] == '_') continue;
                $props[$name] = $prop;
            }
            // $props += $prop;
        }
        return $props;
    }

    public static function get_sql_create(){
        $pjson = static::_get_migration_previous_json();
        if(!empty($pjson)){
            throw new Exception("Can not recreate table '".static::get_table_name()."': recent migration file is not empty.");
        }
        $json = static::_get_migration_default_json();
        $sql = "CREATE TABLE ".static::get_table_name()." (";
        $props = static::get_properties();
        foreach($props as $name => $config){
            $sql .= "$name $config,";
            $json['properties'][$name] = $config;
        }
        $sql = rtrim($sql, ',');
        $sql .= ");";
        return [$sql, $json];
    }

    public static function create(){
        [$sql, $json] = static::get_sql_create();
        $qobj = static::make_query($sql);
        static::_save_migration_current($json);
        return $qobj;
    }

    public static function get_sql_drop(){
        $sql = "DROP TABLE ".static::get_table_name();
        return $sql;
    }

    public static function drop(){
        $qobj = static::make_query(static::get_sql_drop());
        static::_save_migration_current('');
        return $qobj;
    }

    public static function get_sql_schema(){
        $sql = "SELECT * from INFORMATION_SCHEMA.COLUMNS where TABLE_NAME='".static::get_table_name()."'";
        return $sql;
    }

    public static function get_schema(){
        return static::make_query(static::get_sql_schema())->fetchALL(PDO::FETCH_ASSOC);
    }

    public static function get_sql_change(){
        $json = static::_get_migration_previous_json();
        if(empty($json)){
            throw new Exception("Can not do any db operation on table '".static::get_table_name()."'. Either there are no migration files (Table was not created to begin with) or table was deleted (most recent migration file is empty)");
        }
    }

}


class Users extends Model{

    public $username = 'varchar(50)';
    public $aaa = 'varchar(66)';

}


$u = new Users();

// var_dump(Users::get_table_name());
// var_dump($u->get_table_name());
var_dump(Users::get_properties());

// var_dump(Users::get_sql_create());
Users::drop();
Users::create();
// var_dump(Users::get_sql_schema('username'));
// var_dump(Users::get_schema('username'));

var_dump(Users::_get_migration_current_filename(''));
