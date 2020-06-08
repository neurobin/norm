<?php namespace Norm;

class Migrate{
    private $apply_method;
    private $apply = false;
    public static $apply_methods = array(
        'apply'=> 'apply', // the value is used for CLI argument
        'dry'=> 'dry',
        'na'=> 'na',
    );

    public static function print_help(){
        echo "
Available options:
    ".self::$apply_methods['apply']."     Apply all
    ".self::$apply_methods['dry']."       Apply none
    ".self::$apply_methods['na']."        N/A
        \n";
    }

    public function __construct(){
        global $argv;
        if(!empty($argv[1]) && $argv[1] == self::$apply_methods['apply']){
            $this->apply_method = self::$apply_methods['apply'];
            $this->apply = true;
        }
        elseif(!empty($argv[1]) && $argv[1] == self::$apply_methods['dry']){
            $this->apply_method = self::$apply_methods['dry'];
            $this->apply = false;
        }
        elseif(!empty($argv[1]) && $argv[1] == self::$apply_methods['na']){
            $this->apply_method = self::$apply_methods['na'];
            $this->apply = false;
        }
        elseif(!empty($argv[1])){
            self::print_help();
            exit(1);
        }
        else{
            $this->apply_method = self::$apply_methods['na'];
            $this->apply = false;
        }
    }

    public static function run($class, $apply=false){
        return $class::_change_or_create_($apply);
    }

    public static function run_alien($class, $apply=false){
        $prev = $class::_get_migration_previous_file_();
        if(empty($prev)){
            $json = $class::_get_migration_current_json_();
            $class::_save_migration_current_($json);
        }else{
            //no-op;
        }
        return $class::_change_($apply);
    }

    public function get_ans($status){
        $msg = "
Type a command to specify what you want to do:
    yes     I want to apply this migration
    none    I do not want to apply any migration.
    all     I want to apply all migrations (Dangerous/Blind action).

Just hit enter to not apply migration for this model.
        \n";
        if($this->apply_method === self::$apply_methods['na'] && $status){
            echo $msg;
            $ans = readline("Type a command: ");
            readline_add_history($ans);
            if($ans == 'yes' || $ans == 'all'){
                if($ans == 'all'){
                    $this->apply_method = self::$apply_methods['apply'];
                    $this->apply = true;
                }
                return TRUE;
            }elseif($ans == 'none'){
                $this->apply_method = self::$apply_methods['dry'];
            }else{
                $this->apply_method = self::$apply_methods['na'];
            }
        }
        return FALSE;
    }

    public static function run_all($migrate_native, $migrate_alien){

        $me = new self();

        // The native migration runner
        foreach($migrate_native as $class){
            $status = self::run($class, $me->apply);
            if($me->get_ans($status)){
                self::run($class, true);
            }
        }

        // The alien migration runner
        foreach($migrate_alien as $class){
            $status = self::run_alien($class, $apply);
            if($me->get_ans($status)){
                self::run_alien($class, true);
            }
        }
    }
}
