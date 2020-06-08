<?php namespace Norm;

use Norm\Exceptions\TableNotCreatedException;
use Norm\DB;
use Exception;
use ReflectionClass;
use PDO;

abstract class _Model_{
    private static $column_prefix = '_col_';
    private static $default_prefix = '_dfl_';

    public static $_migration_index_length_ = 6;
    public static $_migration_base_dir_ = __DIR__.'/migrations';

    public static function _make_query_($sql){
        /**
         * Override this method if you want to provide your own PDO DB utility implementation.
         */
        $options = array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES   => TRUE, // must be enabled by default.
        );
        return DB::make_query($sql, [], $options);
    }

    public static function _get_table_name_(){
        /**
         * Table name for your model. Override if you want custom table name.
         */
        return str_replace('\\', '_', static::class);
    }

    public static function _get_migration_dir_(){
        /**
         * Override if you want to use a different migration dir. Default is
         * the directory where this (internal _Model_) file resides.
         */
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
        $plength = strlen(self::$column_prefix);
        foreach($chain as $class){
            $cprops = (new ReflectionClass($class))->getDefaultProperties();
            foreach($cprops as $name => $prop){
                $prefix = substr($name, 0, $plength);
                $suffix = substr($name, $plength);
                if($prefix === self::$column_prefix){
                    if($suffix[0] == '_'){
                        throw new Exception("We do not allow column name starting with underscore (_): $suffix");
                    }
                    $props[$suffix] = $prop;
                }
            }
        }
        return $props;
    }

    public static function _get_sql_create_(){
        /**
         * Get the sql for creating table for the model.
         */
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

    public static function _create_($apply){
        /**
         * Create a table for the model.
         */
        [$sql, $json] = static::_get_sql_create_();
        if(empty($sql)) return FALSE;
        if($apply){
            $qobj = static::_make_query_($sql);
            self::_save_migration_current_($json);
            return $qobj;
        }
        echo "\n^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^\n";
        echo "=== This was a dry run, no changes were applied. ===\n";
        echo "====================================================\n\n";
        return TRUE;
    }

    public static function _get_sql_drop_(){
        /**
         * Get sql to delete the table.
         */
        $sql = "DROP TABLE ".static::_get_table_name_();
        return $sql;
    }

    public static function _drop_(){
        /**
         * Delete the table. Dangerous action. You must not use this unless you know what you
         * are doing.
         */
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
        // var_dump($curs);
        // var_dump($pres);
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

    public static function _get_migration_current_json_from_cprops_($cprops){
        $new_json = self::_get_migration_default_json_();
        $new_json['properties'] = $cprops;
        return $new_json;
    }

    public static function _get_migration_current_json_(){
        $cprops = self::_get_properties_();
        return self::_get_migration_current_json_from_cprops_($cprops);
    }

    public static function _get_sql_change_(){
        $json = self::_get_migration_previous_json_();
        if(empty($json)){
            throw new TableNotCreatedException("Can not do any db operation on table '".static::_get_table_name_()."'. Either there are no migration files (Table was not created to begin with) or table was deleted (most recent migration file is empty)");
        }
        $cprops = self::_get_properties_();
        $pprops = $json['properties'];

        $new_json = self::_get_migration_current_json_from_cprops_($cprops);

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

    public static function _change_($apply){
        /**
         * Detect and apply changes to a model.
         */
        [$sql, $json] = static::_get_sql_change_();
        if(empty($sql)) return FALSE;
        if($apply){
            $qobj = static::_make_query_($sql);
            self::_save_migration_current_($json);
            return $qobj;
        }
        echo "\n^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^\n";
        echo "=== This was a dry run, no changes were applied. ===\n";
        echo "====================================================\n\n";
        return TRUE;
    }

    public static function _change_or_create_($apply){
        /**
         * Detect and apply changes to a model if previous migration exists, otherwise create a new one.
         */
        try {
            return static::_change_($apply);
        } catch(TableNotCreatedException $e){
            return static::_create_($apply);
        }
    }

    public function _get_property_value_($n){
        if(isset($this->$n)){
            $v = $this->$n;
        }else{
            $default_var_name = self::$default_prefix.$n;
            if(method_exists($this, $default_var_name)){
                $v = call_user_func_array(array($this, $default_var_name), []);
            }elseif(isset($this->$default_var_name)){
                $v = $this->$default_var_name;
            }elseif(isset(static::$$default_var_name)){
                $v = static::$$default_var_name;
            }else{
                $v = NULL;
            }
        }
        return $v;
    }

    public function _assoc($exclude_values=[], $exclude_keys=[], $strict=TRUE){
        /**
         * Get the model data as an associative array.
         *
         * if $strict is false, exclude values/keys will be loosely compared.
         */
        $this_props = get_object_vars($this);
        $all_props = static::_get_properties_();
        $props = array();
        foreach($all_props as $n=>$v){
            if(in_array($n, $exclude_keys, $strict)) continue;
            $v = $this->_get_property_value_($n);
            if(in_array($v, $exclude_values, $strict)) continue;
            $props[$n] = $v;
        }
        return $props;
    }

    public static function _select($what='*', $where='1', $where_values=[], $options=array()){
        /**
         * SELECT $what FROM this_model_table WHERE $where
         */
        $sql = "SELECT $what FROM ".static::_get_table_name_()." WHERE $where";
        if(empty($options)){
            $options = array(
                PDO::ATTR_EMULATE_PREPARES   => FALSE,
                PDO::ATTR_STRINGIFY_FETCHES => FALSE,
            );
        }
        $stmt = DB::make_query($sql, $where_values, $options);
        $stmt->setFetchMode(PDO::FETCH_CLASS, static::class, []);
        return $stmt;
    }

    public static function _filter($where='1', $where_values=[], $options=array()){
        /**
         * SELECT * FROM this_model_table WHERE $where
         */
        return static::_select('*', $where, $where_values, $options);
    }

    public function _insert($exclude_values=[], $exclude_keys=[], $strict=TRUE){
        /**
         * Insert the data that corrsponds to this object. Will throw error if item
         * already exists.
         */
        $pk = static::$_pk_;
        $data = $this->_assoc($exclude_values, $exclude_keys, $strict);
        if($data[$pk] !== NULL){
            throw new Exception("Can not insert, item exists: ". print_r($data, true));
        }
        unset($data[$pk]);
        DB::insert_assoc(static::_get_table_name_(), $data, $pk);
        $this->$pk = $data[$pk];
    }

    public function _update($exclude_values=[], $exclude_keys=[], $strict=TRUE){
        /**
         * Update the model data. Throws error if does not exist.
         */
        $pk = static::$_pk_;
        if(!isset($this->$pk) || $this->$pk === NULL){
            throw new Exception("Can not update, item does not exist: ". print_r($this, true));
        }
        $pkv = $this->$pk; //pk must be available, thus we can access it directly.
        $data = $this->_assoc($exclude_values=[], $exclude_keys=[], $strict);
        unset($data[$pk]);
        $where = "$pk=?";
        $where_values = [$pkv];
        DB::update_assoc(static::_get_table_name_(), $data, $where, $where_values);
    }

    public function _save($exclude_values=[], $exclude_keys=[], $strict=TRUE){
        /**
         * Saves the model data to db table. Updates if exists and creates if it does not.
         */
        $pk = static::$_pk_;
        if(!isset($this->$pk) || $this->$pk === NULL){
            $this->_insert($exclude_values, $exclude_keys, $strict);
        }else{
            $this->_update($exclude_values, $exclude_keys, $strict);
        }
    }

    public function _delete(){
        /**
         * Deletes the model data corresponding to this object.
         */
        $pk = static::$_pk_;
        if(!isset($this->$pk) || $this->$pk === NULL){
            throw new Exception("Can not delete, item does not exist: ". print_r($this, true));
        }
        $pkv = $this->$pk; //pk must be available, thus we can access it directly.
        $where = "$pk=?";
        $where_values = [$pkv];
        DB::delete_where(static::_get_table_name_(), $where, $where_values);
    }
}
