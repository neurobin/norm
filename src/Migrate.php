<?php namespace Norm;

class Migrate{

    public static function run($class){
        $class::_change_or_create_();
    }

    public static function run_alien($class){
        $prev = $class::_get_migration_previous_file_();
        if(empty($prev)){
            $json = $class::_get_migration_current_json_();
            $class::_save_migration_current_($json);
        }else{
            //no-op;
        }
        $class::_change_();
    }

}
