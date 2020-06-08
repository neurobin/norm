<?php namespace Norm;

class Migrate{

    public static function run($class, $apply=false){
        $class::_change_or_create_($apply);
    }

    public static function run_alien($class, $apply=false){
        $prev = $class::_get_migration_previous_file_();
        if(empty($prev)){
            $json = $class::_get_migration_current_json_();
            $class::_save_migration_current_($json);
        }else{
            //no-op;
        }
        $class::_change_($apply);
    }

}
