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

class TableNotCreatedException extends Exception{}

abstract class _Model_{
    public static $_migration_index_length_ = 6;
    public static $_migration_base_dir_ = __DIR__.'/migrations';

    public static function _make_query_($sql){
        return DB::get_instance()->_make_query_($sql);
    }

    public static function _get_table_name_(){
        return static::class;
    }

    public static function _get_migration_dir_(){
        return static::$_migration_base_dir_."/".static::_get_table_name_();
    }

    public static function _get_migration_previous_file_(){ # no override
        $table_name = static::_get_table_name_();
        $dir = static::_get_migration_dir_();
        if(!is_dir($dir)){
            mkdir($dir, 0755, true);
        }
        $pat = "$dir/{$table_name}_*.json";
        $file = '';
        foreach(glob($pat) as $file){}
        return $file;
    }

    public static function _get_migration_current_filename_($previous_filename){ # no override
        $m = preg_match('/0*(\d+)[\D]*$/s', $previous_filename, $matches);
        if($m){
            $number = (int)$matches[1] + 1;
        }else{
            $number = 1;
        }
        $n = strlen("$number");
        $l = static::$_migration_index_length_-$n;
        $new_number = '';
        for($i=0;$i<$l;$i++){
            $new_number .= '0';
        }
        $new_number .= "$number";
        if($m){
            $new_filename = preg_replace('/(\d+)([\D]*)$/s', "$new_number$2", $previous_filename);
        }else{
            $new_filename = static::_get_table_name_()."_$new_number.json";
        }
        return $new_filename;
    }

    public static function _get_migration_current_file($filename){ # no override
        return static::_get_migration_dir_()."/". $filename;
    }

    public static function _save_migration_current_($json){ # no override
        $prev_file = self::_get_migration_previous_file_();
        $new_filename = self::_get_migration_current_filename_(basename($prev_file));
        $new_file = self::_get_migration_current_file($new_filename);
        file_put_contents($new_file, json_encode($json));
    }

    public static function _get_migration_previous_json_(){ # no override
        //null means no previous migration files.
        $filename = self::_get_migration_previous_file_();
        if(empty($filename)){
            return null;
        }else{
            $str = file_get_contents($filename);
            return json_decode($str, true);
        }
    }

    public static function _get_migration_default_json_(){ # no override
        return [
            'table_name'=> static::_get_table_name_(),
            'properties'=> [],
        ];
    }

    public static function _get_properties_(){ # no override
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

    public static function _get_sql_create_(){
        $pjson = self::_get_migration_previous_json_();
        if(!empty($pjson)){
            throw new Exception("Can not recreate table '".static::_get_table_name_()."': recent migration file is not empty.");
        }
        $json = self::_get_migration_default_json_();
        $sql = "CREATE TABLE ".static::_get_table_name_()." (";
        $props = self::_get_properties_();
        $json['properties'] = $props;
        foreach($props as $name => $config){
            $sql .= "$name $config,";
        }
        $sql = rtrim($sql, ',');
        $sql .= ");";
        return [$sql, $json];
    }

    public static function _create_(){
        [$sql, $json] = static::_get_sql_create_();
        $qobj = static::_make_query_($sql);
        self::_save_migration_current_($json);
        return $qobj;
    }

    public static function _get_sql_drop_(){
        $sql = "DROP TABLE ".static::_get_table_name_();
        return $sql;
    }

    public static function _drop_(){
        $qobj = static::_make_query_(static::_get_sql_drop_());
        self::_save_migration_current_('');
        return $qobj;
    }

    public static function _get_sql_schema_(){
        $sql = "SELECT * from INFORMATION_SCHEMA.COLUMNS where TABLE_NAME='".static::_get_table_name_()."'";
        return $sql;
    }

    public static function _get_schema_(){
        return static::_make_query_(static::_get_sql_schema_())->fetchALL(PDO::FETCH_ASSOC);
    }

    public static function _get_changed_props_($curs, $pres){ # no override
        $ops = [];
        while(($cur=each($curs)) | ($pre=each($pres))){

            //handle cur properties
            if(!empty($cur['key'])){
                if(array_key_exists($cur['key'], $pres)){
                    //cur_key exists in the pres, it has not been deleted but may be modified
                    if($cur['value'] != $pres[$cur['key']]){
                        //modified
                        $ops[$cur['key']] = [
                            'op'=> 'mod',
                            'cur_key'=> $cur['key'],
                            'cur_val'=> $cur['value'],
                            'pre_key'=> $cur['key'],
                            'pre_val'=> $pres[$cur['key']],
                        ];
                    }else{
                        //unmodified
                        //pass
                    }
                }else{
                    //cur_key does not exist in pres, it has been added a new.
                    $ops[$cur['key']] = [
                        'op'=> 'add',
                        'cur_key'=> $cur['key'],
                        'cur_val'=> $cur['value'],
                    ];
                }
            }
            //handle pre properties
            if(!empty($pre['key'])){
                if(array_key_exists($pre['key'], $curs)){
                    //it has already been handled by cur handler
                    //nothing to do here
                }else{
                    // key is either deleted or renamed
                    if(!empty($cur['key']) && !array_key_exists($cur['key'], $pres)){
                        //renamed
                        $ops[$cur['key']] = [
                            'op'=> 'rename',
                            'cur_key'=> $cur['key'],
                            'cur_val'=> $cur['value'],
                            'pre_key'=> $pre['key'],
                            'pre_val'=> $pre['value'],
                        ];
                    }else{
                        //deleted
                        $ops[$pre['key']] = [
                            'op'=> 'delete',
                            'pre_key'=> $pre['key'],
                            'pre_val'=> $pre['value'],
                        ];
                    }

                }
            }
        }
        return $ops;
    }

    public static function _display_($msg){
        echo "$msg\n";
    }

    public static function _disp_head_($msg, $char='='){
        $n = strlen($msg);
        for($i=0;$i<$n;$i++) echo $char;
        echo "\n$msg\n";
        for($i=0;$i<$n;$i++) echo $char;
        echo "\n";
    }


    public static function _get_alter_column_sql_($op){
        $table_name = static::_get_table_name_();
        if($op['op'] == 'rename'){
            self::_display_("> RENAME: {$op['pre_key']} --> {$op['cur_key']} {$op['cur_val']}");
            $sql = "ALTER TABLE $table_name CHANGE COLUMN {$op['pre_key']} {$op['cur_key']} {$op['cur_val']};";
        }elseif($op['op'] == 'mod'){
            self::_display_("> MODIFY: {$op['cur_key']}: {$op['pre_val']} --> {$op['cur_val']}");
            $sql = "ALTER TABLE $table_name MODIFY {$op['cur_key']} {$op['cur_val']};";
        }elseif($op['op'] == 'delete'){
            self::_display_("> DELETE: {$op['pre_key']} {$op['pre_val']}");
            $sql = "ALTER TABLE $table_name DROP COLUMN {$op['pre_key']};";
        }elseif($op['op'] == 'add'){
            self::_display_("> ADD   : {$op['cur_key']} {$op['cur_val']}");
            $sql = "ALTER TABLE $table_name ADD {$op['cur_key']} {$op['cur_val']};";
        }else{
            $sql = '';
        }
        return $sql;
    }

    public static function _get_sql_change_(){
        $json = self::_get_migration_previous_json_();
        if(empty($json)){
            throw new TableNotCreatedException("Can not do any db operation on table '".static::_get_table_name_()."'. Either there are no migration files (Table was not created to begin with) or table was deleted (most recent migration file is empty)");
        }
        $cprops = self::_get_properties_();
        $pprops = $json['properties'];

        $new_json = self::_get_migration_default_json_();
        $new_json['properties'] = $cprops;

        $ops = self::_get_changed_props_($cprops, $pprops);
        $sql = '';
        $hmsg = static::class." [Migrations]";
        self::_disp_head_($hmsg);
        foreach($ops as $k=>$op){
            $sql .= static::_get_alter_column_sql_($op);
        }
        if(empty($sql)){
            self::_display_("> No changes detected.");
        }
        return [$sql, $new_json];
    }

    public static function _change_(){
        [$sql, $json] = static::_get_sql_change_();
        if(empty($sql)) return FALSE;
        $qobj = static::_make_query_($sql);
        self::_save_migration_current_($json);
        return $qobj;
    }

    public static function _change_or_create_(){
        try {
            static::_change_();
        } catch(TableNotCreatedException $e){
            static::_create_();
        }
    }

    public static function _get($where='1', $args=[], $options=array()){
        $sql = "select * from ".static::_get_table_name_()." where $where";
        $stmt = DB::mquery($sql, $args, $options);
        $stmt->setFetchMode(PDO::FETCH_CLASS, static::class, []);
        return $stmt;
    }

    public function _insert_(){

    }
}

abstract class Model extends _Model_{
    /* *
     * All properties that does not start with an underscores, will be treated as
     * database table feild/column.
     *
     * Property name is the column name, and the property value is the sql definition
     * of that column. e.g:
     *
     * ```php
     * $username = 'varchar(65)'
     * ```
     *
     * In above, username will be the column name and 'varchar(65)' will be the
     * sql definition of this column.
     * */

    public static $id = 'INT PRIMARY KEY AUTO_INCREMENT';
    public static $_pk_ = 'id';
}


class Users extends Model{

    public $username = 'varchar(262)';
    public $aaa = 'varchar(635)';
    public $dfd = 'varchar(37)';
    public $dfc = 'varchar(77)';
    public $query = 'varchar(34)';

}


$u = new Users();

// var_dump(Users::_get_table_name_());
// var_dump($u->_get_table_name_());
// var_dump(Users::_get_properties_());

// var_dump(Users::_get_sql_create_());
// Users::_drop_();
// Users::_create_();
// var_dump(Users::_get_sql_schema_('username'));
// var_dump(Users::_get_schema_('username'));

// var_dump(Users::_get_migration_current_filename_(''));

// var_dump(Users::_get_sql_change_());
// Users::_change_or_create_();

Users::_make_query_("select *");
